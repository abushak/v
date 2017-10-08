<?php

/**
 * @file
 * Contains \Drupal\ajax_form_entity\Form\ExampleConfigForm.
 */

namespace Drupal\ajax_form_entity\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class SettingsForm extends ConfigFormBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entity_manager;

  /**
   * Constructs a \Drupal\ajax_form_entity\Form\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityManagerInterface $entity_manager) {
    parent::__construct($config_factory);
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.manager')
    );
  }

  /*
  **
  * Returns a unique string identifying the form.
  *
  * @return string
  *   The unique string identifying the form.
  */
  public function getFormId() {
    return 'id_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */

  public function buildForm(array $form, FormStateInterface $form_state) {
    $definitions = $this->entityManager->getDefinitions();
    $all_bundle = $this->entityManager->getAllBundleInfo();

    // @todo : option to define form modes and view modes.
    $form_modes = $definitions['entity_form_mode'];
    $view_modes = $definitions['entity_view_mode'];

    // Get all display types for the entity.
    //$bundles=$entity_config->getBundleEntityType();
    //$bundles=$entity_config->bundle();
    //   dsm($bundle);


    // $tab_entity_labels = $entity_manager->getEntityTypeLabels();
    $config = $this->config('ajax_form_entity.settings')->get();

    // Content entities to be excluded.
    $excluded_entity_types = array(
      1 => 'shortcut',
      2 => 'menu_link_content',
      3 => 'file',
    );

    foreach ($all_bundle as $entity_name => $bundle) {

      // Exclude content entities which are not supported.
      if (!isset($definitions[$entity_name]) || array_search($entity_name, $excluded_entity_types)) {
        continue;
      }

      $group = $definitions[$entity_name]->get('group');

      // Do not work with configuration entities for now.
      // @todo : see what can be done to improve the backoffice.
      if ($group == 'configuration') {
        continue;
      }

      if (!isset($form[$group])) {
        $form[$group] = array(
          '#type' => 'container',
          '#tree' => TRUE,
          '#title' => $group,
        );
      }

      $form [$group][$entity_name] = array(
        '#type' => 'details',
        '#title' => $definitions[$entity_name]->getLabel()
      );

      // Define all configuration per bundle.
      if (is_array($bundle)) {
        foreach ($bundle as $bundle_name => $label) {
          if (isset($label['label'])) {
            $form[$group][$entity_name] [$bundle_name] = array(
              '#type' => 'details',
              '#group' => $entity_name,
              '#open' => TRUE,
              '#title' => $label['label'],
            );
            if (isset($config[$group][$entity_name][$bundle_name])) {
              $default_values = $config[$group][$entity_name][$bundle_name];
            }
            $form[$group][$entity_name] [$bundle_name]['activate'] = array(
              '#type' => 'checkbox',
              '#title' => $this->t('Activate Ajax Entity form'),
              '#default_value' => isset($default_values['activate']) ? $default_values['activate'] : '',
            );
            $form[$group][$entity_name][$bundle_name]['popin'] = array(
              '#type' => 'checkbox',
              '#title' => $this->t('Popin mode'),
              '#default_value' => isset($default_values['popin']) ? $default_values['popin'] : '',
              '#states' => array(
                'visible' => array(
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][activate]"]' => array(
                    'checked' => TRUE,
                  )
                ),
              ),
            );
            $form[$group][$entity_name] [$bundle_name]['reload'] = array(
              '#type' => 'checkbox',
              '#title' => $this->t('Reload the form on creation'),
              '#default_value' => isset($default_values['reload']) ? $default_values['reload'] : '',
              '#states' => array(
                'visible' => array(
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][activate]"]' => array(
                    'checked' => TRUE,
                  )
                ),
              ),
            );
            $form[$group][$entity_name] [$bundle_name]['send_content'] = array(
              '#type' => 'checkbox',
              '#title' => $this->t('Show result on creation'),
              '#default_value' => isset($default_values['send_content']) ? $default_values['send_content'] : TRUE,
              '#states' => array(
                'visible' => array(
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][activate]"]' => array(
                    'checked' => TRUE,
                  )
                ),
              ),
            );
            $selector_type_options = array(
              'prepend' => $this->t('Before'),
              'append' => $this->t('After'),
            );
            $form[$group][$entity_name] [$bundle_name]['selector_type'] = array(
              '#type' => 'select',
              '#options' => $selector_type_options,
              '#title' => $this->t('Creation view mode'),
              '#description' => $this->t('Area to send the content. If custom'),
              '#default_value' => isset($default_values['selector_type']) ? $default_values['selector_type'] : '#prepend',
              '#states' => array(
                'visible' => array(
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][activate]"]' => array(
                    'checked' => TRUE,
                  ),
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][send_content]"]' => array(
                    'checked' => TRUE,
                  )
                ),
              ),
            );
            $form[$group][$entity_name] [$bundle_name]['selector_content'] = array(
              '#type' => 'textfield',
              '#title' => $this->t('Class or ID where to send the content'),
              '#default_value' => isset($default_values['selector_content']) ? $default_values['selector_content'] : '',
              '#description' => $this->t('Let empty to send before / after the creation form.'),
              '#weight' => 1,
              '#states' => array(
                'visible' => array(
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][activate]"]' => array(
                    'checked' => TRUE,
                  ),
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][send_content]"]' => array(
                    'checked' => TRUE,
                  ),
                ),
              ),
            );
            $form[$group][$entity_name] [$bundle_name]['edit_link'] = array(
              '#type' => 'textfield',
              '#title' => $this->t('Edit link label'),
              '#description' => $this->t('Provide an AJAX edit link in any display mode. Let blank for no link.'),
              '#default_value' => isset($default_values['edit_link']) ? $default_values['edit_link'] : $this->t('Edit'),
              '#weight' => 1,
              '#states' => array(
                'visible' => array(
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][activate]"]' => array(
                    'checked' => TRUE,
                  ),
                ),
              ),
            );

            $form[$group][$entity_name] [$bundle_name]['form'] = array(
              '#type' => 'checkbox',
              '#title' => $this->t('Add a field with edit form'),
              '#description' => $this->t('EXPERIMENTAL - Provide a field in view mode with ajax edit form.'),
              '#default_value' => isset($default_values['form']) ? $default_values['form'] : 0,
              '#weight' => 1,
              '#states' => array(
                'visible' => array(
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][activate]"]' => array(
                    'checked' => TRUE,
                  ),
                ),
              ),
            );
            $form[$group][$entity_name] [$bundle_name]['show_message'] = array(
              '#type' => 'checkbox',
              '#title' => $this->t('Show the message'),
              '#default_value' => isset($default_values['show_message']) ? $default_values['show_message'] : 1,
              '#weight' => 1,
              '#states' => array(
                'visible' => array(
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][activate]"]' => array(
                    'checked' => TRUE,
                  ),
                ),
              ),
            );

            /*
             * // @todo : activate delete link.
            $form[$group][$entity_name] [$bundle_name]['delete_link'] = array(
              '#type' => 'textfield',
              '#title' => $this->t('Delete link label'),
              '#description' => $this->t('Provide an AJAX delete link in any display mode. Let blank for no link.'),
              '#default_value' => isset($default_values['delete_link']) ? $default_values['delete_link'] : $this->t('Delete'),
              '#weight' => 1,
              '#states' => array(
                'visible' => array(
                  'input[name="' . $group . '[' . $entity_name . '][' . $bundle_name. '][activate]"]' => array(
                    'checked' => TRUE,
                  ),
                ),
              ),
            );

             */


            /*
             * @todo : view mode and form mode to be selected.
            $form[$group][$entity_name] [$bundle_name]['view_mode'] = array(
              '#type' => 'select',
              '#options' => array(),
              '#title' => $this->t('Creation view mode'),
              '#description' => $this->('The view mode used after creation'),
              '#default_value' => isset($default_values['view_mode']) ? $default_values['view_mode'] : '',
            );
             $form[$group][$entity_name] [$bundle_name]['form_mode'] = array(
              '#type' => 'select',
              '#options' => array(),
              '#title' => $this->t('Default form mode'),
              '#description' => $this->('The form mode used for AJAX edition'),
              '#default_value' => isset($default_values['form_mode']) ? $default_values['form_mode'] : '',
            );


            */
          }

        }
      }
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    );
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ajax_form_entity.settings');
    $config->set('content', $form_state->getValue('content'));
    $config->save();
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['ajax_form_entity.settings'];
  }

}
