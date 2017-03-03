<?php

namespace Drupal\Tests\persistent_login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the persistent login functionality.
 *
 * @group persistent_login
 */
class PersistentLoginTest extends BrowserTestBase {

  /**
   * A test user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['persistent_login'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Mimic the required setup of the module by setting the session cookie
    // lifetime to 0.
    $parameters = $this->container->getParameter('session.storage.options');
    $parameters['cookie_lifetime'] = 0;
    $this->setContainerParameter('session.storage.options', $parameters);
    $this->rebuildContainer();
    $this->resetAll();

    // Create a test user.
    $this->user = $this->createUser([], 'Garnett Tyrell');
  }

  /**
   * Tests whether a user can be persistently logged in.
   *
   * @param bool $remember_me
   *   Whether or not the "Remember me" option should be checked when logging
   *   in.
   * @param string $message
   *   Error message for test failure.
   *
   * @dataProvider loginProvider
   */
  public function testPersistentLogin($remember_me, $message) {
    // Since we are not logged in yet, the homepage should show a "Log in" link.
    // The reason we are testing the visibility of the "Log in" link rather than
    // inspecting the session cookies, is because this way we can also validate
    // that the page cache is correctly cleared in addition to checking if the
    // user is logged in or not.
    $this->assertTrue($this->homepageHasLoginForm(), 'The login form should be present on the page.');

    // Log in through the UI.
    $this->drupalGet('user');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'name' => $this->user->getAccountName(),
      'pass' => $this->user->passRaw,
      'persistent_login' => $remember_me,
    ], t('Log in'));

    // Check that the homepage now doesn't show the "Log in" link any more.
    $this->assertFalse($this->homepageHasLoginForm(), 'The login form should not be present on the page.');

    // Simulate the user closing the browser window and reopening it, by
    // clearing the session cookies.
    $this->restartSession();

    // The "Log in" link should now only be shown on the homepage when the
    // "Remember me" option was enabled.
    $this->assertEquals($remember_me, !$this->homepageHasLoginForm(), $message);
  }

  /**
   * Emulates closing and re-opening of the browser.
   */
  public function restartSession() {
    $cookie_jar = $this->getCookieJar();

    // We are going to keep all the cookies with a valid expire time. The
    // \Symfony\Component\BrowserKit\CookieJar::all() method already removes
    // expired cookies, so we need to strip the ones with a null expire time.
    // A null expire time means that the cookie is going to be deleted when the
    // browser is closed.
    $persistent = [];
    foreach ($cookie_jar->all() as $cookie) {
      if (!is_null($cookie->getExpiresTime())) {
        $persistent[] = $cookie;
      }
    }

    $this->getSession()->restart();
    $cookie_jar = $this->getCookieJar();

    foreach ($persistent as $cookie) {
      $cookie_jar->set($cookie);
    }
  }

  /**
   * Returns the jar which contains the cookies for the current session.
   *
   * @return \Symfony\Component\BrowserKit\CookieJar
   *   The cookie jar.
   */
  protected function getCookieJar() {
    return $this->getSession()->getDriver()->getClient()->getCookieJar();
  }

  /**
   * Returns whether or not the login form is displayed on the homepage.
   *
   * @return bool
   *   Whether or not the login form is displayed.
   */
  protected function homepageHasLoginForm() {
    $this->drupalGet('<front>');
    return (bool) $this->getSession()->getPage()->findButton('Log in');
  }

  /**
   * Data provider for testPersistentLogin().
   *
   * @return array
   *   An array of test cases. Each test case is an array containing a boolean
   *   that indicates whether or not the "Remember me" option should be checked
   *   when logging in and a message for the assertion.
   */
  public function loginProvider() {
    return [
      [
        // When the "Remember me" functionality is not enabled, the user should
        // not be logged in after starting a new session.
        FALSE,
        'The user should not be logged in after starting a new session.',
      ],
      [
        // When the "Remember me" functionality is enabled, the user should be
        // logged in after starting a new session.
        TRUE,
        'The user should be logged in after starting a new session.',
      ],
    ];
  }

}
