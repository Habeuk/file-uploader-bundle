<?php
declare(strict_types = 1);
namespace Habeuk\FileUploaderBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

final class FileUploaderBundle extends AbstractBundle {

  /**
   * Configs internes au bundle
   *
   * {@inheritdoc}
   * @param array<mixed> $config
   * @see \Symfony\Component\HttpKernel\Bundle\AbstractBundle::loadExtension()
   */
  public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void {
    // Charger les services
    $container->import('../config/services.yaml');
  }

  /**
   * Configs que l'application peut surcharger
   *
   * {@inheritdoc}
   * @see \Symfony\Component\HttpKernel\Bundle\AbstractBundle::prependExtension()
   */
  public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void {
    //
    $builder->prependExtensionConfig('doctrine', [
      'orm' => [
        'mappings' => [
          'HabeukFileUploaderBundle' => [
            'type' => 'attribute',
            'dir' => \dirname(__DIR__) . '/src/Entity',
            'prefix' => 'Habeuk\FileUploaderBundle\Entity',
            'is_bundle' => false
          ]
        ]
      ]
    ]);
    //
    $builder->prependExtensionConfig('twig', [
      'paths' => [
        \dirname(__DIR__) . '/templates' => 'HabeukFileUploader'
      ],
      'form_themes' => [
        '@HabeukFileUploader/form/types/files_upload_js.html.twig'
      ]
    ]);
    // VichUploader
    $container->extension('vich_uploader', [
      'db_driver' => 'orm',
      'metadata' => [
        'type' => 'attribute'
      ],
      'mappings' => [
        'publicFile' => [
          'uri_prefix' => '/uploads/public',
          'upload_destination' => '%kernel.project_dir%/public/uploads/public',
          'namer' => 'Habeuk\FileUploaderBundle\Naming\FilesSlugNamer',
          'directory_namer' => [
            'service' => 'Vich\UploaderBundle\Naming\SubdirDirectoryNamer',
            'options' => [
              'dirs' => 3,
              'chars_per_dir' => 3
            ]
          ],
          'inject_on_load' => false,
          'delete_on_remove' => true,
          'delete_on_update' => true
        ],
        'privateFile' => [
          'uri_prefix' => '/var/storage/private',
          'upload_destination' => '%kernel.project_dir%/var/storage/private',
          'namer' => 'Habeuk\FileUploaderBundle\Naming\FilesSlugNamer',
          'directory_namer' => [
            'service' => 'Vich\UploaderBundle\Naming\SubdirDirectoryNamer',
            'options' => [
              'dirs' => 3,
              'chars_per_dir' => 3
            ]
          ],
          'inject_on_load' => false,
          'delete_on_remove' => true,
          'delete_on_update' => true
        ]
      ]
    ]);
  }

  /**
   * Pour definir des configurations.
   *
   * {@inheritdoc}
   * @see \Symfony\Component\HttpKernel\Bundle\AbstractBundle::configure()
   */
  public function configure(DefinitionConfigurator $container): void {}
}
