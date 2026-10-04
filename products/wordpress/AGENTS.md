# AGENTS.md — WordPress products

The shipped theme and plugins. This is the source of record for anything
installed on a real site.

## Theme territory, plugin territory

The line is what survives deactivation. A theme owns presentation: block
styles, style variations, template parts, layout slots, progressive CSS and JS.
A plugin owns anything whose loss takes content or capability with it: durable
blocks, editor UI, persistence, external protocols.

**A theme must not call `register_block_type()`.** Theme Check fails it, and
ThemeCheck runs at wp.org submission, not in this repository's CI — so treat
this as a convention with a late and expensive failure. Dynamic markup goes
through `render_block_core/*` filters.

## Build the bridge in reverse

Start from what a core block actually emits, reset what core styles leak
through, then map to M3. Do not start from an Axismundi selector and assume the
core block matches it.

Computed values in the front end **and** the editor are the acceptance gate.
A source rule existing is not proof.

## Route the semantics before the paint

A `core/button` anchor with `href` is navigation and may take a Material
button's appearance. A real action, form submission, or federation call is
plugin territory even when it looks identical.

When a core block resembles an M3 component but carries different markup or
accessibility semantics, name which side owns the mismatch before styling it.
Do not collapse distinct core structures into one CSS patch.

## Token architecture

```txt
--md-ref-palette-*  ->  --md-sys-*  ->  --wp--preset--*  ->  consumers
```

Every `--md-sys-color-*` must resolve to `var(--md-ref-palette-*)`. Literal
hex, rgb, or hsl in the sys layer is forbidden. Dark mode swaps sys-to-ref
mappings only; it does not rewrite ref primitives.

**Enforced** by `tools/validators/validate_token_layering.py` axis E, which
reads this theme's `assets/styles/tokens.sys.color.*.css` on every PR.

`settings.custom.axismundi.*` leaves must be `var(--comp-*)`, `var(--md-sys-*)`
or `var(--md-ref-*)`. No automated guard exists — no shipped product declares
`settings.custom` today.

`theme.json` changes that alter a published token value, rather than adding a
binding, need the owner's approval.

## Plugin dependencies

Only the theme stands alone. Each plugin's header and `readme.txt` name what it
requires; nothing auto-installs a dependency, and a plugin whose requirement is
missing should fail closed rather than degrade quietly.

Before adding a global function, grep for the exact name. A redeclaration is a
fatal error, and this has happened.

## wp-env

Products are developed against `wp-env`. **Never update a mounted plugin from
inside `wp-admin`** — it empties the mount directory and keeps no backup.

**Never update WordPress core from inside `wp-admin` either.** `.wp-env.json`
declares the core version and is the only place that decides it; a `wp-admin`
update is drift that the next `wp-env start` silently reverts. Observed
2026-10-04: core had moved to `7.1.2` against a declared `#7.1`, leaving
`wp-content/upgrade-temp-backup/` behind.

If `wp-env start` fails with `destination path '…/WordPress' already exists and
is not an empty directory`, the checkout has lost its `.git`. wp-env decides
with `checkIsRepo('root')` and falls through to `clone`, which then refuses a
non-empty directory. **Do not delete the directory** — it holds `wp-content/uploads`.
Re-attach the metadata in place instead: `git init`, add the
`WordPress/WordPress` remote, shallow-fetch the declared tag, `checkout -f`. The
same applies to `WordPress-PHPUnit` (`wordpress-develop`, sparse `tests/phpunit`,
tag `X.Y.0` where core is `X.Y`). Both were repaired this way on 2026-10-04 with
no loss.

The Gutenberg plugin tracks **latest** deliberately (owner's decision,
2026-10-04): adapting to upstream change is worth more here than a reproducible
pin. Expect a measurement that held yesterday to fail today with no commit of
ours in between, and re-measure rather than assuming a regression is ours.

Pattern files under `patterns/` are not re-registered automatically with dev
mode off; clear the pattern cache after editing one.
