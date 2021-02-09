<?php

namespace Drupal\workspace_plus;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the language manager service.
 */
class WorkspaceOperationProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('workspaces.operation_factory');
    $definition->setClass('Drupal\workspace_plus\WorkspaceOperationsFactory');

    $definition = $container->getDefinition('workspaces.manager');
    $definition->setClass('Drupal\workspace_plus\WorkspaceManager');
  }

}
