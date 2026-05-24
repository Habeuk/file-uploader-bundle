<?php
namespace Habeuk\FileUploaderBundle\Shared\Doctrine\Traits;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

trait OwnerTrait {

  /**
   * Le champs est automatiquement remplie lors de la premiere sauvegarde.
   */
  #[ORM\ManyToOne(targetEntity: User::class)]
  #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: true)]
  private ?User $owner = null;

  public function getOwner(): ?User {
    return $this->owner;
  }

  public function setOwner(User $owner): self {
    if (isset($this->owner) && $this->owner === $owner) {
      return $this;
    }
    $this->owner = $owner;
    return $this;
  }

  /**
   * Vérifie si l'utilisateur est le propriétaire
   */
  public function isOwner(User $user): bool {
    return $this->getOwner() !== null && $this->getOwner()->getId() === $user->getId();
  }

  public function ownerIsDefinie(): bool {
    return $this->getOwner() !== null && $this->getOwner()->getId() !== null;
  }
}