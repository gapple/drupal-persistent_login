<?php

namespace Drupal\persistent_login\PageCache\RequestPolicy;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\persistent_login\CookieHelperInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A policy preventing caching of pages with pending persistent login cookies.
 */
class PendingPersistentLogin implements RequestPolicyInterface {

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * The cookie helper service.
   *
   * @var \Drupal\persistent_login\CookieHelperInterface
   */
  protected $cookieHelper;

  /**
   * Instantiates a new PendingPersistentLogin object.
   *
   * @param \Drupal\persistent_login\CookieHelperInterface $cookie_helper
   *   The cookie helper service.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   */
  public function __construct(CookieHelperInterface $cookie_helper, SessionConfigurationInterface $session_configuration) {
    $this->cookieHelper = $cookie_helper;
    $this->sessionConfiguration = $session_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    // Prevent the serving of cached pages for anonymous users if a persistent
    // login cookie is present.
    if (!$this->sessionConfiguration->hasSession($request) && $this->cookieHelper->hasCookie($request)) {
      return static::DENY;
    }

    return NULL;
  }

}
