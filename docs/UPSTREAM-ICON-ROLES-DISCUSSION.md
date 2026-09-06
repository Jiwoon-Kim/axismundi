# Draft — Gutenberg Discussion: who owns a block's icons

> Status: draft, not posted. Measured against WordPress 7.1 in wp-env on
> 2026-09-05; every number below was read from a running install, not from
> documentation.

---

## Which layer owns what, when a block needs an icon?

WordPress 7.1 made the Icons API public: `wp_register_icon_collection()`,
`wp_register_icon()`, `wp_get_icon()`, and REST controllers for both. That
settles storage and lookup — *where does the SVG for this name live*.

It does not settle the question a block author actually faces, which is *who
decides what this symbol looks like on this site*. Working on a third-party
block, I keep hitting that question from three sides at once, and I would like
to agree on the division of responsibility before proposing an API for it.

### The case

`axismundi/theme-switcher` is a colour-scheme control: Auto, Light, Dark, as a
button group or a single cycling button. It needs three symbols, each in two
visual states — the chosen one is filled, the others are not.

Today it renders them as ligature text against an icon font the theme provides:

```html
<span class="material-symbols-outlined">light_mode</span>
```

Under the theme it ships with, that is a sun. Under any other theme, it is the
literal word `light_mode`, because nothing else on the site loads that font.
That is the whole portability defect, and it is the only one left in the block:
its colours, corner sizes and motion already read `--md-sys-*` custom
properties with baseline fallbacks, so it survives a foreign theme everywhere
except here.

### What I measured

**Core's set has no semantic coverage for this.** The `core` collection
registers 88 icons. None of them is a sun, a moon, a brightness or a contrast
symbol; the nearest are `core/desktop`, `core/mobile`, `core/tablet`. So there
is no `core/*` name to fall back to, and a block needing these must ship its
own SVGs.

**Core has almost no filled/unfilled pairs.** Two, in 88:
`star-empty` / `star-filled` / `star-half`, and `symbol` / `symbol-filled`.
A selected-versus-unselected distinction has no systematic expression in the
set, which matters because that distinction is not decoration — it is how a
toggle says which option is active.

**A theme cannot substitute an icon.** `wp_register_icon()` refuses a name that
is already registered (`_doing_it_wrong`, returns `false`). The only path that
works today is `wp_unregister_icon()` followed by `wp_register_icon()`, which I
confirmed does replace the content. That is a global, destructive mutation with
last-writer-wins semantics and no scope: a theme cannot say "this symbol, in
this block" — only "this name, everywhere, and whoever runs last decides".

**Core blocks do not route through the registry either.** Of the block PHP
files in `wp-includes/blocks`, exactly one calls `wp_get_icon()`: `icon.php`.
Everything else still carries inline SVG. So the registry is currently a
resource store that almost nothing reads.

### The division I think we are missing

Storage is solved. Representation is not, and it seems to me it belongs to
three different parties:

**Core** owns the resource registry and a baseline set — names, SVG, REST, the
`core` namespace. It already does this.

**A block** should own the *requirement*: "I need a symbol meaning `light`, and
it has an unselected and a selected state." That is knowledge only the block
has, it is stable across themes, and it is not a picture.

**A theme or plugin** should own the *representation*: "on this site, `light`
in that block is Material Symbols `light_mode`, filled at `FILL 1`." That is
knowledge only the site has, and today there is nowhere to put it.

The block currently has to own both, which is why it ends up knowing a font
family name — and why it breaks the moment that font is absent.

Two properties of that split are worth stating, because they are what make it
more than renaming:

*A requirement is not an SVG name.* If a block declares `fallback: core/sun`,
it has still chosen a picture; a theme wanting its own sun has to fight it. The
declaration that survives is the meaning plus the states, with a fallback only
as the last resort.

*Providers are not interchangeable in cost.* An icon font expresses this
block's six visual states as three strings plus a variable axis. An SVG
provider needs up to six separate resources. Any resolution layer has to let a
provider satisfy a state the way its medium allows, rather than assuming one
resource per state.

### Strawman, only to make the shape discussable

Not a proposal. Names are placeholders; the point is the three parties.

```php
// The block says what it needs. No font, no SVG, no theme.
wp_register_ui_icon_requirements(
	'axismundi/theme-switcher',
	array(
		'light' => array( 'states' => array( 'default', 'selected' ) ),
		'dark'  => array( 'states' => array( 'default', 'selected' ) ),
		'auto'  => array( 'states' => array( 'default', 'selected' ) ),
	)
);
```

```php
// The theme says how they look here, scoped to the block, destroying nothing.
wp_register_ui_icon_provider(
	'axismundi-material-symbols',
	array(
		'format'      => 'font',
		'font_family' => 'Material Symbols Outlined',
		'provides'    => array(
			'axismundi/theme-switcher' => array(
				'light' => array(
					'content' => 'light_mode',
					'states'  => array(
						'default'  => array( 'FILL' => 0 ),
						'selected' => array( 'FILL' => 1 ),
					),
				),
			),
		),
	)
);
```

```php
// The block renders without knowing which of the two answered.
echo wp_get_ui_icon( 'axismundi/theme-switcher', 'light', array( 'state' => 'selected' ) );
```

Under a theme that provides nothing, the same call falls back to the registry,
or to the block's own collection where core has no name for the meaning — which,
as measured above, is the common case rather than the exception.

### What I would like to settle first

1. Is the three-way split right — block declares meaning, site decides
   representation, core stores resources and resolves between them?
2. Should a requirement live in `block.json`, in a server registry, or in
   `block.json` as a convenience that registers into the server one? Components
   have no `block.json`, and the editor needs the same answer the renderer gets.
3. Should a theme's mapping be PHP, `theme.json`, or both?
4. Is `default` / `selected` the right minimum, given that hover and pressed are
   normally state layers rather than different glyphs?
5. Should a provider be allowed to be something other than SVG at all, or should
   icon fonts stay outside Core's model?
6. Does the same model serve `@wordpress/components`, or is this blocks only?

Related: the same absence of stable semantic identities is why core blocks
cannot have their own icons themed today — the Navigation submenu arrow and the
image lightbox are still inline SVG, and only `core/icon` reads the registry.
