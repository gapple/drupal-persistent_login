<?php

namespace Drupal\persistent_login;

use DateTime;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Class TokenManager.
 *
 * @package Drupal\persistent_login
 */
class TokenManager {

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
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Construct a token manager object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The token generator.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    Connection $connection,
    ConfigFactoryInterface $configFactory,
    CsrfTokenGenerator $csrfToken,
    LoggerInterface $logger,
    TimeInterface $time
  ) {
    $this->configFactory = $configFactory;
    $this->connection = $connection;
    $this->csrfToken = $csrfToken;
    $this->logger = $logger;
    $this->time = $time;
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
      ->fields('pl', ['uid', 'created', 'refreshed', 'expires'])
      ->condition('expires', $this->time->getRequestTime(), '>')
      ->condition('series', $token->getSeries())
      ->condition('instance', $token->getInstance())
      ->execute();

    if (($tokenData = $selectResult->fetchObject())) {
      return $token
        ->setUid($tokenData->uid)
        ->setCreated(new DateTime('@' . $tokenData->created))
        ->setRefreshed(new DateTime('@' . $tokenData->refreshed))
        ->setExpiry(new DateTime('@' . $tokenData->expires));
    }
    else {
      return $token->setInvalid();
    }
  }

  /**
   * Create a new token for the specified user.
   *
   * @param int $uid
   *   The user id to associate the token to.
   *
   * @return PersistentToken
   *   A new PersistentToken object.
   */
  public function createNewTokenForUser($uid) {

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
          'refreshed' => $token->getRefreshed()->getTimestamp(),
          'expires' => $token->getExpiry()->getTimestamp(),
        ])
        ->execute();
    }
    catch (\Exception $e) {
      throw new TokenException("An error occurred storing the new token", 0, $e);
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
        $this->logger->error('Unable to delete extra persistent tokens for user with uid @uid', ['@uid' => $uid]);
      }
    }

    return $token;
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
        ->fields([
          'instance' => $token->getInstance(),
          'refreshed' => $token->getRefreshed()->getTimestamp(),
        ])
        ->condition('series', $token->getSeries())
        ->condition('instance', $originalInstance)
        ->execute();
    }
    catch (\Exception $e) {
      throw new TokenException("An error occurred updating the token", 0, $e);
    }
    return $token;
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
      throw new TokenException("An error occurred trying to delete the token", 0, $e);
    }
    return $token->setInvalid();
  }

  /**
   * Remove expired tokens from the database.
   */
  public function cleanupExpiredTokens() {
    try {
      $this->connection->delete('persistent_login')
        ->condition('expires', $this->time->getRequestTime(), '<')
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('An error occurred while removing expired tokens');
    }
  }

  /**
   * Get all active tokens for a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   A user to get active tokens for.
   *
   * @return PersistentToken[]
   *   An array of the active tokens for the provided user.
   */
  public function getTokensForUser(UserInterface $user) {
    $tokens = [];

    try {
      $tokensResult = $this->connection->select('persistent_login', 'pl')
        ->fields('pl', ['uid', 'series', 'instance', 'created', 'refreshed', 'expires'])
        ->condition('uid', $user->id())
        ->condition('expires', $this->time->getRequestTime(), '>')
        ->orderBy('created')
        ->execute();

      while (($tokenArray = $tokensResult->fetchAssoc())) {
        $tokens[] = PersistentToken::createFromArray($tokenArray);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to list tokens for user with uid @uid', [
        '@uid' => $user->id(),
      ]);
    }

    return $tokens;
  }

}
