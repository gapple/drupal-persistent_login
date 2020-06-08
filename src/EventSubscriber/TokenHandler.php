<?php

namespace Drupal\persistent_login\EventSubscriber;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\persistent_login\CookieHelperInterface;
use Drupal\persistent_login\PersistentToken;
use Drupal\persistent_login\TokenException;
use Drupal\persistent_login\TokenManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class TokenHandler.
 *
 * @package Drupal\persistent_login
 */
class TokenHandler implements EventSubscriberInterface {

  /**
   * The token manager service.
   *
   * @var \Drupal\persistent_login\TokenManager
   */
  protected $tokenManager;

  /**
   * The cookie helper service.
   *
   * @var \Drupal\persistent_login\CookieHelper
   */
  protected $cookieHelper;

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The persistent token of the current request.
   *
   * @var \Drupal\persistent_login\PersistentToken
   */
  protected $token;

  /**
   * Construct a token manager object.
   *
   * @param \Drupal\persistent_login\TokenManager $token_manager
   *   The token manager service.
   * @param \Drupal\persistent_login\CookieHelperInterface $cookie_helper
   *   The cookie helper service.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   */
  public function __construct(
    TokenManager $token_manager,
    CookieHelperInterface $cookie_helper,
    SessionConfigurationInterface $session_configuration,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->tokenManager = $token_manager;
    $this->cookieHelper = $cookie_helper;
    $this->sessionConfiguration = $session_configuration;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Specify subscribed events.
   *
   * @return array
   *   The subscribed events.
   */
  public static function getSubscribedEvents() {
    $events = [];

    // Must occur before AuthenticationSubscriber.
    $events[KernelEvents::REQUEST][] = ['loadTokenOnRequestEvent', 310];
    $events[KernelEvents::RESPONSE][] = ['setTokenOnResponseEvent'];

    return $events;
  }

  /**
   * Load a token on this request, if a persistent cookie is provided.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The request event.
   */
  public function loadTokenOnRequestEvent(GetResponseEvent $event) {

    if (!$event->isMasterRequest()) {
      return;
    }

    $request = $event->getRequest();

    if ($this->cookieHelper->hasCookie($request)) {
      $this->token = $this->getTokenFromCookie($request);

      // Only validate the token if a user session has not been started.
      if (!$this->sessionConfiguration->hasSession($request)) {
        $this->token = $this->tokenManager->validateToken($this->token);

        if ($this->token->getStatus() === PersistentToken::STATUS_VALID) {
          try {
            // TODO make sure we are starting the user session properly.
            /** @var \Drupal\User\UserInterface $user */
            $user = $this->entityTypeManager->getStorage('user')
              ->load($this->token->getUid());
            user_login_finalize($user);
          }
          catch (PluginException $e) {
          }
        }
      }
    }
  }

  /**
   * Set or clear a token cookie on this response, if required.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The response event.
   */
  public function setTokenOnResponseEvent(FilterResponseEvent $event) {

    if (!$event->isMasterRequest()) {
      return;
    }

    if ($this->token) {
      $request = $event->getRequest();
      $response = $event->getResponse();
      $sessionOptions = $this->sessionConfiguration->getOptions($request);

      if ($this->token->getStatus() === PersistentToken::STATUS_VALID) {
        // New or updated token.
        $this->token = $this->tokenManager->updateToken($this->token);
        $response->headers->setCookie(
          new Cookie(
            $this->cookieHelper->getCookieName($request),
            $this->token,
            $this->token->getExpiry(),
            '/',  // TODO Path should probably match the base path.
            $sessionOptions['cookie_domain'],
            $sessionOptions['cookie_secure']
          )
        );
        $response->setPrivate();
      }
      elseif ($this->token->getStatus() === PersistentToken::STATUS_INVALID) {
        // Invalid token, or manually cleared token (e.g. user logged out).
        $this->tokenManager->deleteToken($this->token);
        $response->headers->clearCookie(
          $this->cookieHelper->getCookieName($request),
          '/', // TODO Path should probably match the base path.
          $sessionOptions['cookie_domain'],
          $sessionOptions['cookie_secure']
        );
        $response->setPrivate();
      }
      else {
        // Ignore token if status is STATUS_NOT_VALIDATED.
      }
    }
  }

  /**
   * Create a token object from the cookie provided in the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request that contains a persistent login cookie.
   *
   * @return \Drupal\persistent_login\PersistentToken
   *   A new PersistentToken object.
   */
  public function getTokenFromCookie(Request $request) {
    return PersistentToken::createFromString($this->cookieHelper->getCookieValue($request));
  }

  /**
   * Create and store a new token for the specified user.
   *
   * @param int $uid
   *   The user id to associate the token to.
   */
  public function setNewSessionToken($uid) {
    try {
      $this->token = $this->tokenManager->createNewTokenForUser($uid);
    }
    catch (TokenException $e) {
      // Ignore error creating new token.
    }
  }

  /**
   * Mark the user's current token as invalid.
   *
   * This will cause the token to be removed from the database at the end of the
   * request.
   */
  public function clearSessionToken() {
    if ($this->token) {
      $this->token = $this->token->setInvalid();
    }
  }

}
