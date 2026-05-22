<?php
declare(strict_types = 1);
namespace Habeuk\FileUploaderBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class FileUploaderBundle extends AbstractBundle {

  public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void {
    // Charger les services
    $container->import('../config/services.yaml');
  }
}
