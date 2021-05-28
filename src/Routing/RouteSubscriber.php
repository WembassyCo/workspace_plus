<?php

namespace Drupal\workspace_plus\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Note that the second parameter of setRequirement() is a string.
    if ($route = $collection->get('workspaces.switch_to_live')) {
      // $config = \Drupal::config('workspace_plus.settings');
      // $live_workspace_access = empty($config->get('live_workspace_access')) ? ['administrator'] : $config->get('live_workspace_access');
      // $access_array = array_filter($live_workspace_access);
      // $route->setRequirement('_role', (string) implode('+', $access_array));
    }
    if ($route = $collection->get('entity.workspace.collection')) {
      $route->setRequirement('_permission', 'view workspace toolbar');
    }
  }

}
