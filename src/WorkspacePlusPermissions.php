<?php

/**
 * Implments dynamic per-workspace permissions for use with workspace module.
 */

namespace Drupal\workspace_plus;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WorkspacePlusPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
   public function __construct(EntityTypeManagerInterface $entityTypeManager) {
     $this->entityTypeManager = $entityTypeManager;
   }

   /**
    * {@inheritdoc}
    */
   public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
   }


   /**
    * Creates list of view and edit permissions for available workspaces.
    */
    public function permissions() {
      $workspaces = $this->entityTypeManager->getStorage('workspace')->loadmultiple();
      $permissions = [];
      foreach($workspaces as $id => $workspace) {
        $permissions["{$id} view"] = [
          'title' => $this->t('view content in %workspace', ['%workspace' => $workspace->label()])
        ];

        $permissions["{$id} edit"] = [
          'title' => $this->t('edit content in %workspace', ['%workspace' => $workspace->label()])
        ];
      }
      return $permissions;
    }

 }
