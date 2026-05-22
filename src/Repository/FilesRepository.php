<?php
namespace Habeuk\FileUploaderBundle\Repository;

use Habeuk\FileUploaderBundle\Entity\Files;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 *
 * @extends ServiceEntityRepository<Files>
 */
class FilesRepository extends ServiceEntityRepository {

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Files::class);
  }
}