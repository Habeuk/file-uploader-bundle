<?php
namespace Habeuk\FileUploaderBundle\Entity;

use App\Entity\User;
use Habeuk\FileUploaderBundle\Repository\FilesRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Habeuk\FileUploaderBundle\Shared\Doctrine\Traits\ {
  TimestampableTrait,
  UuidTrait,
  OwnerTrait
};
use Habeuk\FileUploaderBundle\Enum\AllowedMimeType;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: FilesRepository::class)]
#[Vich\Uploadable]
#[ORM\HasLifecycleCallbacks]
class Files {
  use TimestampableTrait;
  use UuidTrait;
  use OwnerTrait;

  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  /**
   * Le nom du fichier renommer.
   */
  #[ORM\Column(length: 255, nullable: true)]
  #[Assert\NotBlank]
  private ?string $fileName = null;

  /**
   * Le nom du fichier à la base.
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  private ?string $originalName = null;

  #[ORM\Column(nullable: true)]
  #[Assert\NotBlank]
  private ?int $fileSize = null;

  /**
   * le mine type.
   */
  #[ORM\Column(length: 64, nullable: true)]
  #[Assert\NotBlank]
  private ?string $mimeType = null;

  /**
   * 'profile', 'document', 'gallery'...
   */
  #[ORM\Column(type: "string", length: 50, nullable: true)]
  private ?string $type = null;

  #[ORM\Column()]
  private bool $privateAccess = false;

  /**
   * Description du fichier / alt pour les images.
   */
  #[ORM\Column(length: 255, nullable: true)]
  private ?string $description = null;

  /**
   * Champ pour les fichiers PUBLICS
   */
  #[Vich\UploadableField(mapping: 'publicFile', fileNameProperty: 'fileName', size: 'fileSize', mimeType: 'mimeType', originalName: 'originalName')]
  #[Assert\File(maxSize: '25M', maxSizeMessage: 'Le fichier est trop lourd ({{ size }} {{ suffix }}). Maximum autorisé : {{ limit }} {{ suffix }}', mimeTypes: AllowedMimeType::DEFAULT_VALUES, mimeTypesMessage: "Type de fichier non autorisé.")]
  #[Ignore]
  private ?File $publicFile = null;

  /**
   * Champ pour les fichiers PRIVÉS
   */
  #[Vich\UploadableField(mapping: 'privateFile', fileNameProperty: 'fileName', size: 'fileSize', mimeType: 'mimeType', originalName: 'originalName')]
  #[Assert\File(maxSize: '25M', maxSizeMessage: 'Le fichier est trop lourd ({{ size }} {{ suffix }}). Maximum autorisé : {{ limit }} {{ suffix }}', mimeTypes: AllowedMimeType::DEFAULT_VALUES, mimeTypesMessage: "Type de fichier non autorisé.")]
  #[Ignore]
  private ?File $privateFile = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  protected \DateTimeImmutable $createdAt;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  protected \DateTimeImmutable $updatedAt;

  function __construct() {
    $now = new \DateTimeImmutable();
    $this->createdAt = $now;
    $this->updatedAt = $now;
  }

  public function getPublicFile(): ?File {
    return $this->publicFile;
  }

  public function setFile(?File $file): void {
    $this->setPublicFile($file);
  }

  public function setPublicFile(?File $file): void {
    $this->publicFile = $file;
    $this->updatedAt = new \DateTimeImmutable();
  }

  public function getPrivateFile(): ?File {
    return $this->privateFile;
  }

  public function setPrivateFile(?File $file): void {
    $this->privateFile = $file;
    if (null !== $file) {
      $this->privateAccess = true;
    }
    $this->updatedAt = new \DateTimeImmutable();
  }

  public function getId(): ?int {
    return $this->id;
  }

  public function getPrivateAccess(): bool {
    return $this->privateAccess;
  }

  public function isPrivateFile(): bool {
    return $this->privateAccess;
  }

  public function setPrivateAccess(bool $privateAccess): static {
    $this->privateAccess = $privateAccess;
    return $this;
  }

  public function markAsPrivate(): static {
    $this->privateAccess = true;
    return $this;
  }

  public function markAsPublic(): static {
    $this->privateAccess = false;
    return $this;
  }

  public function getFileName(): ?string {
    return $this->fileName;
  }

  public function setFileName(?string $FileName): static {
    $this->fileName = $FileName;
    return $this;
  }

  public function getFileSize(): ?int {
    return $this->fileSize;
  }

  public function setFileSize(?int $FileSize): static {
    $this->fileSize = $FileSize;

    return $this;
  }

  public function getMimeType(): ?string {
    return $this->mimeType;
  }

  public function setMimeType(?string $MimeType): static {
    $this->mimeType = $MimeType;
    return $this;
  }

  public function getType(): ?string {
    return $this->type;
  }

  public function setType(?string $type): static {
    $this->type = $type;
    return $this;
  }

  public function setOriginalName(?string $originalName): static {
    $this->originalName = $originalName;
    return $this;
  }

  public function getOriginalName(): ?string {
    return $this->originalName;
  }

  public function getDescription(): ?string {
    return $this->description;
  }

  public function setDescription(string $description): self {
    $this->description = $description;
    return $this;
  }

  /**
   * Retourne le type de média déduit du MIME type.
   */
  public function getMediaType(): string {
    return match (true) {
      str_starts_with($this->mimeType ?? '', 'image/') => 'image',
      str_contains($this->mimeType ?? '', 'pdf') => 'pdf',
      str_contains($this->mimeType ?? '', 'zip') || str_contains($this->mimeType ?? '', 'rar') || str_contains($this->mimeType ?? '', 'tar') ||
      str_contains($this->mimeType ?? '', '7z') => 'archive',
      str_contains($this->mimeType ?? '', 'text/') || str_contains($this->mimeType ?? '', 'csv') || str_contains($this->mimeType ?? '', 'json') ||
      str_contains($this->mimeType ?? '', 'xml') => 'document',
      str_starts_with($this->mimeType ?? '', 'audio/') => 'audio',
      str_starts_with($this->mimeType ?? '', 'video/') => 'video',
      default => 'file'
    };
  }
}
