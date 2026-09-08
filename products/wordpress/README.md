# WordPress products

Themes and plugins that install on a real site. Each is versioned and released
on its own tag; see [Releases](https://github.com/Jiwoon-Kim/axismundi/releases)
and the product list in the [root README](../../README.md).

```txt
themes/     axismundi, and omphalos as its child
plugins/    23 plugins, grouped by what they own rather than by feature
```

## Theme territory, plugin territory

The line is about what survives deactivation, and it decides where a change
belongs before any code is written.

A **theme** may style core blocks, register block style variations, provide
template parts and layout slots, and enqueue progressive interaction CSS and
JS. Everything it owns is presentation: switch themes and the site looks
different, but nothing is lost.

A **plugin** owns durable custom blocks, editor UI, external protocol
integration, content parsing, and data persistence. Deactivate one and content
or capability goes away, which is exactly why it cannot live in a theme.

Two consequences worth stating, because both have been decided the wrong way
before:

- A `core/button` anchor with an `href` is navigation and may take a Material
  button's appearance from the theme. Action behaviour, form submission, and
  federation actions are plugin territory even when they look identical.
- A theme must not call `register_block_type()`. Theme Check fails it, and
  dynamic markup belongs in `render_block_core/*` filters instead.

## Dependencies between plugins

Only the theme stands alone. Several plugins assume others — the identity
registry underneath the social domains, the projection layer underneath the
activity ledger — and each plugin's own header and readme name what it
requires. Nothing here auto-installs a dependency; a plugin whose requirement
is missing is expected to fail closed rather than degrade quietly.

## Development

Products are developed against `wp-env`. Do not update a mounted plugin from
inside `wp-admin`: the update empties the mount directory and keeps no backup.
