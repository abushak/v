<?php

namespace Drupal\liqpay_payment\Plugin\Payment\Method;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Serialization\Json;
use Drupal\payment_offsite_api\Plugin\Payment\Method\PaymentMethodBaseOffsite;
use Drupal\payment_offsite_api\Plugin\Payment\Method\PaymentMethodOffsiteInterface;

/**
 * A Liqpay payment method.
 *
 * @PaymentMethod(
 *   id = "payment_liqpay",
 *   deriver = "\Drupal\payment_offsite_api\Plugin\Payment\Method\PaymentMethodBaseOffsiteDeriver",
 *   operations_provider = "\Drupal\payment_offsite_api\Plugin\Payment\Method\PaymentMethodBaseOffsiteOperationsProvider",
 * )
 */
class Liqpay extends PaymentMethodBaseOffsite implements PaymentMethodOffsiteInterface {

  /**
   * {@inheritdoc}
   */
  protected function getSupportedCurrencies() {
    return TRUE;
  }

  /**
   * Returns redirect value.
   *
   * @return int
   *   Redirect value.
   */
  public function getRedirect() {
    return $this->pluginDefinition['config']['redirect_url'];
  }

  /**
   * Returns API version.
   *
   * @return int
   *   The version.
   */
  public function getVersion() {
    return $this->pluginDefinition['config']['version'];
  }

  /**
   * Returns store public key.
   *
   * @return string
   *   The public key.
   */
  public function getPublicKey() {
    return $this->pluginDefinition['config']['public_key'];
  }

  /**
   * Returns sandbox status.
   *
   * @return string
   *   The sandbox status.
   */
  public function getSandboxStatus() {
    return $this->pluginDefinition['config']['sandbox'];
  }

  /**
   * Returns action.
   *
   * @return string
   *   The action.
   */
  public function getAction() {
    return $this->pluginDefinition['config']['action'];
  }

  /**
   * Returns transition id.
   *
   * @return string
   *   The transition id.
   */
  public function getTransactionIdName() {
    return $this->getPayment()->id();
  }

  /**
   * Returns action.
   *
   * @return string
   *   The action.
   */
  public function getActionUrl() {
    return $this->pluginDefinition['config']['action_url'];
  }

  /**
   * Returns private key.
   *
   * @return string
   *   The action.
   */
  public function getPrivateKey() {
    return $this->pluginDefinition['config']['private_key'];
  }


  /**
   * {@inheritdoc}
   */
  public function paymentForm() {
    $form = [];
    $payment = $this->getPayment();
    $form['#action'] = $this->getActionUrl();
    $data = [
      'version' => $this->getVersion(),
      'public_key' => $this->getPublicKey(),
      'sandbox' => $this->getSandboxStatus(),
      'action' => $this->getAction(),
      'order_id' => $this->getTransactionIdName(),
      'language' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
      'amount' => round($payment->getAmount(), 1),
      'currency' => $payment->getCurrency()->currencyCode,
      'description' => $this->t('Order ID: @order_id, User mail: @mail', [
        '@order_id' => $payment->id(),
        '@mail' => $payment->getOwner()->getEmail(),
      ]),
    ];
    $redirect_url = $this->getRedirect();
    if ($redirect_url) {
      if ($redirect_url == '[redirect_to_referer]') {
       $data['result_url'] = $_SERVER['HTTP_REFERER'];
      }
      else {
        $data['result_url'] = \Drupal::token()->replace($redirect_url, ['global']);
      }
    }
    $private_key = $this->getPrivateKey();
    $json_data = base64_encode(json_encode($data));
    $this->addPaymentFormData('data', $json_data);
    $this->addPaymentFormData('signature', base64_encode(sha1($private_key . $json_data . $private_key, 1)));
    $form += $this->generateForm();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusId($status_id) {
    return $this->configuration['ipn_statuses'][$status_id];
  }

  /**
   * {@inheritdoc}
   */
  public function ipnExecute() {
    $this->logger->error('start_exec');
    $ipn_result = [
      'status' => 'fail',
      'message' => '',
      'response_code' => 200,
    ];

    if (!$this->ipnValidate()) {
      return $ipn_result;
    }
    $response_data_base_64 = $this->request->request->get('data');
    $response_data_json = base64_decode($response_data_base_64);
    $response_data = Json::decode($response_data_json);
    $status = $response_data['status'];
    $payment_status = $this->getStatusId($status);
    $status = isset($payment_status) ? $payment_status : 'payment_pending';
    $this->getPayment()
      ->setPaymentStatus($this->paymentStatusManager->createInstance($status));
    $this->getPayment()->save();

    if (!$this->isFallbackMode()) {
      return [
        'status' => 'success',
        'message' => '',
        'response_code' => 200,
      ];
    }

    return $ipn_result;

  }

  /**
   * {@inheritdoc}
   */
  public function getResultPages() {
    return [
      'pending' => FALSE,
    ];
  }

  /**
   * Returns pending message.
   *
   * @return array
   *   The renderable array.
   */
  public function getPendingContent() {
    $response_data_base_64 = $this->request->request->get('data');
    $status = 'pending';
    if ($response_data_base_64) {
      $response_data_json = base64_decode($response_data_base_64);
      $response_data = Json::decode($response_data_json);
      $status = $response_data['status'];
    }
    return [
      '#markup' => $this->t('Payment as @external_status.', ['@external_status' => $status]),
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function isConfigured() {
    return !empty($this->getActionUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function ipnValidate() {
    if (!$this->validateEmpty()) {
      return FALSE;
    }
    $response_data_base_64 = $this->request->request->get('data');
    $response_signature = $this->request->request->get('signature');
    $private_key = $this->getPrivateKey();
    $signature = base64_encode(sha1($private_key . $response_data_base_64 . $private_key, 1));
    if (Unicode::strtoupper($response_signature) != Unicode::strtoupper($signature)) {
      if ($this->isVerbose()) {
        $this->logger->error('Missing Signature. POST data: <pre>@data</pre>',
          ['@data' => print_r($this->request->request, TRUE)]
        );
      }
      return FALSE;
    }
    $response_data_json = base64_decode($response_data_base_64);
    $response_data = Json::decode($response_data_json);
    if (!($this->validateTransactionId($response_data['order_id']) && $this->validateAmount($response_data['amount']) && $this->validateCurrency($response_data['currency']))) {
      return FALSE;
    };
    return TRUE;
  }

  /**
   * Currency default validator.
   *
   * @return bool
   *   TRUE on successful validation FALSE otherwise.
   */
  protected function validateCurrency($request_currency) {
    if ($this->getPayment()->getCurrency()->currencyCode != $request_currency) {
      if ($this->isVerbose()) {
        $this->logger->error('Missing transaction id currency. POST data: <pre>@data</pre>',
          ['@data' => print_r(\Drupal::request()->request, TRUE)]
        );
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Transaction ID default validator.
   *
   * @return bool
   *   TRUE on successful validation FALSE otherwise.
   */
  protected function validateTransactionId($request_payment_id) {
    $payment = \Drupal::entityTypeManager()
      ->getStorage('payment')
      ->load($request_payment_id);
    if (!$payment) {
      if ($this->isVerbose()) {
        $this->logger->error('Missing transaction id. POST data: <pre>@data</pre>',
          ['@data' => print_r($this->request->request, TRUE)]
        );
      }
      return FALSE;
    }
    $this->setPayment($payment);
    return TRUE;
  }

  /**
   * Amount default validator.
   *
   * @return bool
   *   TRUE on successful validation FALSE otherwise.
   */
  protected function validateAmount($request_amount) {
    if ($this->getPayment()->getAmount() != $request_amount) {
      if ($this->isVerbose()) {
        $this->logger->error('Missing transaction id amount. POST data: <pre>@data</pre>',
          ['@data' => print_r(\Drupal::request()->request, TRUE)]
        );
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Empty default validator.
   *
   * @return bool
   *   TRUE on successful validation FALSE otherwise.
   */
  protected function validateEmpty() {
    // Exit now if the $_POST was empty.
    if (empty($this->request->request)) {
      if ($this->isVerbose()) {
        $this->logger->error('Interaction URL accessed with no POST data submitted.',
          []
        );
      }
      return FALSE;
    }
    return TRUE;

  }

}
