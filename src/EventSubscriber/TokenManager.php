<?php

/**
 * @file
 * Contains Drupal\persistent_login\TokenManager
 */

namespace Drupal\persistent_login\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
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
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * @var PersistentToken
   */
  protected $token;

  public function __construct(Connection $connection, SessionConfigurationInterface $sessionConfiguration, EntityManagerInterface $entityManager) {
    $this->connection = $connection;
    $this->sessionConfiguration = $sessionConfiguration;
    $this->entityManager = $entityManager;
  }

  public static function getSubscribedEvents() {
    $events = array();

    // Must occur before AuthenticationSubscriber.
    $events[KernelEvents::REQUEST][] = ['loadTokenOnRequestEvent', 310];
    $events[KernelEvents::RESPONSE][] = ['setTokenOnResponseEvent'];

    return $events;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   */
  public function loadTokenOnRequestEvent(GetResponseEvent $event) {

    if (!$event->isMasterRequest()) {
      return;
    }

    $request = $event->getRequest();

    if ($this->hasCookie($request)) {

      $this->token = $this->getTokenFromCookie($request);

      if (!$this->sessionConfiguration->hasSession($request) && $this->validateToken($this->token)) {
        // TODO make sure we start the user session properly
        $user = $this->entityManager->getStorage('user')->load($this->token->getUid());
        user_login_finalize($user);
      }
    }
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   */
  public function setTokenOnResponseEvent (FilterResponseEvent $event) {

    if (!$event->isMasterRequest()) {
      return;
    }

    if ($this->token) {
      $request = $event->getRequest();
      $response = $event->getResponse();

      if ($this->token->getUid()) {
        $this->updateToken($this->token);
        $response->headers->setCookie(new Cookie($this->getCookieName($request), $this->token));
      }
      else if ($this->token->getUid() == PersistentToken::INVALID){
        $this->deleteToken($this->token);
        $response->headers->clearCookie($this->getCookieName($request));
      }
    }
  }

  /**
   * Get the name of the persistent login cookie, based on the session cookie name.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return string
   */
  protected function getCookieName(Request $request) {
    $sessionConfigurationSettings = $this->sessionConfiguration->getOptions($request);
    return 'PL' . substr($sessionConfigurationSettings['name'], 4);
  }

  /**
   * Check if a request contains a persistent login cookie.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return bool
   */
  public function hasCookie(Request $request) {
    return $request->cookies->has($this->getCookieName($request));
  }

  /**
   * Create a token object from the cookie provided in the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Drupal\persistent_login\PersistentToken
   */
  public function getTokenFromCookie(Request $request) {
    return PersistentToken::createFromString($request->cookies->get($this->getCookieName($request)));
  }

  /**
   * Check the database for the provided token, and update the token uid if
   * successful.
   *
   * @param \Drupal\persistent_login\PersistentToken $token
   *
   * @return bool
   *   If a valid entry for the provided token is stored in the database.
   */
  public function validateToken(PersistentToken $token) {

    $selectResult = $this->connection->select('persistent_login', 'pl')
      ->fields('pl', array('uid'))
      ->condition('expires', REQUEST_TIME, '>')
      ->condition('series', $token->getSeries())
      ->condition('instance', $token->getInstance())
      ->execute();

    if ($tokenData = $selectResult->fetchObject()) {
      $token->setUid($tokenData->uid);
      return TRUE;
    }
    else {
      $token->setUid(PersistentToken::INVALID);
      return FALSE;
    }
  }

  /**
   * Create and store a new token for the specified user.
   *
   * @param $uid
   */
  public function setNewToken($uid) {

    $token = PersistentToken::createNew($uid);

    try {
      $this->connection->insert('persistent_login')
        ->fields(array(
          'uid' => $uid,
          'series' => $token->getSeries(),
          'instance' => $token->getInstance(),
          'expires' => strtotime('now +30 day'),
        ))
        ->execute();

      $this->token = $token;
    }
    catch(\Exception $e) {
      // TODO handle new token failure
      return;
    }
  }

  /**
   * Update the provided token object's instance identifier, and propagate the
   * new value to the database.
   *
   * @param \Drupal\persistent_login\PersistentToken $token
   */
  public function updateToken(PersistentToken $token) {

    $originalInstance = $token->getInstance();

    $token->updateInstance();

    try {
      $this->connection->update('persistent_login')
        ->fields(array('instance' => $token->getInstance()))
        ->condition('series', $token->getSeries())
        ->condition('instance', $originalInstance)
        ->execute();
    }
    catch (\Exception $e) {

    }
  }

  /**
   * Mark the user's current token as invalid.
   *
   * This will cause the token to be removed from the database at the end of the
   * request.
   */
  public function clearToken() {
    if ($this->token) {
      $this->token->setUid(PersistentToken::INVALID);
    }
  }

  /**
   * Delete the specified token from the database, if it exists.
   *
   * @param \Drupal\persistent_login\PersistentToken
   */
  public function deleteToken(PersistentToken $token) {
    try {
      $this->connection->delete('persistent_login')
        ->condition('series', $token->getSeries())
        ->condition('instance', $token->getInstance())
        ->execute();

      $token->setUid(PersistentToken::INVALID);
    }
    catch (\Exception $e) {

    }
  }
}
