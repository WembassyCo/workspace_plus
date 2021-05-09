<?php

namespace Drupal\workspace_plus;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\workspaces\EntityOperations as BaseEntityOperations;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\workspaces\WorkspaceAssociationInterface;

/**
 * Defines a class for reacting to entity events.
 *
 * @internal
 */
class EntityOperations extends BaseEntityOperations {

  /**
   * The workspace manager service.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a new EntityOperations instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspace_association
   *   The workspace association service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspace_manager, WorkspaceAssociationInterface $workspace_association) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspace_manager;
    $this->workspaceAssociation = $workspace_association;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
     $container->get('entity_type.manager'),
     $container->get('workspaces.manager'),
     $container->get('workspaces.association')
    );
  }

  /**
   * Acts on entity IDs before they are loaded.
   *
   * @see hook_entity_preload()
   */
  public function entityPreload(array $ids, $entity_type_id) {
    $entities = [];

    // Only run if the entity type can belong to a workspace and we are in a
    // non-default workspace.
    if (!$this->workspaceManager->shouldAlterOperations($this->entityTypeManager->getDefinition($entity_type_id))) {
      return $entities;
    }

    // Get a list of revision IDs for entities that have a revision set for the
    // current active workspace. If an entity has multiple revisions set for a
    // workspace, only the one with the highest ID is returned.
    if ($tracked_entities = $this->workspaceAssociation->getTrackedEntities($this->workspaceManager->getActiveWorkspace()->id(), $entity_type_id, $ids)) {
      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($entity_type_id);

      // Swap out every entity which has a revision set for the current active
      // workspace.
      foreach ($storage->loadMultipleRevisions(array_keys($tracked_entities[$entity_type_id])) as $revision) {
        $entities[$revision->id()] = $revision;
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function entityPresave(EntityInterface $entity) {
    $entity_type = $entity->getEntityType();

    // Only run if we are not dealing with an entity type provided by the
    // Workspaces module, an internal entity type or if we are in a non-default
    // workspace.
    if ($this->shouldSkipPreOperations($entity_type)) {
      return;
    }

    // Disallow any change to an unsupported entity when we are not in the
    // default workspace.
    if (!$this->workspaceManager->isEntityTypeSupported($entity_type)) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface|\Drupal\Core\Entity\EntityPublishedInterface $entity */
    if (!$entity->isNew() && !$entity->isSyncing() && $entity->getEntityType()->id() != 'menu_link_content') {
      // Force a new revision if the entity is not replicating.
      $entity->setNewRevision(TRUE);

      // All entities in the non-default workspace are pending revisions,
      // regardless of their publishing status. This means that when creating
      // a published pending revision in a non-default workspace it will also be
      // a published pending revision in the default workspace, however, it will
      // become the default revision only when it is replicated to the default
      // workspace.
      $entity->isDefaultRevision(FALSE);

      $message1 = 'workspace plus custom operator';
      \Drupal::logger('workspace_plus_custom_operator')->notice($message1);

      // Track the workspaces in which the new revision was saved.
      $field_name = $entity_type->getRevisionMetadataKey('workspace');
      $entity->{$field_name}->target_id = $this->workspaceManager->getActiveWorkspace()->id();
    }

    // When a new published entity is inserted in a non-default workspace, we
    // actually want two revisions to be saved:
    // - An unpublished default revision in the default ('live') workspace.
    // - A published pending revision in the current workspace.
    if ($entity->isNew() && $this->isPublished($entity)) {
      // Keep track of the publishing status in a dynamic property for
      // ::entityInsert(), then unpublish the default revision.
      // @todo Remove this dynamic property once we have an API for associating
      //   temporary data with an entity: https://www.drupal.org/node/2896474.
      $entity->_initialPublished = TRUE;
      $this->setUnpublished($entity);
    }
  }

  /**
   * Helper function to allow new entities.
   */
  protected function isPublished($entity) {
    if (method_exists($entity, 'isPublished')) {
      return $entity->isPublished();
    }
    return ($entity->hasField('status')) ? $entity->status->value : TRUE;
  }

  /**
   * Helper funciton to unpublish new entity types.
   */
  protected function setUnpublished(&$entity) {
    if (method_exists($entity, 'setUnpublished')) {
      $entity->setUnpublished();
      return $entity;
    }
    else if ($entity->hasField('status')) {
      $entity->set('status', 0);
    }
    return $entity;
  }

  /**
   * Helper funciton to unpublish new entity types.
   */
  protected function setPublished(&$entity) {
    if (method_exists($entity, 'setPublished')) {
      $entity->setPublished();
      return $entity;
    }
    else if ($entity->hasField('status')) {
      $entity->set('status', 1);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function entityInsert(EntityInterface $entity) {
    /** @var \Drupal\Core\Entity\RevisionableInterface|\Drupal\Core\Entity\EntityPublishedInterface $entity */
    // Only run if the entity type can belong to a workspace and we are in a
    // non-default workspace.
    if (!$this->workspaceManager->shouldAlterOperations($entity->getEntityType())) {
      return;
    }

    if (!$this->workspaceManager->isEntityTypeSupported($entity->getEntityType())) {
      return;
    }

    $this->workspaceAssociation->trackEntity($entity, $this->workspaceManager->getActiveWorkspace());

    // When an entity is newly created in a workspace, it should be published in
    // that workspace, but not yet published on the live workspace. It is first
    // saved as unpublished for the default revision, then immediately a second
    // revision is created which is published and attached to the workspace.
    // This ensures that the published version of the entity does not 'leak'
    // into the live site. This differs from edits to existing entities where
    // there is already a valid default revision for the live workspace.
    if (isset($entity->_initialPublished)) {
      // Operate on a clone to avoid changing the entity prior to subsequent
      // hook_entity_insert() implementations.
      $pending_revision = clone $entity;
      $pending_revision->setPublished();
      $pending_revision->isDefaultRevision(FALSE);
      $pending_revision->save();
    }
  }

}
