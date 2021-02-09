<?php

namespace Drupal\workspace_plus;

use Drupal\workspaces\WorkspaceOperationFactory as BaseOperationFactory;
use Drupal\workspaces\WorkspaceInterface;

/**
 * Callback to publish content.
 */
class WorkspaceOperationFactory extends BaseOperationFactory {

  /**
   * Gets the workspace publisher.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $source
   *   A workspace entity.
   *
   * @return \Drupal\workspaces\WorkspacePublisherInterface
   *   A workspace publisher object.
   */
  public function getPublisher(WorkspaceInterface $source) {
    return new WorkspacePublisher($this->entityTypeManager, $this->database, $this->workspaceManager, $this->workspaceAssociation, $source);
  }

}
