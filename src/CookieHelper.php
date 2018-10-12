<?php

namespace Drupal\persistent_login;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cookie helper service.
 */
class CookieHelper implements CookieHelperInterface {

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  private $sessionConfiguration;

  /**
   * The Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Instantiates a new CookieHelper instance.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory service.
   */
  public function __construct(SessionConfigurationInterface $session_configuration, ConfigFactoryInterface $configFactory) {
    $this->sessionConfiguration = $session_configuration;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function getCookieName(Request $request) {
    $prefix = $this->configFactory
      ->get('persistent_login.settings')
      ->get('cookie_prefix');

    // Use different session identifiers for HTTPS and HTTP to prevent a cookie
    // collision.
    // @see \Drupal\Core\Session\SessionConfiguration::getName()
    if ($request->isSecure()) {
      $prefix = 'S' . $prefix;
    }

    $sessionConfigurationSettings = $this->sessionConfiguration->getOptions($request);
    // Replace the session cookie prefix.
    $session_name = preg_replace('/^S?SESS/', '', $sessionConfigurationSettings['name']);

    return $prefix . $session_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getCookieValue(Request $request) {
    return $request->cookies->get($this->getCookieName($request));
  }

  /**
   * {@inheritdoc}
   */
  public function hasCookie(Request $request) {
    return $request->cookies->has($this->getCookieName($request));
  }

}
