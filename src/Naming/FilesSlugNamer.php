<?php

// src/Naming/CustomSlugNamer.php
namespace Habeuk\FileUploaderBundle\Naming;

use Vich\UploaderBundle\Naming\NamerInterface;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\Polyfill\FileExtensionTrait;
use Vich\UploaderBundle\Util\Transliterator;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Service dedié à l'entité Files.
 *
 * @author stephane
 * @implements NamerInterface<object>
 */
class FilesSlugNamer implements NamerInterface {
  use FileExtensionTrait;

  public function __construct(private readonly Transliterator $transliterator, private readonly ObjectManager $entityManager) {}

  public function name($object, PropertyMapping $mapping): string {
    /**
     *
     * @var \Habeuk\FileUploaderBundle\Entity\Files $object
     */
    $slug = $this->generateSlug($object, $mapping);
    return $this->makeUnique($slug, $object);
  }

  private function generateSlug(object $object, PropertyMapping $mapping): string {
    $file = $mapping->getFile($object);
    if (! ($file instanceof File))
      throw new \ErrorException("Le fichier n'est pas valide");
    $originalName = $file->getClientOriginalName();
    $extension = $this->getExtension($file);
    $basename = substr(pathinfo($originalName, PATHINFO_FILENAME), 0, 240);
    $basename = strtolower($this->transliterator->transliterate($basename));
    // Nettoyage supplémentaire
    $basename = preg_replace('/[^a-z0-9._-]/', '', $basename);
    if ($basename === null) {
      $basename = 'file';
    }
    return $extension !== null ? sprintf('%s.%s', $basename, $extension) : $basename;
  }

  private function makeUnique(string $slug, object $object): string {
    $base = pathinfo($slug, PATHINFO_FILENAME);
    $extension = pathinfo($slug, PATHINFO_EXTENSION) !== '' ? pathinfo($slug, PATHINFO_EXTENSION) : '';

    $i = 0;
    while (true) {
      $existing = $this->entityManager->getRepository(get_class($object))->findOneBy([
        'fileName' => $slug
      ]);

      if ($existing === null || $existing === $object) {
        return $slug;
      }

      $slug = $extension !== '' ? sprintf('%s-%d.%s', $base, ++ $i, $extension) : sprintf('%s-%d', $base, ++ $i);
    }
  }
}