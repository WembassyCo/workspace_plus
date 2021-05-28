<?php
namespace Drupal\workspace_plus;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workspaces\WorkspaceAccessControlHandler;

class WorkspacePlusAccessControlHandler extends WorkspaceAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $accessResult = AccessResult::neutral();
    if ($operation === 'publish' && $entity->hasParent()) {
      $message = $this->t('Only top-level workspaces can be published.');
      return AccessResult::forbidden((string) $message)->addCacheableDependency($entity);
    }

    if ($account->hasPermission('administer workspaces')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if ($operation == 'view' || $operation == 'edit') {
      $accessResult = AccessResult::allowedif(
          $op == 'view' && $account->hasPermission("{$entity->id()} view") ||
          $op == 'edit' && $account->hasPermission("{$entity->id()} edit")
      );
    }

    if ($accessResult->isNeutral()) {
      $accessResult = parent::checkAccess($entity, $operation, $account);
    }
    return $accessResult;
  }
}
