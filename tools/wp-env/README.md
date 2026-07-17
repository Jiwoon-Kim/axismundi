# wp-env: pretty permalinks

`.wp-env.json` sets `/%postname%/` after every start:

```json
"lifecycleScripts": {
  "afterStart": "wp-env run cli wp rewrite structure /%postname%/ --hard"
}
```

That is all this repo needs. **A freshly created wp-env environment needs nothing at all** —
wp-env has enabled pretty permalinks by default since 11.0.0 and writes the `wp-cli.yml`
that makes it work. This directory exists for the one thing wp-env cannot do: repair an
environment created *before* that.

## Why pretty permalinks matter here

Object Projections 0.0.18 shipped a `/media/folder/{uuid}` route that 404'd on the live
site. It shipped green because the route could not be exercised locally on a plain-permalink
site — the `?ax_op_media_folder=` fallback worked, every fixture passed, and the pretty route
was deferred to "check it on staging". Anything that only manifests through a rewrite rule is
invisible until pretty permalinks are on.

## The legacy-environment repair (one time, per machine)

wp-env configures a site only when it **installs** it. An environment created before 11.0.0
keeps plain permalinks forever, and its `wp-cli.yml` stays **0 bytes** — wp-env guards that
write with `[ -f wp-cli.yml ] ||`, which is satisfied by an empty file. Upgrading wp-env
does not re-run any of it.

An empty `wp-cli.yml` is not cosmetic. WordPress writes rewrite rules into `.htaccess` only
when `got_mod_rewrite()` is true, and that is false under PHP CLI (`$is_apache` comes from
`$_SERVER['SERVER_SOFTWARE']`, which CLI does not set). WP-CLI's escape hatch is the
`apache_modules` setting — `Rewrite_Command::apache_modules()` mocks `apache_get_modules()`
and forces `$is_apache` — and that setting is exactly what the empty file is missing. So
`wp rewrite flush --hard` reports `Success: Rewrite rules flushed.` and writes nothing, the
`.htaccess` marker block stays empty, and Apache 404s every pretty URL before WordPress is
reached — including `/hello-world/`.

If pretty URLs 404 here, check the file inside the wp-env-managed WordPress directory
(`~/.wp-env/<project>/WordPress/wp-cli.yml`). If it is empty, write:

```yaml
apache_modules:
  - mod_rewrite
```

Then `npx wp-env start`. Verified: from plain permalinks with an empty marker block, that
one command restores 200s.

## Two traps

- **An Apache 404 is not a WordPress 404.** Apache's is `text/html; charset=iso-8859-1` and
  names the server; WordPress's is `charset=UTF-8`. The first means the request never
  reached PHP, so no amount of plugin debugging will explain it.
- **`AllowOverride None` is not the blocker.** Verified: with it untouched and a populated
  `.htaccess`, every pretty route serves 200 across a full container restart. Do not patch
  the container.

## Do not map a `wp-cli.yml` through `mappings`

An earlier version of this setup bind-mounted a repo `wp-cli.yml` over
`/var/www/html/wp-cli.yml`. It appeared to work, but it made
`wp rewrite structure --hard` silently stop writing `.htaccess` while
`wp rewrite flush --hard` still did — which looked like a WP-CLI bug and was not one. With
wp-env's own unmounted `wp-cli.yml`, `structure --hard` writes correctly and one command is
enough. If you find yourself needing two commands to flush, suspect a mount before
suspecting WP-CLI.
