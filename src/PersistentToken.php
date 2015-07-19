<?php

/**
 * @file
 * Contains Drupal\persistent_login\PersistentToken
 */

namespace Drupal\persistent_login;

use Drupal\Component\Utility\Crypt;

class PersistentToken {

  const STATUS_NOT_VALIDATED = 0;
  const STATUS_VALID = 1;
  const STATUS_INVALID = -1;

  /**
   * Long-lived identifier.
   * @var string
   */
  protected $series;

  /**
   * Single-use identifier.
   * @var string
   */
  protected $instance;

  /**
   * @var int
   */
  protected $uid;

  /**
   * @param string $series
   * @param string $instance
   * @param int $uid
   */
  public function __construct($series, $instance, $uid = 0) {
    $this->series = $series;
    $this->instance = $instance;
    $this->uid = $uid;
  }

  /**
   * Create a new token.
   *
   * @param int $uid
   *
   * @return static
   *   A token for the provided uid, with random series and instance identifiers.
   */
  public static function createNew($uid = 0) {
    return new static(
      \Drupal::csrfToken()->get(Crypt::randomBytesBase64()),
      \Drupal::csrfToken()->get(Crypt::randomBytesBase64()),
      $uid
    );
  }

  /**
   * Initialize a new object from a cookie value string.
   *
   * @param string $value
   * @return static
   */
  public static function createFromString($value) {
    $values = explode(':', $value);
    return new static($values[0], $values[1]);
  }

  /**
   * Return a string suitable for use as a cookie value.
   *
   * @return string
   */
  public function __toString() {
    return $this->series . ':' . $this->instance;
  }

  /**
   * Get the uid of this token.
   *
   * @return int
   */
  public function getUid() {
    return $this->uid;
  }

  /**
   * Set the uid for this token.
   * This marks the token as valid.
   *
   * @param $uid
   * @return $this
   */
  public function setUid($uid) {
    $this->uid = $uid;

    return $this;
  }

  /**
   * @return int
   *  A status constant.
   */
  public function getStatus() {
    if ($this->uid === 0) {
      return self::STATUS_NOT_VALIDATED;
    }
    elseif ($this->uid > 0) {
      return self::STATUS_VALID;
    }
    else {
      return self::STATUS_INVALID;
    }
  }

  /**
   * Mark this token as invalid.
   *
   * @return $this
   */
  public function setInvalid() {
    $this->uid = self::STATUS_INVALID;

    return $this;
  }

  /**
   * Get the series identifier of this token.
   *
   * @return string
   */
  public function getSeries() {
    return $this->series;
  }

  /**
   * Get the instance identifier of this token.
   *
   * @return string
   */
  public function getInstance() {
    return $this->instance;
  }

  /**
   * Update the instance identifier with a new random value.
   *
   * @return $this
   */
  public function updateInstance() {
    $this->instance = \Drupal::csrfToken()->get(Crypt::randomBytesBase64());

    return $this;
  }
}
