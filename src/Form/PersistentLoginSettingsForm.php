<?php
namespace Drupal\persistent_login\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for editing Persistent Login module settings.
 */
class PersistentLoginSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'persistent_login_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'persistent_login.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('persistent_login.settings');

    $form['lifetime'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Lifetime'),
      '#description' => $this->t('The maximum number of days for which a persistent login session is valid.  Enter <em>0</em> for no expiration.'),
      '#default_value' => $config->get('lifetime'),
    ];

    $form['max_tokens'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Maximum Tokens'),
      '#description' => $this->t('The maximum number of tokens per user.  Enter <em>0</em> for no limit.'),
      '#default_value' => $config->get('max_tokens'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('persistent_login.settings')
      ->set('lifetime', (int) $form_state->getValue('lifetime'))
      ->set('max_tokens', (int) $form_state->getValue('max_tokens'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
