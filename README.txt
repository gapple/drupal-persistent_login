Persistent Login module

PREREQUISITES

Drupal 4.7.

OVERVIEW

The Persistent Login module provides the familiar "Remember Me" option
in the user login form.

INSTALLATION

1.  Install and activate Persistent Login like every other Drupal module.

2.  For maximum security, edit your settings.php file so PHP session
    cookies have a lifetime of the browser session:

    ini_set('session.cookie_lifetime',  0);

3.  Visit admin >> settings >> persistent_login to set how long
    persistent sessions should last and which pages users cannot
    access without a password-based login.

DESCRIPTION

The Persistent Login module provides the familiar "Remember Me" option in
the user login form.

The administrator can control how long user logins are remembered and
specify which pages a remembered user can or cannot access without
explicitly logging in with a username and password (e.g. you cannot
change your password with just a persistent login).  Users also have
the option of explicitly clearing all of their remembered logins.

Persistent Login is independent of the PHP session settings and is more
secure (and user-friendly) than simply setting a long PHP session
lifetime.  Persistent Login's design is based on "Persistent Login Cookie
Best Practice" by Charles Miller, 01/19/2004.  See
http://fishbowl.pastiche.org/2004/01/19/persistent_login_cookie_best_practice
for details.

AUTHOR

Barry Jaspan
firstname at lastname dot org
