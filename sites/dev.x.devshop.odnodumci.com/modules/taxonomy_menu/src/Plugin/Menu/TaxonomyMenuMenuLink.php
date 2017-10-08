<?php

/**
 * @file
 * Contains \Drupal\taxonomy_menu\Plugin\Menu\TaxonomyMenuMenuLink.
 */

namespace Drupal\taxonomy_menu\Plugin\Menu;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines menu links provided by taxonomy menu.
 *
 * @see \Drupal\taxonony_menu\Plugin\Derivative\TaxonomyMenuMenuLink
 */
class TaxonomyMenuMenuLink extends MenuLinkBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $overrideAllowed = array(
    //'menu_name' => 1,
    //'parent' => 1,
    'weight' => 1,
    'expanded' => 1,
    'enabled' => 1,
    //'title' => 1,
    //'description' => 1,
    //'metadata' => 1,
  );

  /**
   * The taxonomy term storage.
   *
   * @var TermStorageInterface
   */
  protected $termStorage;

  /**
   * The entity repository.
   *
   * @var EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The static menu link service used to store updates to weight/parent etc.
   *
   * @var \Drupal\Core\Menu\StaticMenuLinkOverridesInterface
   */
  protected $staticOverride;

  /**
   * Constructs a new TaxonomyMenuMenuLink.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param TermStorageInterface $term_storage
   *   The taxonomy term storage
   * @param EntityRepositoryInterface $entity_repository
   *   The entity repository
   * @param StaticMenuLinkOverridesInterface $static_override
   *   The link overrides
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TermStorageInterface $term_storage,
    EntityRepositoryInterface $entity_repository,
    StaticMenuLinkOverridesInterface $static_override
  ) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->termStorage = $term_storage;
    $this->entityRepository = $entity_repository;
    $this->staticOverride = $static_override;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('entity.repository'),
      $container->get('menu_link.static.overrides')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    /** @var $term \Drupal\taxonomy\Entity\Term */
    $term = $this->termStorage
      ->load($this->pluginDefinition['metadata']['taxonomy_term_id']);
    if (!empty($term)) {
      $term = $this->entityRepository->getTranslationFromContext($term);
      return $term->label();
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    /** @var $link \Drupal\taxonomy\Entity\Term */
    $term = $this->termStorage
      ->load($this->pluginDefinition['metadata']['taxonomy_term_id']);
    if (!empty($term)) {
      $term = $this->entityRepository->getTranslationFromContext($term);
      return $term->getDescription();
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist) {
    $overrides = array_intersect_key($new_definition_values, $this->overrideAllowed);
    // Update the definition.
    $this->pluginDefinition = $overrides + $this->pluginDefinition;
    if ($persist) {
      // TODO - consider any "persistence" back to TaxonomyMenu and/or Taxonomy upon menu link update.
      // Always save the menu name as an override to avoid defaulting to tools.
      $overrides['menu_name'] = $this->pluginDefinition['menu_name'];
      $this->staticOverride->saveOverride($this->getPluginId(), $this->pluginDefinition);
    }
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isDeletable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLink() {
  }
}
