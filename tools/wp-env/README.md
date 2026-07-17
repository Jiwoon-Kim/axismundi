# wp-env: pretty permalinks

`.wp-env.json` maps `wp-cli.yml` into the docroot and runs two commands after every start:

```
wp rewrite structure /%postname%/ && wp rewrite flush --hard
```

Together these make **pretty routes testable locally**. Neither part is optional, and the
two-command split is not redundant.

## Why this is here

A wp-env site starts on plain permalinks with a `.htaccess` whose WordPress marker block is
**empty** — no `RewriteEngine`, no rules. Turn on pretty permalinks and Apache hands every
pretty URL to the filesystem and 404s it before WordPress is reached, including plain core
permalinks like `/hello-world/`. The symptom reads like a WordPress problem and is not one:
an Apache 404 is `text/html; charset=iso-8859-1` and names the server, while a WordPress
404 is `charset=UTF-8`.

The usual repair, `wp rewrite flush --hard`, silently does nothing. WordPress writes the
rules only when `got_mod_rewrite()` is true, and that is false under PHP CLI — `$is_apache`
comes from `$_SERVER['SERVER_SOFTWARE']`, which CLI does not set. Because the command still
reports `Success: Rewrite rules flushed.`, the file looks permanently unfixable and it is
easy to conclude that wp-env simply cannot serve pretty permalinks. It can.

`wp-cli.yml` is the fix. WP-CLI's rewrite command mocks `apache_get_modules()` and sets
`$is_apache` from the `apache_modules` setting, so declaring `mod_rewrite` lets it write
`.htaccess` itself. Nothing about the container needs patching, and no `.htaccess` is kept
in this repo.

## The two-command split

`wp rewrite structure /%postname%/ --hard` **does not write `.htaccess`**, while
`wp rewrite flush --hard` does. `structure` re-runs the flush in a separate WP-CLI process
that does not carry the `apache_modules` setting into it. So the structure change and the
hard flush are issued as two commands. This is the same failure reported upstream in
[gutenberg#50538](https://github.com/WordPress/gutenberg/issues/50538) (open, undiagnosed).

## What this cost

Object Projections 0.0.18 shipped a `/media/folder/{uuid}` route that 404'd on the live
site, and it shipped because the route could not be exercised here — the plain
`?ax_op_media_folder=` fallback was green, every fixture was green, and the pretty route was
deferred to "check it on staging". Routing regressions are now catchable: a rule missing
from the stored `rewrite_rules` option can be reproduced and fetched over real HTTP,
exactly as it failed on staging.

## Do not

- **Do not patch `AllowOverride`.** It is not the blocker. Verified: with
  `AllowOverride None` untouched and a populated `.htaccess`, every pretty route serves 200
  across a full container restart.
- **Do not commit a `.htaccess` and mount it.** That works, but it makes a repo file live
  under WordPress's pen — saving Settings > Permalinks in the browser would rewrite it.
