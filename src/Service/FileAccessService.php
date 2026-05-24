<?php
declare(strict_types = 1);
namespace Habeuk\FileUploaderBundle\Service;

use Habeuk\FileUploaderBundle\Entity\Files;
use Habeuk\FileUploaderBundle\Repository\FilesRepository;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FileAccessService {

  public function __construct(private readonly FilesRepository $filesRepository, private readonly UploaderHelper $uploaderHelper,
    private readonly CacheManager $imagineCacheManager, private readonly AuthorizationCheckerInterface $authorizationChecker,
    #[Autowire(param: 'kernel.project_dir')] private readonly string $projectDir) {}

  /**
   * Récupère les données formatées d'un fichier pour l'affichage.
   *
   * @return array<string, mixed>
   */
  public function getFileData(Files|int $data, string $filter = 'cadre_max_100x100_'): array {
    $file = $this->findFileOrFail($data);
    return $this->formatFileData($file, $filter);
  }

  /**
   * Sert un fichier public en téléchargement ou affichage.
   */
  public function servePublicFile(Files|int $data): BinaryFileResponse {
    $file = $this->findFileOrFail($data);

    if ($file->getPrivateAccess()) {
      throw new AccessDeniedHttpException('Ce fichier est privé.');
    }

    return $this->createBinaryResponse($file, 'publicFile');
  }

  /**
   * Sert un fichier privé (vérifie les droits d'accès).
   */
  public function servePrivateFile(int $fileId): BinaryFileResponse {
    $file = $this->findFileOrFail($fileId);

    if (! $file->getPrivateAccess()) {
      // Fichier public, rediriger vers la route publique
      throw new NotFoundHttpException('Ce fichier n\'est pas privé.');
    }

    // Vérifier les droits (propriétaire ou admin)
    $this->checkAccess($file);

    return $this->createBinaryResponse($file, 'privateFile');
  }

  /**
   * Récupère le contenu brut d'un fichier.
   *
   * @return string Le contenu du fichier
   */
  public function getFileFullPath(Files|int $data): string {
    $file = $this->findFileOrFail($data);

    $fieldName = $file->getPrivateAccess() ? 'privateFile' : 'publicFile';
    $path = $this->uploaderHelper->asset($file, $fieldName);

    if ($path === null) {
      throw new NotFoundHttpException('Fichier introuvable.');
    }
    if ($file->isPrivateFile()) {
      $this->checkAccess($file);
      $fullPath = $this->projectDir . $path;
    }
    else {
      $fullPath = $this->projectDir . '/public' . $path;
    }
    if (! file_exists($fullPath)) {
      throw new NotFoundHttpException('Fichier introuvable sur le disque.');
    }
    if (! is_readable($fullPath)) {
      throw new AccessDeniedHttpException('Fichier illisible.');
    }
    return $fullPath;
  }

  /**
   * Retourne une miniature pour une image.
   */
  public function getThumbnail(int $fileId, string $filter = 'cadre_max_100x100_'): ?string {
    $file = $this->findFileOrFail($fileId);

    if ($file->getMediaType() !== 'image') {
      return null;
    }

    $path = $this->uploaderHelper->asset($file, 'publicFile');

    if ($path === null) {
      return null;
    }

    try {
      return $this->imagineCacheManager->getBrowserPath($path, $filter);
    }
    catch (\Exception) {
      return $path;
    }
  }

  /**
   * Retourne l'URL publique d'un fichier.
   */
  public function getPublicUrl(int $fileId): ?string {
    $file = $this->findFileOrFail($fileId);

    if ($file->getPrivateAccess()) {
      return null;
    }

    return $this->uploaderHelper->asset($file, 'publicFile');
  }

  /**
   * Retourne l'icône correspondant au type de média.
   */
  public function getFileIcon(Files|int $data): string {
    $file = $this->findFileOrFail($data);

    return $this->resolveIcon($file->getMediaType());
  }

  /**
   * Formate les données d'un fichier en tableau.
   *
   * @return array<string, mixed>
   */
  private function formatFileData(Files $file, string $filter): array {
    $mediaType = $file->getMediaType();
    $fieldName = $file->getPrivateAccess() ? 'privateFile' : 'publicFile';
    $path = $this->uploaderHelper->asset($file, $fieldName);

    $data = [
      'id' => $file->getId(),
      'fileName' => $file->getOriginalName() ?? $file->getFileName(),
      'url' => $path,
      'type' => $mediaType,
      'mimeType' => $file->getMimeType(),
      'size' => $this->formatFileSize($file->getFileSize()),
      'icon' => $this->resolveIcon($mediaType),
      'isPrivate' => $file->getPrivateAccess()
    ];
    // Miniature pour les images publiques
    if ($mediaType === 'image' && ! $file->getPrivateAccess() && $path !== null) {
      try {
        $data['thumbnail'] = $this->imagineCacheManager->getBrowserPath($path, $filter);
      }
      catch (\Exception) {
        $data['thumbnail'] = $path;
      }
    }
    // Masquer l'URL pour les fichiers privés
    if ($file->getPrivateAccess()) {
      $data['url'] = null;
    }
    return $data;
  }

  /**
   * Crée une BinaryFileResponse avec les bons headers.
   */
  private function createBinaryResponse(Files $file, string $fieldName): BinaryFileResponse {
    $path = $this->uploaderHelper->asset($file, $fieldName);

    if ($path === null) {
      throw new NotFoundHttpException('Fichier introuvable.');
    }

    $fullPath = $this->projectDir . '/public' . $path;

    if (! file_exists($fullPath)) {
      throw new NotFoundHttpException('Fichier introuvable sur le disque.');
    }

    return new BinaryFileResponse($fullPath, Response::HTTP_OK, [
      'Content-Type' => $file->getMimeType() ?? 'application/octet-stream',
      'Content-Disposition' => 'inline; filename="' . ($file->getOriginalName() ?? 'file') . '"'
    ]);
  }

  /**
   * Vérifie les droits d'accès au fichier privé.
   */
  private function checkAccess(Files $file): void {
    // Admin : accès total
    if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
      return;
    }

    // Propriétaire : accès à ses propres fichiers
    // (à adapter selon votre logique métier)
  }

  /**
   * Trouve un fichier ou lance une exception.
   */
  private function findFileOrFail(Files|int $data): Files {
    if ($data instanceof Files)
      $file = $data;
    else $file = $this->filesRepository->find($data);

    if ($file === null) {
      throw new NotFoundHttpException(sprintf('Fichier #%d introuvable.', $data));
    }
    return $file;
  }

  private function resolveIcon(string $type): string {
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
}