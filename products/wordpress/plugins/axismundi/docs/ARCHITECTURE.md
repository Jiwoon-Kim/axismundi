# Axismundi Reader: the shape before the features

Status: **design and shell implementation for `0.1.0-alpha0.1`.** Written 2026-09-29.

This release is not a feature release. It is a shell, a design-system contract and a component
foundation, and it is finished when those three hold — not when a feed shows real posts.

Everything here about WordPress or Gutenberg was read from the Gutenberg repository on
2026-09-29 and is quoted rather than recalled. Where this document says "verified", the quote
is in the section.

## 1. Reader is the product, not a tool for building one

Gutenberg's Storybook and Playground exist to build an editor. Here the React application *is*
the product and the interaction surface. So the developer surface is not a separate site next
to the product; it is a hidden mode inside it, driving the same components, the same router,
the same tokens and the same state layer from different fixtures.

That difference is worth keeping in mind whenever this document borrows a pattern from
upstream: the pattern is borrowed, the tooling is not.

## 2. Two surfaces, separated at the route

Mastodon and Misskey both keep server administration apart from the social client, and for the
same reason: they answer to different people about different things.

```txt
User surface                      Admin surface
  Home                              Reader settings
  Following                         Federation / cache status
  Communities                       Moderation
  Notifications                     Queue / failures
  Search                            Diagnostics
  Profile                           Developer  (see §7)
  Thread / Object view              PWA / notification settings
```

In alpha0.1 the admin surface may be an empty shell. **What must be true now is that the two
route trees are owned separately**, because merging them later is a rewrite and splitting them
later is a migration.

The user surface is the front-end route **`/social/`**. It owns an M3 token and layout system and
does not enqueue WPDS or wp-admin styles. The admin surface stays in wp-admin and owns a separate
WPDS-based layout system. The two systems share React infrastructure and semantic component
contracts, not their token sources or page chrome.

The admin surface is a **Reader-owned full-screen console**. `add_menu_page()` remains its PHP
entry point and capability boundary, but it is not the lasting visual shell: settings,
diagnostics, moderation, and Developer need Reader navigation rather than the crowded global
wp-admin menu. The Reader console removes the left admin menu, footer, and default page offset
only on its own screen.

The Social Web app is the direct Core-compatible precedent. It removes notices and the admin
bar, adds WordPress's `is-fullscreen-mode` body class, then uses `@wordpress/boot`'s
`initSinglePage()` to mount its own React `app-layout` and sidebar. Reader follows that split:
fullscreen is an app decision, while `initSinglePage()` is only the router/mount bootstrap.
`init()` supplies Boot's own private fullscreen shell, whose internal layout has no stable
styling or replacement hooks, so it is not Reader's product boundary.

Reader deliberately differs on the two global admin channels: **keep the admin bar and keep
admin notices**. This is an operator console, so the bar remains the way back to WordPress-wide
work and notices remain visible until a specific one is proven incompatible with the app.
Fullscreen means Reader owns its workspace, not that it hides operational context.

`/social/` is already a Reader-owned document. It invokes WordPress's document hooks so the active
theme's M3 token and font context can load, but it never calls the active theme's header or footer.
Theme block, site-layout, navigation, and prose markup therefore have no place in the Reader
document.

## 3. Build order, and why it is not negotiable

```txt
1  Plugin bootstrap
2  User / Admin surfaces separated
3  React SPA shell
4  CSS cascade layer contract
5  Token baseline
6  Axismundi Theme M3 integration
7  Primitive components
8  Composition components
9  Static mock feed
10 Real data: Activities, Object Projections, Actors
11 PWA beyond the shell
```

Steps 4–6 come before 7 because a component written against the wrong token or outside the
layer contract has to be rewritten, not adjusted. Step 9 before 10 because the point of the
mock feed is to settle what shape a component consumes — activity envelope, actor, object,
viewer state — while changing it is still cheap.

## 4. CSS cascade layers

Upstream has settled this, and we follow it rather than inventing a parallel vocabulary.
`@wordpress/ui` declares (verified, `packages/ui/CONTRIBUTING.md`):

```css
@layer wp-ui {
	@layer utilities, components, compositions, overrides;
}
```

and tells consumers to declare their own order against it (verified, `packages/ui/README.md`):

```css
@layer wp-ui, example-app;
```

The README also says why it matters: the components "use CSS cascade layers when defining
their styles, which can conflict in some applications which apply styles on bare element
selectors." wp-admin is exactly such an application.

Reader's order, declared once, before anything else loads:

```css
@layer
	wp-ui,
	axismundi-reader.tokens,
	axismundi-reader.primitives,
	axismundi-reader.components,
	axismundi-reader.compositions,
	axismundi-reader.utilities,
	axismundi-reader.overrides;
```

**Unlayered CSS beats every layer.** That is the cascade's rule, not a quirk, and it is why
wp-admin's global stylesheet — which is unlayered — wins against anything we put in a layer.
Upstream's answer is §5. Ours is the same.

### The state rule that layers create

Verified, same file:

> Never reassign the same custom property within state selectors—layer precedence will
> override it.

So a component declares one property per state and switches between them:

```css
.button {
	--_ax-reader-button-bg: …;
	--_ax-reader-button-bg-hover: …;
	background-color: var( --_ax-reader-button-bg );

	&:hover {
		background-color: var( --_ax-reader-button-bg-hover );
	}
}
```

This is the kind of rule that is cheap to follow from the first component and expensive to
retrofit across thirty.

## 5. Surviving wp-admin's global CSS

`@wordpress/ui` reserves a naming tier for precisely this (verified):

- `--wp-ui-*` — public API, frozen
- `--_wp-ui-*` — private
- `--_gcd-*` — **defence bridges against wp-admin global CSS**

That third tier is the part the admin plan is most likely to miss. Bare-element rules in wp-admin
(`button`, `input`, `a`) are unlayered, so they beat our layers, and the fix is a bridge
property the component reads rather than a specificity war.

Reader's equivalent:

- `--ax-reader-*` — the semantic aliases other code may use
- `--_ax-reader-*` — private to a component
- `--_ax-reader-gcd-*` — bridges for wp-admin interference

**The Developer surface must be able to toggle wp-admin global CSS on and off when it is running
inside the admin surface** (§7), because that is the only way to see which of the two is holding
an admin component together. The public Reader does not inherit this problem.

## 6. Tokens: separate admin and frontend contracts

`@wordpress/ui` is a companion to `@wordpress/theme`, whose tokens load as (verified):

```js
import '@wordpress/theme/design-tokens.css';
```

and its components "ship built-in fallback values for all CSS custom properties". The public
route receives the active theme's M3 token stack through the normal frontend document and does not
enqueue WPDS. The admin route receives WPDS from wp-admin and does not enqueue the frontend theme
token stack. A component owns no cross-surface palette.

```txt
Admin: WPDS                       baseline appearance
        ↓
Admin Reader aliases              --ax-reader-*
        ↓
Admin React components            read only aliases

Frontend: Axismundi Theme M3      --md-ref-* / --md-sys-*
        ↓
Frontend Reader aliases           --ax-reader-*
        ↓
Frontend React components         read only aliases
```

Components never name a WordPress token or an M3 token. They name an alias, and the alias
carries the fallback chain:

```css
@layer axismundi-reader.tokens {
	.axismundi-reader[data-surface="admin"] {
		--ax-reader-surface: var( --wpds-color-background-surface-neutral );
		--ax-reader-on-surface: var( --wpds-color-foreground-content-neutral );
	}

	.axismundi-reader[data-surface="user"] {
		--ax-reader-surface: var( --md-sys-color-surface );
		--ax-reader-on-surface: var( --md-sys-color-on-surface );
	}
}
```

The Developer route (`p=/developer`) is the one deliberate bridge: it loads the active theme's
M3 token files for an isolated frontend preview inside the WPDS admin shell. Ordinary admin
screens never load those files.

`@wordpress/ui` "is still experimental. 'Experimental' means this is an early implementation
subject to drastic and breaking changes" (verified). So **Reader does not depend on the
package.** It borrows the layer pattern, the naming tiers and the token names as a fallback
contract. A `var()` naming a token that does not exist costs nothing; an import of a package
that changes shape costs a release.

The exact WordPress token names above were verified against `@wordpress/theme`'s
`design-tokens.css` before the first component.

### What the active theme contributes

`theme.json` has two different kinds of output, and Reader treats them differently.

```txt
Consume as inputs                    Do not inherit as presentation
----------------------------------   ---------------------------------------------
settings.color.palette               styles.color / typography / spacing
settings.spacing.spacingSizes        styles.elements (body, heading, link, button)
settings.typography.* and font faces styles.blocks
settings.layout content/wide sizes   theme style.css and block/component styles
M3 token styles, when published      templates, template parts, or block markup
```

The first column is a design vocabulary. Reader maps it into `--ax-reader-*` aliases; a
component never relies on a theme selector. The layout sizes are optional constraints for
Reader aliases, not a mandate to reproduce a theme's page frame. The second column is authored
presentation and belongs to the WordPress site, not the Reader application.

The Axismundi theme currently publishes M3 token files separately from its semantic
`style.css` and block styles. Make that separation a public theme contract: one registered
**design-token handle** may contain reference/system token CSS and font faces, but it must not
depend on the theme's element, component, or block handles. Reader can enqueue that handle when
available and otherwise finishes the chain at WPDS. It must not identify a theme by directory
name or reach into its asset paths.

The Reader route will eventually request only the global-style variable/preset output needed
for the first column, instead of running the ordinary theme front-end asset queue. Its own
reset starts at the Reader document and its components own every visual rule below that point.

## 7. The Developer surface

Not a separate tool. A mode of the product, gated by **both** an administrator capability and
an option, so it can be exercised on a real site without ever reaching a reader:

```txt
manage_options  AND  axismundi_reader_developer_tools = true
```

```txt
Developer
├─ Foundations   layout, typography, radius, elevation
├─ Tokens        the alias table: alias → source → resolved value → which source won
├─ Components    isolated units, by tier
├─ Patterns      empty states, errors, loading, destructive actions
├─ Scenarios     composed product states
└─ Environment   the runtime conditions behind a fixture
```

The distinction that earns its keep is **Components vs Scenarios**. A component view answers
"does this render in every state"; a scenario answers "does the product hold together" —
a mixed feed, a deep thread, a boosted object, a sensitive object, a tombstone, an offline
cached feed, a network failure, empty notifications, very long multilingual content.

A shared toolbar drives both:

```txt
Theme     WordPress baseline | M3 light | M3 dark
Density   compact | default | comfortable
Direction LTR | RTL
CSS       wp-admin global on | isolated
Viewport  mobile | tablet | desktop
```

`Direction` is there from the start so components are written with logical properties
(`margin-inline`, `inset-inline`, `border-start-start-radius`) rather than converted later.
`CSS` is the §5 check. `Theme` is the §6 check — if switching it changes nothing, the alias
layer is not wired.

`Environment` answers a question Diagnostics does not: what conditions rendered this result?
It reports the Reader, React and WordPress versions; active theme; detected WPDS and M3 tokens;
CSS layer support; display mode; online state; service-worker and manifest state; and REST
nonce/auth availability. The CSS control is admin-only; `/social/` has no wp-admin stylesheet
to toggle.

### Fixtures have one home

Each component owns its fixtures beside it, and both the Developer surface and any future
Storybook import the same ones. Two copies of a test case is two truths. Fixtures are their own
source file rather than a Storybook story being the source of truth:

```txt
button.fixtures.js
  ├── Reader Developer surface
  ├── future button.story.js
  └── visual regression test
```

`@wordpress/ui` colocates the same way (verified):

```txt
src/component-name/
├── index.ts
├── component-name.tsx
├── types.ts
├── style.module.css
├── stories/
│   ├── *.mdx
│   └── index.story.tsx
└── test/
    └── component-name.test.tsx
```

Reader follows the colocation principle. Whether `.mdx` or Storybook files are worth keeping is
a later question; the fixtures and implementation stay together either way.

## 8. Components, from the bottom

```txt
Primitive     Button, IconButton, Avatar, Spinner, VisuallyHidden
Composition   Tabs, Dialog, EmptyState
Reader        ActorIdentity, ObjectMeta, ObjectCard, ObjectCardHeader, ObjectCardBody,
              ReactionBar, InteractionBar, FeedItem, Feed
```

`Box`, `Surface`, `Stack`, and `Inline` remain CSS classes until repeated product evidence earns
them a component abstraction. Alpha0.1 should not invent a layout DSL before it has a product
layout to simplify.

Each carries a tier and a status (`Foundation` / `Primitive` / `Composition` / `Reader` /
`Experiment`, and `Stable` / `Experimental` / `Internal` / `Deprecated`), shown in the
Developer surface.

### Component metadata

Reader is a React plugin with a workspace package boundary, like Gutenberg rather than the
existing PHP-first plugins. `packages/component-library` is the reusable component library; the
Reader application is a separate consumer package. Inside that library, components follow
Gutenberg's `packages/block-library/src/paragraph` and `src/heading` pattern: each component is
a direct child of `src/`, with a colocated `component.json`. The file is analogous to
`block.json`, but it does **not** register anything with WordPress and does not duplicate the
TypeScript prop contract. Its job is to make the library catalogue machine-readable for the
Developer surface, fixtures, build checks, and a future Storybook adapter.

```txt
packages/
├── component-library/
│   ├── package.json
│   ├── schemas/component.schema.json
│   └── src/
│       ├── app-bar/
│       │   ├── component.json   library metadata
│       │   ├── index.js         public entry: metadata, component, init
│       │   ├── app-bar.js       implementation
│       │   ├── style.css        component-owned CSS layer
│       │   ├── fixtures.js      shared visual states
│       │   └── test/
│       ├── avatar/
│       ├── button/
│       └── object-card/
└── reader-app/
    └── src/                     routes, application composition, bootstrap
```

```json
{
  "$schema": "../../schemas/component.schema.json",
  "apiVersion": 1,
  "name": "axismundi-reader/button",
  "title": "Button",
  "description": "A command control with accessible pressed, disabled, and busy states.",
  "tier": "primitive",
  "status": "experimental",
  "surfaces": [ "user", "admin", "developer" ],
  "entry": "./button.js",
  "style": "./button.css",
  "fixtures": "./button.fixtures.js"
}
```

`name`, `tier`, `status`, and `surfaces` form the library and Developer catalogue. `entry`,
`style`, and `fixtures` are build-time paths relative to the manifest. Props, layout policy, and
runtime data remain in the component source so there is one type system and no JSON shadow API.
The first component adds the JSON Schema and a manifest validator; alpha0.1 does not manufacture
metadata for an empty shell.

The existing Gutenberg blocks — `actor-avatar`, `actor-name`, `actor-handle`, `object-type`,
`object-date`, `object-card-header`, `object-card-body`, `reaction-bar`, `interaction` — are
**reference for the React API, not shared implementation.** They answer what a card needs to
show and which pieces are separable. They do not answer how React should hold that state, and
the block versions escape their own output at a sink Reader will not have.

## 9. What `0.1.0-alpha0.1` must and must not do

Must:

- the plugin activates
- a user route and an admin route exist, owned separately
- the React application mounts on its own
- the cascade layer order is declared
- WordPress design tokens give a baseline appearance
- Axismundi Theme M3 tokens override through Reader aliases, demonstrably
- no dependency on block CSS
- no dependency on the Object Projections renderer
- the primitive set exists, and the basic compositions
- a static mock feed renders entirely from React
- the shell is responsive
- light/dark switching proves the token architecture rather than asserting it
- a PWA manifest and app shell exist

Must not, yet:

- read the Activity ledger
- fetch an Object
- perform any mutation
- send a push
- cache data offline

The mock feed is the point of the release. It settles the shape a component consumes —
activity envelope, actor, object, viewer state — while that is still a cheap thing to change.

## 10. PWA

Manifest, icon, installable shell, client-side router and root mounting are alpha0.1 work. An
offline fallback requires a minimal service worker, so it remains optional until the shell has
earned that requirement. Activity/object/actor cache strategies, push, background sync and
offline data all depend on data APIs that do not exist yet, and a schema chosen now is a schema
to migrate later.

## Related

- `axismundi-activities/docs/C2S.md` — how a client will eventually submit, when Reader writes
- `axismundi-object-projections/docs/LOCAL-OBJECTS.md` — attribution and body, on the server side
- Reader reads Object Projections' remote cache, not product CPTs. That is what lets one card
  render a Lemmy topic and a Mastodon note whether or not Forum or Note is installed.
