<?php

namespace Drupal\persistent_login;

use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for Persistent Login Cookie Helper services.
 */
interface CookieHelperInterface {

  /**
   * Returns the name of the persistent login cookie.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The cookie name.
   */
  public function getCookieName(Request $request);

  /**
   * Returns the value of the persistent login cookie.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The cookie value.
   */
  public function getCookieValue(Request $request);

  /**
   * Checks if a request contains a persistent login cookie.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return bool
   *   TRUE if the request provides a persistent login cookie.
   */
  public function hasCookie(Request $request);

}
