<?php
namespace Habeuk\FileUploaderBundle\Shared\Doctrine\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

trait UuidTrait {

  /**
   * --
   */
  #[ORM\Column(type: 'uuid', unique: true)]
  private ?Uuid $uuid = null;

  /**
   * Callback exécuté avant la persistance en base
   */
  #[ORM\PrePersist]
  public function initializeUuid(): void {
    if ($this->uuid === null)
      $this->uuid = Uuid::v4();
  }

  public function getUuid(): ?Uuid {
    return $this->uuid;
  }

  public function setUuid(Uuid $uuid): static {
    $this->uuid = $uuid;
    return $this;
  }
}