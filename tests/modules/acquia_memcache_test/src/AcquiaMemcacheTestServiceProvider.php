<?php

namespace Drupal\acquia_memcache_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Test service provider.
 */
class AcquiaMemcacheTestServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = new Definition('Drupal\Core\Lock\LockBackendInterface');
    $definition->setFactory([new Reference('memcache.lock.factory'), 'get']);

    $container->setDefinition('lock', $definition);
  }

}
