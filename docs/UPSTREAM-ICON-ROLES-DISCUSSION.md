# Draft — reply to Gutenberg discussion #82229, comment 18243970

> Status: **posted** 2026-09-06 as a threaded reply under @meyshad's comment.
> <https://github.com/WordPress/gutenberg/discussions/82229#discussioncomment-18314084>
>
> The plugin link was left out on purpose: the Show and tell entry carries it,
> and a comment narrowing an API question reads better without one.

## What this draft stopped trying to do

Two earlier versions of this file overreached and were cut back.

The first proposed a separate discussion. It was not one: #82228 owns how an
icon-only control gets its accessible name, #82229 owns what an icon reference
resolves to, and @meyshad had already replied there with the provider shape.

The second stayed in #82229 but proposed a new contract — `IconRequirement`,
a block-scoped semantic slot, and `state` carried in the reference. That is a
layer above the question being asked, and it argued against this block's own
implementation. Checked in the source: `render.php` stores one glyph name per
mode (`'light' => 'light_mode'`) and `style.css` expresses selection by setting
`--md-icon-fill: 1` on `[aria-pressed="true"]`. There is no second stored
reference for the selected state anywhere in the block. So the claim that state
must live in the reference came from a hypothetical SVG provider's cost, not
from anything this block has hit — and it collided with @meyshad's boundary
("variable-font state stays CSS/control state unless it represents a real named
variant") for no gain.

What survives is narrower and is only about the question #82229 actually asks.

## The comment

```
This makes me think there are two distinct cases.

When an author chooses a pictogram, an opaque `provider` / `name` / optional
`variant` reference is the right shape.

A fixed UI control is different. A light/dark/auto switcher does not ask an
author to choose its sun or moon; the block owns the action, state, and
accessible name, while the active theme owns the icon language and its visual
state. A theme can already provide that through an icon font, with selection
expressed through CSS rather than a second stored icon reference.

That is the model used by my Theme Switcher block. It works with its companion
theme today; the portability problem is that the block must currently know the
theme's font class and ligature names. I do not think that implies a semantic
slot API should be designed in this thread. It does reinforce the narrower
question here: a provider-based Icon Registry should not require SVG markup or
enumeration as the only way for a theme-provided icon language to participate.

A future block-to-theme contract for fixed semantic UI symbols may be a
separate layer above icon references, but it seems useful not to foreclose it.
```

Posted without the plugin link, as above.

## The role split this rests on

Not part of the comment. Recorded because it is the frame the later Show and
tell entry should follow.

```
Core                 a safe icon resource registry, and the provider /
                     resolution groundwork above it
Theme                the icon language: font loading, glyph and axis choice,
                     visual state
Block plugin         behaviour, semantic state, the accessible name, markup
Site administrator   uploadable and selectable icon assets — a separate,
                     later problem
```

### How the layers resolve against each other

Settled in conversation, 2026-09-06. Deliberately **not** in the #82229 comment:
it belongs to the layer that comment declines to design, and raising it there
would undo the narrowing.

The precedence does not need inventing — WordPress already runs this cascade for
design data, and matching it is a stronger argument upstream than arguing it is
sensible. Read from the running 7.1:

```
WP_Theme_JSON::VALID_ORIGINS      default < blocks < theme < custom
resolver merge order              core -> blocks -> theme -> user
```

which is `core < plugin < theme < user/admin` under different names.

Two things about icons do not map onto it cleanly.

**A block's own bundled SVG is the floor, not a bid.** There are two plugin
roles here that theme.json's single `blocks` origin does not separate: the
plugin whose block *needs* a symbol, and an icon-pack plugin that *offers*
representations. If both sit on the same rung, an icon pack can silently
restyle a control whose meaning rides on the glyph. So the block's bundled icons
are what happens when nobody answers, underneath the cascade rather than in it:

```
floor        the block's own icons, used only when nothing answers
cascade      core baseline  <  icon-pack plugin  <  theme  <  site admin
```

**Within one origin, the narrower mapping wins.** A theme declaring a global
icon language should lose to an explicit mapping for `axismundi/theme-switcher`'s
`light`, the way a per-block `settings.blocks[...]` beats a global one in
theme.json and the way specificity settles it in CSS. Origin first, scope
second.

The admin rung does not exist yet — uploadable, selectable icon assets are the
separate later problem named above. Today the live cascade is
`core < plugin < theme`, with that rung reserved rather than proposed.

## Measured background

Not in the comment; it belongs to the layer the comment declines to design.
Read from a running WordPress 7.1 in wp-env on 2026-09-05.

- The `core` collection registers 88 icons, none of them a sun, moon, brightness
  or contrast symbol. The nearest are `core/desktop`, `core/mobile`,
  `core/tablet`.
- Two filled/unfilled pairs exist in those 88: `star-empty` / `star-filled` /
  `star-half`, and `symbol` / `symbol-filled`.
- `wp_register_icon()` refuses a name that is already registered
  (`_doing_it_wrong`, returns `false`). `wp_unregister_icon()` then
  `wp_register_icon()` does replace the content, but globally and
  last-writer-wins: there is no way to scope a substitution to one block.
- Of the block PHP in `wp-includes/blocks`, one file calls `wp_get_icon()`:
  `icon.php`.

## Later, separately

Posted: [#82501](https://github.com/WordPress/gutenberg/discussions/82501),
Show and tell. Shows the block and what composing it out of five Core blocks'
habits taught me, and hands the two API-shaped questions to #82228 and #82229
rather than restating them. Drafted in `UPSTREAM-SHOW-AND-TELL.md`.
