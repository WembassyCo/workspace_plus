<?php

namespace Drupal\workspace_plus;

use Drupal\workspaces\WorkspacePublisher as BasePublisher;

/**
 * WorkspacePublisher to handle deployment.
 */
class WorkspacePublisher extends BasePublisher {

  /**
   * Updates the publish function to allow passing list of items to publish.
   *
   * @var array $items - Array of entities to publish.
   */
  public function publish() {
    $publish_access = $this->sourceWorkspace->access('publish', NULL, TRUE);
    if (!$publish_access->isAllowed()) {
      $message = $publish_access instanceof AccessResultReasonInterface ? $publish_access->getReason() : '';
      throw new WorkspaceAccessException($message);
    }

    if ($this->checkConflictsOnTarget()) {
      throw new WorkspaceConflictException();
    }

    $transaction = $this->database->startTransaction();
    try {
      // @todo Handle the publishing of a workspace with a batch operation in
      //   https://www.drupal.org/node/2958752.
      $this->workspaceManager->executeOutsideWorkspace(function () {
        foreach ($this->getDifferringRevisionIdsOnSource() as $entity_type_id => $revision_difference) {

          $entity_revisions = $this->entityTypeManager->getStorage($entity_type_id)
            ->loadMultipleRevisions(array_keys($revision_difference));
          $default_revisions = $this->entityTypeManager->getStorage($entity_type_id)
            ->loadMultiple(array_values($revision_difference));

          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          foreach ($entity_revisions as $entity) {
            // When pushing workspace-specific revisions to the default
            // workspace (Live), we simply need to mark them as default
            // revisions.
            if ($this->canDeploy($entity)) {
              $entity->set('moderation_state', 'published');
              $entity->setSyncing(TRUE);
              $entity->isDefaultRevision(TRUE);

              // The default revision is not workspace-specific anymore.
              $field_name = $entity->getEntityType()->getRevisionMetadataKey('workspace');
              $entity->{$field_name}->target_id = NULL;

              // $entity->original = $default_revisions[$entity->id()];
              $entity->save();
            }
          }
        }
      });
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      watchdog_exception('workspaces', $e);
      throw $e;
    }

    // Notify the workspace association that a workspace has been published.
    $this->workspaceAssociation->postPublish($this->sourceWorkspace);
  }

  /**
   * {@inheritdoc}
   */
  public function getDifferringRevisionIdsOnSource() {

    // Get the Workspace association revisions
    // Which haven't been pushed yet and are in a Deploy state.
    $tracked_entities = $this->workspaceAssociation->getTrackedEntities($this->sourceWorkspace->id());
    foreach ($tracked_entities as $entity_type_id => $revision_difference) {
      foreach (\Drupal::entityTypeManager()->getStorage($entity_type_id)->loadMultipleRevisions(array_keys($revision_difference)) as $entity) {
        if (!$this->canDeploy($entity)) {
          $delta = array_search($entity->id(), $tracked_entities[$entity_type_id]);
          unset($tracked_entities[$entity_type_id][$delta]);
        }
      }
    }

    $message = 'workspace plus getDifferringRevisionIdsOnSource';
    \Drupal::logger('workspace_plus_custom')->notice($message);

    return $tracked_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getDifferringRevisionIdsOnTarget() {
    $target_revision_difference = [];

    $tracked_entities = $this->workspaceAssociation->getTrackedEntities($this->sourceWorkspace->id());

    foreach ($tracked_entities as $entity_type_id => $revision_difference) {
      foreach (\Drupal::entityTypeManager()->getStorage($entity_type_id)->loadMultipleRevisions(array_keys($revision_difference)) as $entity) {
        if (!$this->canDeploy($entity)) {
          $delta = array_search($entity->id(), $tracked_entities[$entity_type_id]);
          unset($tracked_entities[$entity_type_id][$delta]);
        }
      }
    }

    foreach ($tracked_entities as $entity_type_id => $tracked_revisions) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      // Get the latest revision IDs for all the entities that are tracked by
      // the source workspace.
      $query = $this->entityTypeManager
        ->getStorage($entity_type_id)
        ->getQuery()
        ->condition($entity_type->getKey('id'), $tracked_revisions, 'IN')
        ->latestRevision();
      $result = $query->execute();

      // Now we compare the revision IDs which are tracked by the source
      // workspace to the latest revision IDs of those entities and the
      // difference between these two arrays gives us all the entities which
      // have been modified on the target.
      if ($revision_difference = array_diff_key($result, $tracked_revisions)) {
        $target_revision_difference[$entity_type_id] = $revision_difference;
      }
    }

    return $target_revision_difference;
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberOfChangesOnTarget() {
    $total_changes = $this->getDifferringRevisionIdsOnTarget();
    return count($total_changes, COUNT_RECURSIVE) - count($total_changes);
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberOfChangesOnSource() {
    $total_changes = $this->getDifferringRevisionIdsOnSource();
    return count($total_changes, COUNT_RECURSIVE) - count($total_changes);
  }

  /**
   * Returns true if the selected revision can be published.
   */
  protected function canDeploy($entity) {
    $moderation_info = \Drupal::service('content_moderation.moderation_information');
    $config = \Drupal::config('workspace_plus.settings');
    if ($workflow = $moderation_info->getWorkflowForEntity($entity)) {
      $settings = $config->get($workflow->id());
      return (isset($settings[$entity->moderation_state->value]) && $settings[$entity->moderation_state->value] == 1);
    }
    drupal_set_message("No workflow configured for %title", ['%title' => $entity->label()]);
    return 0;
  }

}
