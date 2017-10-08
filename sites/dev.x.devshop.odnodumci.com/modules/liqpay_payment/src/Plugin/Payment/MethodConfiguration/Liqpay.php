<?php

namespace Drupal\liqpay_payment\Plugin\Payment\MethodConfiguration;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\payment_offsite_api\Plugin\Payment\MethodConfiguration\PaymentMethodConfigurationBaseOffsite;

/**
 * Provides the configuration for the payment_liqpay payment method plugin.
 *
 * Plugins extending this class should provide a configuration schema that
 * extends
 * plugin.plugin_configuration.payment_method_configuration.payment_liqpay.
 *
 * @PaymentMethodConfiguration(
 *   description = @Translation("A payment method type that process payments via Liqpay payment gateway."),
 *   id = "payment_liqpay",
 *   label = @Translation("Liqpay")
 * )
 */
class Liqpay extends PaymentMethodConfigurationBaseOffsite implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'message_text' => 'In addition to the order amount liqpay fee can be charged.',
      'message_text_format' => 'plain_text',
      'ipn_statuses' => [
        'success' => 'payment_success',
        'failure' => 'payment_failed',
        'reversed' => 'payment_failed',
        'sandbox' => 'payment_success',
        'otp_verify' => 'payment_pending',
        '3ds_verify' => 'payment_pending',
        'cvv_verify' => 'payment_pending',
        'sender_verify' => 'payment_pending',
        'receiver_verify' => 'payment_pending',
        'wait_secure' => 'payment_pending',
        'wait_accept' => 'payment_pending',
      ],
      'config' => [
        'version' => 3,
        'public_key' => '',
        'private_key' => '',
        'action_url' => '',
        'action' => 'pay',
        'sandbox' => 1,
        'redirect_url' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processBuildConfigurationForm(array &$element, FormStateInterface $form_state, array &$form) {
    $element['version'] = [
      '#type' => 'textfield',
      '#title' => t('API version'),
      '#default_value' => $this->getVersion(),
      '#maxlength' => 1,
      '#required' => TRUE,
    ];

    $element['public_key'] = [
      '#type' => 'textfield',
      '#title' => t('Public key'),
      '#default_value' => $this->getPublicKey(),
      '#required' => TRUE,
    ];

    $element['private_key'] = [
      '#type' => 'textfield',
      '#title' => t('Private key'),
      '#default_value' => $this->getPrivateKey(),
      '#required' => TRUE,
    ];

    $element['action'] = [
      '#type' => 'textfield',
      '#title' => t('Action'),
      '#default_value' => $this->getAction(),
      '#required' => TRUE,
    ];

    $element['action_url'] = [
      '#type' => 'textfield',
      '#title' => t('Action url'),
      '#default_value' => $this->getActionUrl(),
      '#required' => TRUE,
    ];

    $element['redirect_url'] = [
      '#type' => 'textfield',
      '#title' => t('Redirect url'),
      '#description' => t('If you have installed Token module you may use tokens in URL. Set [redirect_to_referer] if you wanna set redirection to referer page.'),
      '#default_value' => $this->getRedirect(),
      '#required' => TRUE,
    ];

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $element['token_help'] = array(
        '#type' => 'details',
        '#title' => t('Available tokens'),
      );
      $element['token_help']['tree'] = array(
        '#theme' => 'token_tree_link',
        '#token_types' => ['global'],
        '#element_validate' => 'token_element_validate',
      );
    }

    $element['sandbox'] = [
      '#type' => 'checkbox',
      '#title' => t('Sandbox'),
      '#default_value' => $this->getSandboxStatus(),
      '#required' => TRUE,
    ];

    return parent::processBuildConfigurationForm($element, $form_state, $form);
  }


  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $parents = $form['plugin_form']['#parents'];
    $values = $form_state->getValues();
    $values = NestedArray::getValue($values, $parents);

    $this->setVersion($values['version']);
    $this->setPublicKey($values['public_key']);
    $this->setPrivateKey($values['private_key']);
    $this->setAction($values['action']);
    $this->setActionUrl($values['action_url']);
    $this->setSandboxStatus($values['sandbox']);
    $this->setRedirect($values['redirect_url']);
  }

  /**
   * Returns the API version.
   *
   * @return int
   *   API version.
   */
  public function getVersion() {
    return $this->configuration['config']['version'];
  }

  /**
   * Sets the API version.
   *
   * @param string $version
   *   The API version.
   *
   * @return static
   */
  public function setVersion($version) {
    $this->configuration['config']['version'] = $version;

    return $this;
  }

  /**
   * Returns redirect value.
   *
   * @return int
   *   Redirect value.
   */
  public function getRedirect() {
    return $this->configuration['config']['redirect_url'];
  }

  /**
   * Sets redirect value.
   *
   * @param string $redirect
   *    Redirect value.
   *
   * @return static
   */
  public function setRedirect($redirect) {
    $this->configuration['config']['redirect_url'] = trim($redirect);

    return $this;
  }

  /**
   * Returns the store private key.
   *
   * @return int
   *   The store private key.
   */
  public function getPrivateKey() {
    return $this->configuration['config']['private_key'];
  }

  /**
   * Sets the store private key.
   *
   * @param string $private_key
   *   The store private key.
   *
   * @return static
   */
  public function setPrivateKey($private_key) {
    $this->configuration['config']['private_key'] = $private_key;

    return $this;
  }

  /**
   * Returns store public key.
   *
   * @return string
   *   Store public key.
   */
  public function getPublicKey() {
    return $this->configuration['config']['public_key'];
  }

  /**
   * Sets store public key.
   *
   * @param string $public_key
   *   An store public key.
   *
   * @return static
   */
  public function setPublicKey($public_key) {
    $this->configuration['config']['public_key'] = $public_key;

    return $this;
  }

  /**
   * Returns the default action.
   *
   * @return string
   *   The action.
   */
  public function getAction() {
    return $this->configuration['config']['action'];
  }

  /**
   * Sets default action.
   *
   * @param string $action
   *   The action.
   *
   * @return static
   */
  public function setAction($action) {
    $this->configuration['config']['action'] = $action;

    return $this;
  }
  /**
   * Returns the default action url.
   *WBBuZDbtiSOm5GVw44JA8ILgOODwrTmULHsLkLcD
   * @return string
   *   The action url.
   */
  public function getActionUrl() {
    return $this->configuration['config']['action_url'];
  }

  /**
   * Sets default action url.
   *
   * @param string $action_url
   *   The action url.
   *
   * @return static
   */
  public function setActionUrl($action_url) {
    $this->configuration['config']['action_url'] = $action_url;

    return $this;
  }

  /**
   * Returns sandbox status.
   *
   * @return string
   *   The sandbox status.
   */
  public function getSandboxStatus() {
    return $this->configuration['config']['sandbox'];
  }

  /**
   * Sets sandbox status.
   *
   * @param string $sandbox_status
   *   The sandbox status.
   *
   * @return static
   */
  public function setSandboxStatus($sandbox_status) {
    $this->configuration['config']['sandbox'] = $sandbox_status;

    return $this;
  }

}
