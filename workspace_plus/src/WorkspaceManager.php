<?php

namespace Drupal\workspace_plus;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\workspaces\WorkspaceManager as BaseManager;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * WorkspaceManager to check compatibilty.
 */
class WorkspaceManager extends BaseManager implements WorkspaceManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function isEntityTypeSupported(EntityTypeInterface $entity_type) {

    $this->blacklist['workflow'] = 'workflow';
    $this->blacklist['workspace'] = 'workspace';
    $this->blacklist['crop'] = 'crop';

    // First, check if we already determined whether this entity type is
    // supported or not.
    if (isset($this->blacklist[$entity_type->id()])) {
      return FALSE;
    }

    if ($entity_type->isRevisionable()) {
      $entity_keys = $entity_type->getKeys();
      return TRUE;
    }

    // This entity type can not belong to a workspace, add it to the blacklist.
    $this->blacklist[$entity_type->id()] = $entity_type->id();
    return FALSE;
  }

}
