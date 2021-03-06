<?php

namespace Drupal\ajax_assets_plus;

use Drupal\ajax_assets_plus\Ajax\AjaxAssetsPlusResponse;
use Drupal\Core\Ajax\AddCssCommand;
use Drupal\Core\Ajax\AjaxResponseAttachmentsProcessor;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Asset\AttachedAssets;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Asset\LibraryDiscoveryInterface;

/**
 * Processes attachments of AJAX responses.
 *
 * Adds all the setting commands and libraries necessary for attachments.
 *
 * @see \Drupal\Core\Ajax\AjaxResponseAttachmentsProcessor
 * @see \Drupal\ajax_assets_plus\Ajax\AjaxAssetsPlusResponse
 * @see \Drupal\Core\Render\MainContent\AjaxRenderer
 */
class AjaxAssetsPlusAjaxResponseAttachmentsProcessor extends AjaxResponseAttachmentsProcessor {

  /**
   * The asset resolver service.
   *
   * @var \Drupal\ajax_assets_plus\AjaxAssetsPlusAssetResolver
   */
  protected $assetResolver;

  /**
   * All the css, js assets grouped by libraries.
   *
   * @var mixed[]
   *
   * @todo Cache this. @see \Drupal\Core\Asset\AssetResolver::getCssAssets().
   */
  protected $libraries = [];

  /**
   * The array ajax commands.
   *
   * @var \Drupal\Core\Ajax\CommandInterface[]
   */
  protected $commands;

  /**
   * The library discovery service.
   *
   * @var LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * Gets libraries.
   *
   * @return mixed[]
   *   Libraries array.
   */
  public function getLibraries() {
    return $this->libraries;
  }

  /**
   * Gets array ajax commands.
   *
   * @return \Drupal\Core\Ajax\CommandInterface[]
   *   The array ajax commands.
   */
  public function getCommands() {
    return $this->commands;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    AssetResolverInterface $asset_resolver,
    ConfigFactoryInterface $config_factory,
    AssetCollectionRendererInterface $css_collection_renderer,
    AssetCollectionRendererInterface $js_collection_renderer,
    RequestStack $request_stack,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    LibraryDiscoveryInterface $library_discovery
  ) {
    parent::__construct($asset_resolver, $config_factory, $css_collection_renderer, $js_collection_renderer, $request_stack, $renderer, $module_handler);
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processAttachments(AttachmentsInterface $response) {
    if (!$response instanceof AjaxAssetsPlusResponse) {
      return parent::processAttachments($response);
    }

    $request = $this->requestStack->getCurrentRequest();
    $this->buildAttachments($response, $request);

    $libraries = $this->getLibraries();
    $response->setLibraries($libraries);

    $response->setData([
      'commands' => $this->getCommands(),
      'libraries' => $libraries,
      'content' => $response->getContent(),
    ]);

    return $response;
  }

  /**
   * Prepares the AJAX commands and libraries to attach assets.
   *
   * @param \Drupal\ajax_assets_plus\Ajax\AjaxAssetsPlusResponse $response
   *   The AJAX response to update.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that the AJAX is responding to.
   */
  protected function buildAttachments(AjaxAssetsPlusResponse $response, Request $request) {
    // @todo Aggregate CSS/JS if necessary, during normal site operation.
    //   We should optimize every file separately, else it will be not possible
    //   to distinguish between them.
    //   $optimize_css = !defined('MAINTENANCE_MODE') &&
    //   $this->config->get('css.preprocess');
    //   $optimize_js = !defined('MAINTENANCE_MODE') &&
    //   $this->config->get('js.preprocess');
    $optimize_css = $optimize_js = FALSE;

    $attachments = $response->getAttachments();

    // Resolve the attached libraries into asset collections.
    $assets = new AttachedAssets();
    // We should not set already loaded libraries here from the ajax page state,
    // as different clients might have different libraries loaded.
    $assets->setLibraries(isset($attachments['library']) ? $attachments['library'] : [])
      ->setSettings(isset($attachments['drupalSettings']) ? $attachments['drupalSettings'] : []);
    $css_assets = $this->assetResolver->getCssAssets($assets, $optimize_css);
    list($js_assets_header, $js_assets_footer) = $this->assetResolver->getJsAssets($assets, $optimize_js);

    $attachments['drupalSettings'] = $assets->getSettings();

    // Render the HTML to load these files, and add AJAX commands to insert this
    // HTML in the page. Settings are handled separately, afterwards.
    $settings = [];
    if (isset($js_assets_header['drupalSettings'])) {
      $settings = $js_assets_header['drupalSettings']['data'];
      unset($js_assets_header['drupalSettings']);
    }
    if (isset($js_assets_footer['drupalSettings'])) {
      $settings = $js_assets_footer['drupalSettings']['data'];
      unset($js_assets_footer['drupalSettings']);
    }

    $libraries = $this->assetResolver->getAllLibrariesToLoad($assets);
    $this->groupAssetsByLibraries($libraries, $css_assets, 'css');
    $this->groupAssetsByLibraries($libraries, $js_assets_header, 'js');
    $this->groupAssetsByLibraries($libraries, $js_assets_footer, 'js');

    // Prepend a command to merge changes and additions to drupalSettings.
    if (!empty($settings)) {
      // During Ajax requests basic path-specific settings are excluded from
      // new drupalSettings values. The original page where this request comes
      // from already has the right values. An Ajax request would update them
      // with values for the Ajax request and incorrectly override the page's
      // values.
      // @see system_js_settings_alter()
      unset($settings['path']);

      // Ajax page state is updated at the frontend side, as page state might be
      // different for every client.
      unset($settings['ajaxPageState']);

      $response->addCommand(new SettingsCommand($settings, TRUE), TRUE);
    }

    $commands = $response->getCommands();
    $this->moduleHandler->alter('ajax_render', $commands);

    $this->commands = $commands;
  }

  /**
   * Groups all the css, js assets by libraries.
   *
   * @param array $libraries
   *   Libraries array.
   * @param array $assets
   *   Assets array.
   * @param string $type
   *   Assets type(css or js).
   */
  protected function groupAssetsByLibraries(array $libraries, array $assets, $type) {
    foreach ($libraries as $library) {
      list($extension, $name) = explode('/', $library, 2);
      $definition = $this->getLibraryDiscovery()->getLibraryByName($extension, $name);

      if (empty($definition[$type])) {
        continue;
      }

      foreach ($definition[$type] as $options) {
        if (!empty($options['data']) && !empty($assets[$options['data']])) {
          $asset = $assets[$options['data']];

          // Create commands to add the assets.
          if ($type == 'css') {
            $css_render_array = $this->cssCollectionRenderer->render([$asset]);
            $command = new AddCssCommand($this->renderer->renderPlain($css_render_array));
          }
          elseif ($type == 'js') {
            $js_render_array = $this->jsCollectionRenderer->render([$asset]);
            if ($asset['scope'] == 'header') {
              $command = new PrependCommand('head', $this->renderer->renderPlain($js_render_array));
            }
            elseif ($asset['scope'] == 'footer') {
              $command = new AppendCommand('body', $this->renderer->renderPlain($js_render_array));
            }
          }

          if (!empty($command)) {
            $this->libraries[$library][$asset['data']] = $command->render();
          }
        }
      }
    }
  }

  /**
   * Gets library discovery service.
   *
   * @return \Drupal\Core\Asset\LibraryDiscoveryInterface
   *   The library discovery service.
   */
  public function getLibraryDiscovery() {
    return $this->libraryDiscovery;
  }

}
