<?php

namespace Drupal\workspace_plus\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\workspace_plus\WorkspaceOperationFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\workspaces\Form\WorkspaceDeployForm as BaseDeployForm;

/**
 * Create deployment form.
 */
class WorkspaceDeployForm extends BaseDeployForm {

  /**
   * Constructs a new WorkspaceDeployForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\workspace_plus\WorkspaceOperationFactory $workspace_operation_factory
   *   The workspace operation factory service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, MessengerInterface $messenger, WorkspaceOperationFactory $workspace_operation_factory) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time, $messenger, $workspace_operation_factory);
    $this->messenger = $messenger;
    $this->workspaceOperationFactory = $workspace_operation_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('workspace_plus.operation_factory')
    );
  }

}
