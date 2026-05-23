<?php

// src/Controller/UrlAliasController.php
namespace Habeuk\FileUploaderBundle\Controller;

/**
 * 'entity_name' => '[a-z-]+', // Uniquement des lettres et tirets (string)
 *
 * @author stephane
 *
 */
use Doctrine\ORM\EntityManagerInterface;
use Habeuk\FileUploaderBundle\Entity\ {
  Files
};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Response;

class UploadFilesController extends AbstractController {

  #[Route('/image/upload', name: 'file_uploader_bundle_ckeditor')]
  #[IsGranted('ROLE_ADMIN')]
  public function imageUploadCkeditor(Request $request, EntityManagerInterface $em, UploaderHelper $uploaderHelper, CacheManager $imagineCacheManager) {
    $datas = [];
    /**
     *
     * @var \Symfony\Component\HttpFoundation\File\UploadedFile $image
     */
    $image = $request->files->get('upload');
    if ($image) {
      $fileEntity = new Files();
      $fileEntity->setUser($this->getUser());
      $fileEntity->setFile($image);
      $fileEntity->setType('ckeditor');
      $em->persist($fileEntity);
      $em->flush();
      $originalPath = $uploaderHelper->asset($fileEntity, 'file', Files::class);
      $datas = [
        'urls' => [
          'default' => $originalPath
          // '100' => $imagineCacheManager->generateUrl($originalPath,
          // 'cadre_max_100x100_', [], null,
          // UrlGeneratorInterface::ABSOLUTE_PATH)
        ]
      ];
    }
    return $this->json($datas, 200, []);
  }

  #[Route('/file-uploader-bundle/upload/files', name: 'file_uploader_bundle_files', methods: [
    'POST'
  ])]
  public function CreateFiles(Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager, UploaderHelper $uploaderHelper,
    CacheManager $imagineCacheManager): JsonResponse {
    if (! $csrfTokenManager->isTokenValid(new CsrfToken('files_upload_js', $request->request->get('_token_csrf')))) {
      return $this->json([
        'error' => 'Token invalide'
      ], Response::HTTP_BAD_REQUEST);
    }
    $store_type = $request->query->get('store_type');
    $fieldName = $store_type === 'private' ? 'privateFile' : 'publicFile';
    $entities = $this->buildEntitiesArrays($request, $fieldName);
    \Stephane888\Debug\debugLog::symfonyDebug($entities, 'buildEntitiesArrays', true);
    $entitiesSaved = [];
    $datas = [];
    if ($entities) {
      // On check les contenu.
      foreach ($entities as $entity) {
        $fileEntity = new Files();
        if ($user = $this->getUser())
          $fileEntity->setUser($user);
        $this->hydrateEntity($fileEntity, $entity);
        $em->persist($fileEntity);
        $entitiesSaved[] = $fileEntity;
      }
      $em->flush();
      foreach ($entitiesSaved as $entity) {
        $originalPath = $uploaderHelper->asset($entity, $fieldName, Files::class);
        $datas[] = [
          'id' => $entity->getId(),
          'fileName' => $entity->getFileName(),
          'url' => $originalPath
          // 'thumbnail' => $imagineCacheManager->getBrowserPath($originalPath, 'cadre_max_100x100_'),
          // 'type' => $entity->getType()
        ];
      }
    }
    return $this->json($datas);
  }

  /**
   * Fusionne les données fichiers et métadonnées.
   */
  protected function buildEntitiesArrays(Request $request, string $fieldName, array $entitiesFiles = [], array $entitiesData = []): array {
    if (empty($entitiesFiles))
      $entitiesFiles = $request->files->all('entities');
    if (empty($entitiesData))
      $entitiesData = $request->request->all('entities');
    $entities = [];
    foreach ($entitiesData as $index => $entityData) {
      foreach ($entityData as $key => $value) {
        $entities[$index][$key] = $value;
      }
    }
    foreach ($entitiesFiles as $index => $entityData) {
      foreach ($entityData as $key => $value) {
        if ($key === 'file') {
          $entities[$index][$fieldName] = $value;
        }
      }
    }
    return $entities;
  }

  /**
   * Hydrate l'entité dynamiquement
   */
  protected function hydrateEntity($entity, array $data): void {
    foreach ($data as $field => $value) {
      $setter = 'set' . ucfirst($field);
      if (method_exists($entity, $setter)) {
        $entity->$setter($value);
      }
    }
  }
}