<?php

/**
 * @file
 * Contains Drupal\persistent_login\TokenManager.
 */

namespace Drupal\persistent_login\EventSubscriber;

use DateTime;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\persistent_login\PersistentToken;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class TokenManager.
 *
 * @package Drupal\persistent_login
 */
class TokenManager implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The session configuration object.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The persistent token of the current request.
   *
   * @var PersistentToken
   */
  protected $token;

  /**
   * Construct a token manager object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The token generator.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration
   *   The session configuration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   The enitity manager.
   */
  public function __construct(
    Connection $connection,
    ConfigFactoryInterface $configFactory,
    CsrfTokenGenerator $csrfToken,
    SessionConfigurationInterface $sessionConfiguration,
    EntityTypeManagerInterface $entityManager
  ) {
    $this->configFactory = $configFactory;
    $this->connection = $connection;
    $this->csrfToken = $csrfToken;
    $this->sessionConfiguration = $sessionConfiguration;
    $this->entityManager = $entityManager;
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

    if ($this->hasCookie($request)) {
      $this->token = $this->getTokenFromCookie($request);

      // Only validate the token if a user session has not been started.
      if (!$this->sessionConfiguration->hasSession($request)) {
        $this->token = $this->validateToken($this->token);

        if ($this->token->getStatus() === PersistentToken::STATUS_VALID) {
          // TODO make sure we are starting the user session properly.
          /** @var \Drupal\User\UserInterface $user */
          $user = $this->entityManager->getStorage('user')
            ->load($this->token->getUid());
          user_login_finalize($user);
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

      if ($this->token->getStatus() === PersistentToken::STATUS_VALID) {
        // New or updated token.
        $this->token = $this->updateToken($this->token);
        $response->headers->setCookie(
          new Cookie(
            $this->getCookieName($request),
            $this->token,
            $this->token->getExpiry()
          )
        );
      }
      elseif ($this->token->getStatus() === PersistentToken::STATUS_INVALID) {
        // Invalid token, or manually cleared token (e.g. user logged out).
        $this->deleteToken($this->token);
        $response->headers->clearCookie($this->getCookieName($request));
      }
      else {
        // Ignore token if status is STATUS_NOT_VALIDATED.
      }
    }
  }

  /**
   * Get the name of the persistent login cookie.
   *
   * Value is based on the session cookie name.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The cookie name.
   */
  protected function getCookieName(Request $request) {
    $sessionConfigurationSettings = $this->sessionConfiguration->getOptions($request);
    return 'PL' . substr($sessionConfigurationSettings['name'], 4);
  }

  /**
   * Check if a request contains a persistent login cookie.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return bool
   *   True if the request provides a persistent login cookie.
   */
  public function hasCookie(Request $request) {
    return $request->cookies->has($this->getCookieName($request));
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
    return PersistentToken::createFromString($request->cookies->get($this->getCookieName($request)));
  }

  /**
   * Check the database for the provided token.
   *
   * If valid, a new token is returned with the uid set to the associated user,
   * otherwise a new invalid token is returned.
   *
   * @param \Drupal\persistent_login\PersistentToken $token
   *   The token to validate.
   *
   * @return \Drupal\persistent_login\PersistentToken
   *   A validated token.
   */
  public function validateToken(PersistentToken $token) {

    $selectResult = $this->connection->select('persistent_login', 'pl')
      ->fields('pl', ['uid', 'created', 'expires'])
      ->condition('expires', REQUEST_TIME, '>')
      ->condition('series', $token->getSeries())
      ->condition('instance', $token->getInstance())
      ->execute();

    if (($tokenData = $selectResult->fetchObject())) {
      return $token
        ->setUid($tokenData->uid)
        ->setCreated(new DateTime(('@' . $tokenData->created)))
        ->setExpiry(new DateTime('@' . $tokenData->expires));
    }
    else {
      return $token->setInvalid();
    }
  }

  /**
   * Create and store a new token for the specified user.
   *
   * @param int $uid
   *   The user id to associate the token to.
   */
  public function setNewSessionToken($uid) {

    $config = $this->configFactory->get('persistent_login.settings');

    $token = new PersistentToken(
        $this->csrfToken->get(Crypt::randomBytesBase64()),
        $this->csrfToken->get(Crypt::randomBytesBase64()),
        $uid
      );
    if ($config->get('lifetime') === 0) {
      $token->setExpiry(new DateTime("@" . 2147483647));
    }
    else {
      $token->setExpiry(new DateTime("now +" . $config->get('lifetime') . " day"));
    }

    try {
      $this->connection->insert('persistent_login')
        ->fields([
          'uid' => $uid,
          'series' => $token->getSeries(),
          'instance' => $token->getInstance(),
          'created' => $token->getCreated()->getTimestamp(),
          'expires' => $token->getExpiry()->getTimestamp(),
        ])
        ->execute();

      $this->token = $token;
    }
    catch (\Exception $e) {
      // TODO handle new token failure.
      return;
    }

    // Check for exceeding the maximum tokens per user.
    if ($config->get('max_tokens') !== 0) {
      try {
        $oldTokensResult = $this->connection->select('persistent_login', 'pl')
          ->fields('pl', ['created', 'expires'])
          ->orderBy('expires', 'DESC')
          ->orderBy('created', 'DESC')
          ->condition('uid', $uid)
          ->range($config->get('max_tokens'), 1)
          ->execute();
        if (($oldestToken = $oldTokensResult->fetchObject())) {
          $this->connection->delete('persistent_login')
            ->condition('uid', $uid)
            ->condition('expires', $oldestToken->expires, '<=')
            ->condition('created', $oldestToken->created, '<=')
            ->execute();
        }
      }
      catch (\Exception $e) {

      }
    }

  }

  /**
   * Update the provided token's instance identifier.
   *
   * The new instance value is also propagated the to the database.
   *
   * @param \Drupal\persistent_login\PersistentToken $token
   *   The token.
   *
   * @return \Drupal\persistent_login\PersistentToken
   *   An updated token.
   */
  public function updateToken(PersistentToken $token) {

    $originalInstance = $token->getInstance();
    $token = $token->updateInstance($this->csrfToken->get(Crypt::randomBytesBase64()));

    try {
      $this->connection->update('persistent_login')
        ->fields(['instance' => $token->getInstance()])
        ->condition('series', $token->getSeries())
        ->condition('instance', $originalInstance)
        ->execute();
    }
    catch (\Exception $e) {
      // If update fails, new token will just fail to validate when used.
    }
    return $token;
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

  /**
   * Delete the specified token from the database, if it exists.
   *
   * @param \Drupal\persistent_login\PersistentToken $token
   *   The token.
   *
   * @return \Drupal\persistent_login\PersistentToken
   *   An invalidated token.
   */
  public function deleteToken(PersistentToken $token) {
    try {
      $this->connection->delete('persistent_login')
        ->condition('series', $token->getSeries())
        ->condition('instance', $token->getInstance())
        ->execute();
    }
    catch (\Exception $e) {

    }
    return $token->setInvalid();
  }

  /**
   * Remove expired tokens from the database.
   */
  public function cleanupExpiredTokens() {
    try {
      $this->connection->delete('persistent_login')
        ->condition('expires', REQUEST_TIME, '<')
        ->execute();
    }
    catch (\Exception $e) {

    }
  }

}
