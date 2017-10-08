<?php

namespace Drupal\ajax_form_entity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Entity\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AjaxFormController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityFormBuilder definition.
   *
   * @var Drupal\Core\Entity\EntityFormBuilder
   * @var Drupal\Core\Entity\EntityManager
   */
  protected $entity_form_builder;
  protected $entity_manager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityFormBuilder $entity_form_builder, EntityManager $entity_manager) {
    $this->entity_form_builder = $entity_form_builder;
    $this->entity_manager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('entity.manager')
    );
  }


  /**
   * Sends back a form entity to edit any content entity.
   */
  public function ajaxForm($entity_type, $id, $popin, $view_mode) {
    // Get the entity and generate the form.
    $entity = $this->entity_manager->getStorage($entity_type)->load($id);
    $form = $this->entity_form_builder->getForm($entity);

    // If popin, return directly, else return an AJAX callback.
    if ($popin) {
      return $form;
    }
    else {
      $response = new AjaxResponse();
      $selector = '.ajax-form-entity-view-' . $entity_type . '-' . $id;
      $response->addCommand(new ReplaceCommand($selector, $form));
      return $response;
    }
  }
}
