<?php

namespace Drupal\workspace_plus\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure workspace_plus settings for this site.
 */
class WorkspacePlusSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'workspace_plus.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_plus_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $user_roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    foreach ($user_roles as $key => $value) {
      $roles[$value->id()] = $value->label();
    }

    $form['live_workspace_access'] = [
      '#type' => 'checkboxes',
      '#title' => 'Select Roles',
      '#options' => $roles,
      '#default_value' => (array) $config->get('live_workspace_access'),
      '#required' => TRUE,
      '#multiple' => TRUE,
      '#description' => 'Selected roles will be able to access Live Workspace.',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      // Set the submitted configuration setting.
      ->set('live_workspace_access', $form_state->getValue('live_workspace_access'))
      ->save();

    $users_noacess = $form_state->getValue('live_workspace_access');
    foreach ($users_noacess as $key => $value) {
      if ($value == '0') {
        $users_logout[] = $key;
      }
    }

    if (!empty($users_logout)) {
      // All the users except admin role are logged out.
      $query = \Drupal::entityQuery('user')
        ->condition('status', 1)
        ->condition('roles', $users_logout, 'IN');
      $uids = $query->execute();
      $user_storage = \Drupal::service('entity_type.manager')->getStorage('user');
      $result = $user_storage->loadMultiple($uids);
      foreach ($result as $user) {
        \Drupal::currentUser()->setAccount($user);
        if (\Drupal::currentUser()->isAuthenticated()) {
          $session_manager = \Drupal::service('session_manager');
          $session_manager->delete(\Drupal::currentUser()->id());
        }
      }
    }
    parent::submitForm($form, $form_state);

    // Rebuild users.
    drupal_flush_all_caches();
  }

}
