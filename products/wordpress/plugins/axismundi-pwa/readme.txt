=== Axismundi PWA ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: pwa
Stable tag: 0.1.0-beta.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: pwa, push, notifications, service worker

Web Push for Axismundi, built on the PWA feature plugin rather than beside it.

== Description ==

The service worker belongs to the site, not to one plugin. Axismundi PWA
composes its push handlers into the worker the PWA feature plugin already
registers, and does nothing at all when that plugin is absent: it never
registers a second worker of its own, because two workers claiming one scope is
how a site ends up with a cache nobody controls.

What this plugin owns is the part that is specific to Axismundi -- the
subscriptions each browser makes, the VAPID identity they are signed with, and
the handlers that turn a push into a notification.

A push carries a delivery id and a category and nothing else. The endpoint is
public and the notification is shown on a lock screen, so the content is fetched
after the person opens it, through an authenticated request that asks again
whether they may still read that Actor's inbox.

= VAPID keys =

The private key is never stored in the database or in this plugin. It is read
from the environment or from a constant, so it lives wherever the site's other
secrets live:

    define( 'AXISMUNDI_PWA_VAPID_SUBJECT', 'mailto:you@example.com' );
    define( 'AXISMUNDI_PWA_VAPID_PUBLIC_KEY', '...' );
    define( 'AXISMUNDI_PWA_VAPID_PRIVATE_KEY', '...' );

`wp axismundi-pwa keys` generates a pair and prints the lines to paste. Only the
public key is ever handed to a browser.

Without keys, push capability is absent and every surface that would offer it
stays hidden -- the honest failure, rather than a switch that saves and delivers
nothing.

== Changelog ==

= 0.1.0-beta.1 =
* First pre-release. Push subscriptions per device, VAPID identity from the
  environment, service worker handlers composed into the PWA feature plugin's
  worker, a devices screen, and delivery through minishlink/web-push.
