# wp-env: pretty permalinks

`.wp-env.json` maps this directory's `htaccess` onto `/var/www/html/.htaccess` and turns on
`/%postname%/` permalinks after every start. Both exist so that **pretty routes are
testable locally**, and neither is optional.

## Why this is here

wp-env ships a `.htaccess` whose WordPress marker block is **empty** — no `RewriteEngine`,
no rules. Apache therefore hands every pretty URL to the filesystem and 404s it before
WordPress is ever reached, including plain core permalinks like `/hello-world/`. The
symptom reads like a WordPress problem and is not one: an Apache 404 is
`text/html; charset=iso-8859-1` and names the server, while a WordPress 404 is
`charset=UTF-8`.

WP-CLI cannot repair it. `wp rewrite flush --hard` writes the rules only when
`got_mod_rewrite()` is true, and that is false under PHP CLI (`$is_apache` comes from
`$_SERVER['SERVER_SOFTWARE']`, which CLI does not set). So the usual fix silently does
nothing, which is what makes the empty file look permanent.

This cost real money. Object Projections 0.0.18 shipped a `/media/folder/{uuid}` route that
404'd on the live site, and it shipped because the route could not be exercised here — the
plain `?ax_op_media_folder=` fallback was green, every fixture was green, and the pretty
route was deferred to "check it on staging". The conclusion drawn at the time, that wp-env
*structurally cannot* serve pretty permalinks, was wrong: Apache's `AllowOverride None` is
not the blocker, and nothing about the container needs patching. It was one empty file.

## What to know

- **Do not "fix" this by editing `AllowOverride`.** Verified: with `AllowOverride None`
  restored and this `htaccess` in place, every pretty route serves 200 across a full
  container restart.
- **This file is bind-mounted, so it is live.** Saving Settings > Permalinks in the browser
  runs under Apache, where `got_mod_rewrite()` *is* true, and WordPress will rewrite the
  mounted file — which means it edits this repo file. Content should come back equivalent;
  if it comes back with translated comments or extra rules, that is why.
- **Routing regressions are now catchable.** A rewrite rule missing from the stored
  `rewrite_rules` option can be reproduced and fetched over real HTTP here, exactly as it
  failed on staging.
