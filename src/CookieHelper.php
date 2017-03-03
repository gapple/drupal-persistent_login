<?php

namespace Drupal\persistent_login;

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
  protected $sessionConfiguration;

  /**
   * Instantiates a new CookieHelper instance.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   */
  public function __construct(SessionConfigurationInterface $session_configuration) {
    $this->sessionConfiguration = $session_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getCookieName(Request $request) {
    $sessionConfigurationSettings = $this->sessionConfiguration->getOptions($request);
    return 'PL' . substr($sessionConfigurationSettings['name'], 4);
  }

  /**
   * {@inheritdoc}
   */
  public function hasCookie(Request $request) {
    return $request->cookies->has($this->getCookieName($request));
  }

}
