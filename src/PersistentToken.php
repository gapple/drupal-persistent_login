<?php

namespace Drupal\persistent_login;

/**
 * Class PersistentToken.
 *
 * @package Drupal\persistent_login
 */
class PersistentToken {

  const STATUS_NOT_VALIDATED = 0;
  const STATUS_VALID = 1;
  const STATUS_INVALID = -1;

  /**
   * Long-lived identifier.
   *
   * @var string
   */
  protected $series;

  /**
   * Single-use identifier.
   *
   * @var string
   */
  protected $instance;

  /**
   * User id.
   *
   * @var int
   */
  protected $uid;


  /**
   * Creation time.
   *
   * @var \DateTimeInterface
   */
  protected $created;

  /**
   * Refresh time.
   *
   * @var \DateTimeInterface
   */
  protected $refreshed;

  /**
   * Expiration time.
   *
   * @var \DateTimeInterface
   */
  protected $expires;

  /**
   * Construct a persistent token.
   *
   * If a user id is not provided, the token is marked as not-validated.
   *
   * @param string $series
   *   The long-lived identifier.
   * @param string $instance
   *   The single-use identifier.
   * @param int $uid
   *   The user id (optional).
   */
  public function __construct($series, $instance, $uid = self::STATUS_NOT_VALIDATED) {
    $this->series = $series;
    $this->instance = $instance;
    $this->uid = $uid;

    // Set the default creation date.
    // Existing tokens can update the value afterwards with setCreated().
    $this->created = $this->refreshed = new \DateTime();
  }

  /**
   * Initialize a new object from a cookie value string.
   *
   * @param string $value
   *   The cookie value.
   *
   * @return static
   *   A new token.
   */
  public static function createFromString($value) {
    list($series, $instance) = explode(':', $value);
    return new static($series, $instance);
  }

  /**
   * Initialize a new object from an array of values.
   *
   * @param $values
   *   An array of values to set object properties.
   */
  public static function createFromArray(array $values) {
    if (empty($values['series'])) {
      throw new \Exception("Required property 'series' not set.");
    }
    if (empty($values['instance'])) {
      throw new \Exception("Required property 'instance' not set.");
    }

    $token = new static($values['series'], $values['instance'], $values['uid']);
      $token = $token
        ->setCreated(new \DateTime('@' . $values['created']))
        ->setRefreshed(new \DateTime('@' . $values['refreshed']))
        ->setExpiry(new \DateTime('@' . $values['expires']));

    return $token;
  }

  /**
   * Return a string suitable for use as a cookie value.
   *
   * @return string
   *   The cookie value.
   */
  public function __toString() {
    return $this->series . ':' . $this->instance;
  }

  /**
   * Get the uid of this token.
   *
   * @return int
   *   The user id.
   */
  public function getUid() {
    return $this->uid;
  }

  /**
   * Set the uid for this token.
   *
   * This marks the token as valid.
   *
   * @param int $uid
   *   The user id.
   *
   * @return $this
   */
  public function setUid($uid) {
    $this->uid = $uid;

    return $this;
  }

  /**
   * The token validation status.
   *
   * @return int
   *   A validation status constant.
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
   *   The series identifier.
   */
  public function getSeries() {
    return $this->series;
  }

  /**
   * Get the instance identifier of this token.
   *
   * @return string
   *   The instance identifier.
   */
  public function getInstance() {
    return $this->instance;
  }

  /**
   * Update the instance identifier.
   *
   * This also updates the token's last refresh time.
   *
   * @param string $instance
   *   The new instance identifier.
   *
   * @return $this
   */
  public function updateInstance($instance) {
    $this->instance = $instance;

    return $this->setRefreshed(new \DateTime());
  }

  /**
   * Get the creation time of this token.
   *
   * @return \DateTimeInterface
   *   The creation time.
   */
  public function getCreated() {
    return $this->created;
  }

  /**
   * Set the creation time of this token.
   *
   * @param \DateTimeInterface $date
   *   The creation time.
   *
   * @return $this
   */
  public function setCreated(\DateTimeInterface $date) {
    $this->created = $date;

    return $this;
  }

  /**
   * Get the last refresh time of this token.
   *
   * @return \DateTimeInterface
   *   The last refresh time.
   */
  public function getRefreshed() {
    return $this->refreshed;
  }

  /**
   * Set the last refresh time of this token.
   *
   * @param \DateTimeInterface $date
   *   The last refresh time.
   *
   * @return $this
   */
  public function setRefreshed(\DateTimeInterface $date) {
    $this->refreshed = $date;

    return $this;
  }

  /**
   * Get the expiry time for this token.
   *
   * @return \DateTimeInterface
   *   The expiry time.
   */
  public function getExpiry() {
    return $this->expires;
  }

  /**
   * Set the expiry time for this token.
   *
   * @param \DateTimeInterface $date
   *   The expiry time.
   *
   * @return $this
   */
  public function setExpiry(\DateTimeInterface $date) {
    $this->expires = $date;

    return $this;
  }

}
