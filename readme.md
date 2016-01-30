Persistent Login
================

The Persistent Login module provides the familiar "Remember Me" option on the
user login form.


## Description

Persistent Login is independent of the PHP session settings and is more secure
(and user-friendly) than simply setting a long PHP session lifetime.

The module's settings allow the administrator to:

- Control how long user logins are remembered.
- Control how many different persistent logins are remembered per user.

## Setup

1. Edit your `services.yml` file so PHP session cookies have a lifetime of the
   browser session:

        parameters:
          session.storage.options:
            cookie_lifetime: 0

2. Visit *Administration > Configuration > System > Persistent Login* to
   configure available options.

3. If using a reverse-proxy cache, such as Varnish, the configuration must be
   updated to not respond from the cache for requests that send a persistent
   login cookie.
