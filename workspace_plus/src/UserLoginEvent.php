<?php

namespace Drupal\workspace_plus;

use Drupal\user\UserInterface;

/**
 * Event that is fired when a user logs in.
 */
class UserLoginEvent {

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The workspace entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $workspaceStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user account.
   *
   * @var \Drupal\user\UserInterface
   */
  public $account;

  /**
   * Constructs the object.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account of the user logged in.
   */
  public function __construct(UserInterface $account) {
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->workspaceManager = \Drupal::service('workspaces.manager');
    $this->workspaceStorage = $this->entityTypeManager->getStorage('workspace');
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function setStageWorkspace() {
    $id = 'stage';
    /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
    $workspace = $this->workspaceStorage->load($id);

    try {
      $this->workspaceManager->setActiveWorkspace($workspace);
      \Drupal::messenger()->addMessage('%workspace_label is now the active workspace.', ['%workspace_label' => $workspace->label()]);
    }
    catch (WorkspaceAccessException $e) {
      \Drupal::messenger()->addError('You do not have access to activate the %workspace_label workspace.', ['%workspace_label' => $workspace->label()]);
    }
  }

}
