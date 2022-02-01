<?php

namespace Drupal\daxko_sso\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings Form for Daxko SSO setup.
 */
class DaxkoSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'daxko_sso_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'daxko_sso.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('daxko_sso.settings');

    $form_state->setCached(FALSE);

    /* @see \Drupal\daxko\DaxkoClientFactory::get */

    $form['base_uri'] = [
      '#type' => 'url',
      '#title' => $this->t('Daxko API Base URI v3'),
      '#default_value' => $config->get('base_uri'),
      '#description' => t('Add your Daxko API base uri here. It is most likely https://api.daxko.com/v3/.'),
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Id'),
      '#default_value' => $config->get('client_id'),
      '#description' => t('Your Daxko client id. Like 4032.'),
    ];

    $form['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Daxko account name'),
      '#default_value' => $config->get('user'),
      '#description' => t('Add your Daxko API user name here.'),
    ];

    $form['pass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Daxko password'),
      '#default_value' => $config->get('pass'),
      '#description' => t('Add your Daxko API password.'),
    ];

    $form['referesh_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Daxko refresh token'),
      '#default_value' => $config->get('referesh_token'),
      '#description' => t('Refresh token is a large string like 241 chars long.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* @var $config \Drupal\Core\Config\Config */
    $config = $this->configFactory->getEditable('daxko_sso.settings');

    $config->set('referesh_token', trim($form_state->getValue('referesh_token')))->save();

    $config->set('client_id', trim($form_state->getValue('client_id')))->save();

    if ($base_uri = $form_state->getValue('base_uri')) {
      if (preg_match("#https?://#", $base_uri) === 0) {
        $base_uri = 'https://' . $base_uri;
      }
      $config->set('base_uri', rtrim($base_uri, '/') . '/')->save();
    }

    $config->set('user', trim($form_state->getValue('user')))->save();

    if (empty($pass = $form_state->getValue('pass'))) {
      $pass = $config->get('pass');
    }
    $config->set('pass', trim($pass))->save();


    parent::submitForm($form, $form_state);
  }

}
