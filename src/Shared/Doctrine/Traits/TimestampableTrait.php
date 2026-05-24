<?php
namespace Habeuk\FileUploaderBundle\Shared\Doctrine\Traits;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

/**
 * Trait pour la gestion automatique des dates de création et mise à jour
 * NB, il faut ajouter #[ORM\HasLifecycleCallbacks] aux entites qui integres
 * TimestampableTrait.
 */
trait TimestampableTrait {

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  protected \DateTimeImmutable $createdAt;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  protected \DateTimeImmutable $updatedAt;

  /**
   * Callback exécuté avant la persistance en base
   */
  #[ORM\PrePersist]
  public function setTimestampsOnCreate(): void {
    $now = new \DateTimeImmutable();
    if (! isset($this->createdAt)) {
      $this->createdAt = $now;
    }
    $this->updatedAt = $now;
  }

  /**
   * Callback exécuté avant la mise à jour en base
   */
  #[ORM\PreUpdate]
  public function setTimestampsOnUpdate(): void {
    $this->updatedAt = new \DateTimeImmutable();
  }

  /**
   * Getter pour createdAt
   */
  public function getCreatedAt(): \DateTimeImmutable {
    return $this->createdAt;
  }

  /**
   * Getter pour updatedAt
   */
  public function getUpdatedAt(): \DateTimeImmutable {
    return $this->updatedAt;
  }

  /**
   * Force la mise à jour du timestamp updatedAt
   */
  public function touch(): static {
    $this->updatedAt = new \DateTimeImmutable();
    return $this;
  }

  /**
   * Retourne l'âge de l'entité en secondes
   */
  public function getAge(): int {
    return $this->updatedAt->getTimestamp() - $this->createdAt->getTimestamp();
  }

  /**
   * Vérifie si l'entité a été modifiée depuis sa création
   */
  public function hasBeenModified(): bool {
    return $this->createdAt->getTimestamp() !== $this->updatedAt->getTimestamp();
  }

  /**
   * Retourne le temps écoulé depuis la dernière modification
   */
  public function getTimeSinceLastUpdate(): \DateInterval {
    $now = new \DateTimeImmutable();
    return $this->updatedAt->diff($now);
  }

  /**
   * Retourne le temps écoulé depuis la création
   */
  public function getTimeSinceCreation(): \DateInterval {
    $now = new \DateTimeImmutable();
    return $this->createdAt->diff($now);
  }
}