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
  public function imageUploadCkeditor(Request $request, EntityManagerInterface $em, UploaderHelper $uploaderHelper, CacheManager $imagineCacheManager): JsonResponse {
    $datas = [];

    $image = $request->files->get('upload');
    if ($image !== null) {
      /** @var  \Symfony\Component\HttpFoundation\File\UploadedFile $image */
      $fileEntity = new Files();
      $user = $this->getUser();
      if ($user !== null && $user instanceof User) {
        $fileEntity->setUser($user);
      }
      $fileEntity->setPublicFile($image);
      $fileEntity->setType('ckeditor');
      $em->persist($fileEntity);
      $em->flush();
      $datas = $this->formatResponseData($fileEntity, 'publicFile', $uploaderHelper, $imagineCacheManager);
    }
    return $this->json($datas, 200, []);
  }

  #[Route('/file-uploader-bundle/upload/files', name: 'file_uploader_bundle_files', methods: [
    'POST'
  ])]
  public function CreateFiles(Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager, UploaderHelper $uploaderHelper,
    CacheManager $imagineCacheManager): JsonResponse {
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
          $fileEntity->setUser($user);
        $em->persist($fileEntity);
        $entitiesSaved[] = $fileEntity;
      }
      $em->flush();
      foreach ($entitiesSaved as $entity) {
        $datas[] = $this->formatResponseData($entity, $fieldName, $uploaderHelper, $imagineCacheManager);
      }
    }
    return $this->json($datas);
  }

  /**
   * Formate les données de réponse selon le type de fichier.
   *
   * @param Files $entity
   * @param string $fieldName
   * @param UploaderHelper $uploaderHelper
   * @param CacheManager $imagineCacheManager
   * @return array<string, mixed>
   */
  private function formatResponseData(Files $entity, string $fieldName, UploaderHelper $uploaderHelper, CacheManager $imagineCacheManager): array {
    $originalPath = $uploaderHelper->asset($entity, $fieldName);
    $fileType = $entity->getMediaType();
    $data = [
      'id' => $entity->getId(),
      'fileName' => $entity->getOriginalName() ?? $entity->getFileName(),
      'url' => $originalPath,
      'type' => $fileType,
      'mimeType' => $entity->getMimeType(),
      'size' => $this->formatFileSize($entity->getFileSize()),
      'icon' => $this->getFileIcon($fileType)
    ];
    // Miniature uniquement pour les images publiques
    if ($fileType === 'image' && $fieldName === 'publicFile') {
      try {
        if ($originalPath !== null) {
          $data['thumbnail'] = $imagineCacheManager->getBrowserPath($originalPath, 'cadre_max_100x100_');
        }
      }
      catch (\Exception) {
        $data['thumbnail'] = $originalPath;
      }
    }
    // Pour les fichiers privés, ne pas exposer l'URL directe
    if ($fieldName === 'privateFile') {
      $data['url'] = null; // Pas d'accès direct
      $data['isPrivate'] = true;
    }
    return $data;
  }

  /**
   * Retourne une icône FontAwesome selon le type de fichier.
   */
  private function getFileIcon(string $type): string {
    return match ($type) {
      'image' => 'fa-file-image',
      'pdf' => 'fa-file-pdf',
      'archive' => 'fa-file-zipper',
      'document' => 'fa-file-lines',
      'audio' => 'fa-file-audio',
      'video' => 'fa-file-video',
      default => 'fa-file'
    };
  }

  /**
   * Formate la taille du fichier en taille lisible.
   */
  private function formatFileSize(?int $bytes): string {
    if ($bytes === null || $bytes === 0) {
      return '0 B';
    }

    $units = [
      'B',
      'KB',
      'MB',
      'GB',
      'TB'
    ];
    $i = (int) floor(log($bytes, 1024));

    return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
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