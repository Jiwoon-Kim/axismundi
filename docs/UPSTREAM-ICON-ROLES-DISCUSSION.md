# Draft — reply to Gutenberg discussion #82229, comment 18243970

> Status: draft, not posted. Threaded reply under @meyshad's comment, which is
> what it refines. Measured against WordPress 7.1 in wp-env on 2026-09-05; the
> numbers were read from a running install.
>
> Earlier draft of this file proposed a separate discussion. It is not one:
> #82229 already owns "what does an icon reference resolve to", and @meyshad's
> reply already proposes the provider shape. This narrows one part of that
> model instead of restating it.

---

That separation is the right one, and `search_callback` as optional discovery
rather than registration is the part I would have got wrong.

One boundary in the stored shape I would draw differently, from having built a
block that hits it.

`provider` in the persisted reference makes the block choose the site's icon
language. For a block distributed on its own that is the wrong owner: it does
not know what the active theme uses, and whatever it picks becomes something a
theme has to override rather than something it can simply answer. A portable
block knows something narrower and more stable — *which symbol, in which state* —
and would be better persisting only that:

```ts
type IconRequirement = {
	name: string;               // meaning, block-scoped: "light", "dark", "auto"
	state?: string;             // "default" | "selected"
};
```

with the site binding requirement to provider, and `IconReference` remaining
exactly as you describe it for the case where an author *has* picked an icon —
`core/icon`, a Social Link, anything where choosing the picture is the point.
Those are two different authoring acts and I think they want two shapes.

## Why the state belongs in the declaration rather than in CSS

Your last boundary says variable-font state stays CSS/control state unless it is
a real named variant, and for fill and weight as decoration I agree. The case
that does not fit is a toggle, where filled-versus-outlined is not styling — it
is how the control says which option is active.

That distinction costs the two provider kinds very different amounts. A block
with three symbols in two states is three strings plus one axis to a font
provider, and up to six separate resources to an SVG provider. If the state is
not in the reference, the SVG provider has no way to be asked for the second
one; if it is, each provider can satisfy it the way its medium allows, which I
think is the same argument you are already making one level down.

## What is measurable today

I checked these against 7.1 rather than assuming, because they change how much
of this is theoretical:

- The `core` collection registers **88 icons**, and none of them is a sun, moon,
  brightness or contrast symbol. The nearest are `core/desktop`, `core/mobile`,
  `core/tablet`. A colour-scheme control has no `core/*` name to fall back to at
  all — so "register your own collection" is the common case, not the exception.
- There are **two** filled/unfilled pairs in those 88: `star-empty` /
  `star-filled` / `star-half`, and `symbol` / `symbol-filled`. Selected-versus-
  unselected has no systematic expression in the set yet.
- `wp_register_icon()` **refuses a name that is already registered**
  (`_doing_it_wrong`, returns `false`). `wp_unregister_icon()` followed by
  `wp_register_icon()` does replace the content — I confirmed that — but it is
  global and last-writer-wins. There is no way today for a theme to say "this
  symbol, in this block", only "this name, everywhere, and whoever ran last
  decides".
- Of the block PHP in `wp-includes/blocks`, exactly one file calls
  `wp_get_icon()`: `icon.php`.

The third one is why I think the binding layer matters more than it looks. Even
with providers, if the only substitution mechanism stays name-global, a theme
that wants its own light/dark symbols has to unregister something to get them.

## The case this came from

`axismundi/theme-switcher` is an Auto / Light / Dark control in the Block
Directory. It renders its symbols as ligature text against an icon font the
companion theme loads:

```html
<span class="material-symbols-outlined">light_mode</span>
```

Under that theme it is a sun. Under any other theme it is the literal string
`light_mode`, because nothing else loads the font. That is the only portability
defect left in the block — its colours, corner sizes and motion already read
`--md-sys-*` custom properties with baseline fallbacks, so a foreign theme
degrades those gracefully and breaks only here.

Worth saying plainly, though: a theme shipping an icon font that a block
consumes already *is* a theme-provided icon registry, without any Core API. What
is missing is not the ability — it is a way for the block to express what it
needs without naming that font, and for the theme to answer without unregistering
anything.
