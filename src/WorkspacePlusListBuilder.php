<?php

namespace Drupal\workspace_plus;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\workspaces\WorkspaceListBuilder;

/**
 * Defines a class to build a listing of workspace entities.
 * Overrides the core workspaces list builder to add in access handling.
 *
 * @see \Drupal\workspaces\Entity\Workspace
 */

 class WorkspacePlusListBuilder extends WorkspaceListBuilder {

   /**
    * {@inheritdoc}
    */
   public function load() {
     // Get all the workspace entities and sort them in tree order.
     $workspace_tree = $this->workspaceRepository->loadTree();
     $entities = array_replace($workspace_tree, $this->storage->loadMultiple());
     foreach ($entities as $id => $entity) {
       if (!$entity->access('view')) {
         unset($entities[$id]);
         continue;
       }
       $entity->_depth = $workspace_tree[$id]['depth'];
     }

     return $entities;
   }

 }
