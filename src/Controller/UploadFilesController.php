<?php

// src/Controller/UrlAliasController.php
namespace Habeuk\FileUploaderBundle\Controller;

/**
 * 'entity_name' => '[a-z-]+', // Uniquement des lettres et tirets (string)
 *
 * @author stephane
 *
 */
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Habeuk\FileUploaderBundle\Entity\ {
  Files
};
use Habeuk\FileUploaderBundle\Service\FileAccessService;
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

  function __construct(private readonly FileAccessService $fileAccess) {}

  #[Route('/image/upload', name: 'file_uploader_bundle_ckeditor')]
  #[IsGranted('ROLE_ADMIN')]
  public function imageUploadCkeditor(Request $request, EntityManagerInterface $em): JsonResponse {
    $datas = [];

    $image = $request->files->get('upload');
    if ($image !== null) {
      /** @var  \Symfony\Component\HttpFoundation\File\UploadedFile $image */
      $fileEntity = new Files();
      $user = $this->getUser();
      if ($user !== null && $user instanceof User) {
        $fileEntity->setOwner($user);
      }
      $fileEntity->setPublicFile($image);
      $fileEntity->setType('ckeditor');
      $em->persist($fileEntity);
      $em->flush();
      $datas = $this->fileAccess->getFileData($fileEntity);
    }
    return $this->json($datas, 200, []);
  }

  #[Route('/file-uploader-bundle/upload/files', name: 'file_uploader_bundle_files', methods: [
    'POST'
  ])]
  public function CreateFiles(Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse {
    $token = (string) $request->request->get('_token_csrf');
    if (! $csrfTokenManager->isTokenValid(new CsrfToken('files_upload_js', $token))) {
      return $this->json([
        'error' => 'Token invalide'
      ], Response::HTTP_BAD_REQUEST);
    }
    $store_type = $request->query->get('store_type');
    $fieldName = $store_type === 'private' ? 'privateFile' : 'publicFile';
    $entities = $this->buildEntitiesArrays($request, $fieldName);

    $entitiesSaved = [];
    $datas = [];
    if ($entities !== []) {
      // On check les contenu.
      foreach ($entities as $data) {
        $fileEntity = new Files();
        $this->hydrateEntity($fileEntity, $data);
        $user = $this->getUser();
        if ($user !== null && $user instanceof User)
          $fileEntity->setOwner($user);
        $em->persist($fileEntity);
        $entitiesSaved[] = $fileEntity;
      }
      $em->flush();
      foreach ($entitiesSaved as $fileEntity) {
        $datas[] = $this->fileAccess->getFileData($fileEntity);
      }
    }
    return $this->json($datas);
  }

  /**
   * Fusionne les données fichiers et métadonnées.
   *
   * @param Request $request
   * @param string $fieldName
   * @param array<mixed> $entitiesFiles
   * @param array<mixed> $entitiesData
   * @return array<mixed>
   */
  protected function buildEntitiesArrays(Request $request, string $fieldName, array $entitiesFiles = [], array $entitiesData = []): array {
    if ($entitiesFiles === [])
      $entitiesFiles = $request->files->all('entities');
    if ($entitiesData === [])
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
   *
   * @param Files $entity
   * @param array<string, mixed> $data
   */
  protected function hydrateEntity(Files $entity, array $data): void {
    foreach ($data as $field => $value) {
      $setter = 'set' . ucfirst($field);
      if (method_exists($entity, $setter)) {
        /* @phpstan-ignore method.dynamicName */
        $entity->$setter($value);
      }
    }
  }
}