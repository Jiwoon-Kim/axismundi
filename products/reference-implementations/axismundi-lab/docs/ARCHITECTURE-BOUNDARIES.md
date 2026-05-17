# Architecture Boundaries — Axismundi Charter

> Cross-cutting governance document. Establishes the four-layer architecture
> (baseline / module / theme interaction / plugin), the cascade-vs-intrusion
> rule for theme state, the bucket reclassification scheme, the theme-can /
> plugin-should split, the forbidden-ancestor list (in lockstep with
> `BEER-CSS-INTAKE.md`), the federation portability constraint, and the
> frontier-theme failure-mode policy.
>
> Every subsequent module audit (icon-system, popover, TOC, date/time,
> ActivityPub) refers back to this charter rather than re-deriving the same
> boundary decisions from scratch. Authored at v3.4.1.

## Why this exists

By the end of v3.4.0, four classes of upcoming work were all queued up:
icon system (cross-cutting), popover (chrome interaction + a11y), TOC
(post-template-adjacent), date/time picker (visual + data + locale), and
ActivityPub modules (federation + data). Each of those repeatedly asks the
same boundary questions:

```
Is this baseline?
Is this a lab module?
Is this a theme interaction?
Is this a plugin?
May this enter post_content or federated surfaces?
```

Rather than answer those questions inside every per-module audit, the
charter sets them once at the project level. Per-module audits then cite
the relevant clause instead of re-arguing it.

## §1 — Four layers

```
Baseline styleguide   = WordPress core block + M3 token/component direct mapping
Lab module            = Bounded extension surface for M3 patterns that core
                        blocks cannot fully represent
Theme interaction     = Thin runtime enhancement over existing markup,
                        without new content schema
Plugin                = Durable custom blocks, editor UI, pickers, registries,
                        data, protocol integrations
```

### Baseline styleguide

The visual + structural surface that any WordPress site running this theme
sees by default. WordPress core blocks (Paragraph, Heading, List, Image,
Gallery, Buttons, etc.) are styled with M3 design tokens. Anything that
needs a custom block, custom schema, or editor UI is **not** baseline.

The published `/styleguide/` directory mirrors the baseline. Lab modules
are not part of baseline.

### Lab module

A bounded experiment that lives under `lab/modules/<name>/`. Each module
has its own CSS, JS, pattern HTML, and audit documentation. Modules are
not yet promoted into the canonical styleguide; they are validation
surfaces. A module exists when an M3 component, interaction, or authoring
pattern cannot be fully represented by WordPress core blocks alone — but
can still be explored through a bounded lab surface before being either
promoted to baseline, dispatched to a theme interaction, or escalated to
a plugin.

### Theme interaction

A small JS+CSS runtime layer over baseline markup that adds visual or
state behavior without introducing new content schema or block types.
Ripple and the theme toggle are theme interactions: they decorate
existing buttons / chrome elements, do not change what gets stored in
post content, and degrade cleanly when the runtime fails.

### Plugin

Anything that needs to register a durable custom block, host editor UI
(toolbar items, sidebar inspectors, pickers), persist non-WordPress data
schema, or speak to external protocols (ActivityPub, REST, federation).
Plugins live under `products/distributables/plugins/<name>/` when they
exist.

## §2 — Theme state vs theme control

The most important rule in this charter. Stated as two clauses:

> **Theme state is global.**
> **Theme controls are chrome-only.**

In English:

> The selected theme mode must cascade into prose and post content
> through design tokens.
>
> The theme toggle control itself must remain in theme chrome
> (header, footer, navigation, settings surface) and must not be
> inserted into prose, post_content, comments, excerpts, or
> federated content.

한글:

> 테마 상태는 전역이고, 테마 컨트롤은 chrome 전용이다.
>
> 선택된 light/dark 상태는 color/surface/text/link/code/blockquote 등
> 디자인 토큰을 통해 prose와 post content 내부까지 자연스럽게
> 상속되어야 한다. 그러나 theme toggle 버튼/아이콘/상호작용 UI 자체는
> header/footer/navigation 같은 theme chrome에만 존재해야 하며,
> prose/post_content/comment/excerpt/federated content 안으로
> 삽입되어서는 안 된다.

### What "cascade into prose" means

```css
/* Allowed: theme state cascades via tokens */
[data-theme="dark"] {
  --md-sys-color-surface: #131313;
  --md-sys-color-on-surface: #e6e1e5;
  --md-sys-color-primary: #d0bcff;
}

.prose {
  color: var(--md-sys-color-on-surface);
  background: var(--md-sys-color-surface);
}

.prose a {
  color: var(--md-sys-color-primary);
}
```

### What "controls in prose" looks like (FORBIDDEN)

```html
<!-- FORBIDDEN -->
<article class="prose">
  <button class="ax-theme-toggle">...</button>
</article>
```

### Short form

```
Allowed cascade:        theme tokens → prose
Forbidden intrusion:    theme controls → prose
```

The same logic generalizes to other state systems Axismundi may add
later (display density, reading width, etc.): the *state* may cascade
through tokens into content; the *control* that changes the state
stays in chrome.

## §3 — Bucket reclassification

Block-component mapping uses seven buckets. Every component or interaction
in the project gets a bucket label.

| Bucket | Meaning |
|---|---|
| **A** — Core block direct | WordPress core block + token styling, no variation or pattern needed |
| **B** — Core block + style variation | Core block with a registered style variation that maps to an M3 pattern |
| **C** — Core block + pattern | Core block(s) composed into an M3 pattern via a block pattern |
| **D** — Theme interaction enhancement | Thin runtime layer over baseline markup; no new schema |
| **E** — Lab module / plugin candidate | Visual + interaction layer that may eventually promote to baseline, dispatch to theme interaction, or escalate to plugin — not yet decided |
| **F** — Plugin territory | Editor UI, picker, registry, custom block, data schema, protocol integration |
| **G** — Excluded / archive | Legacy or experimental surface that should not be carried forward |

### Working classification (as of v3.4.1)

| Item | Bucket | Rationale |
|---|---|---|
| Paragraph, Heading, List | A | Core block direct |
| Image, Quote, Code, Preformatted | A | Core block direct |
| Button | B + D | `core/button` + style variation + theme interaction (ripple) |
| Icon button | B + D | `core/button` variation + icon system runtime |
| Gallery | C + E | `core/gallery` direct; carousel candidate sits on top |
| Carousel | E | Lab module (v3.3.2); promotion / plugin escalation TBD |
| Ripple | D | Theme interaction; no schema change |
| Theme switch toggle | D | Theme chrome interaction |
| Search bar | A + D | `core/search` direct + lab-search-expansion enhancement |
| Search expansion module | D + E | Theme interaction candidate; lab module (v3.3.4) |
| Popover / menu | E (pending) | Lab module candidate (v3.4.3) |
| Text field visual spec | E (or F) | Frontend baseline weak; editor `TextControl` / `InputControl` mapping → plugin |
| Date / time picker | E + F | Lab visual + plugin for editor controls + locale data |
| Icon font system | D + F | Theme interaction (chrome glyphs) + plugin (picker, registry, axes controls) |
| SVG icon block / social icon | F | Plugin (custom block, social icon variation, sanitization) |
| TOC slot (template) | C + D | Theme can provide the slot; layout pattern |
| TOC generation (parsing, scrollspy, editor controls) | F | Plugin (content behavior, not theme styling) |
| ActivityPub actor / feed / post | F | Plugin (protocol integration) |
| HCT color panel | F | Plugin (editor UI) |
| Legacy social-CMS prototype | G | Archive — see `_archive/` |

### Per-module bucket field

Every per-module audit doc from v3.4.1 onward MUST include a `Bucket:`
field in its front matter or first heading. Example:

```
# Popover Audit — v3.4.3

> Bucket: E (lab module / plugin candidate)
> Charter: see lab/docs/ARCHITECTURE-BOUNDARIES.md §3
```

Existing audits (carousel, ripple, search-expansion) get the bucket field
appended retroactively when each is next revised.

## §4 — Theme can / Plugin should

### Theme can

- Style core blocks via `theme.json` and CSS.
- Register block patterns.
- Register block style variations.
- Define template parts (header, footer, sidebar, single, archive).
- Enqueue progressive interaction CSS/JS (ripple, theme toggle, search expansion).
- Provide slots / containers (e.g. `.ax-toc-slot`) for plugin-rendered content.
- Render baseline M3 glyph system via icon font.

### Plugin should

- Register durable custom blocks (those whose `name` is saved into
  post content and would migrate across themes).
- Host editor UI: toolbar items, sidebar inspectors, popovers, pickers,
  modals.
- Persist non-WordPress schema (icon registry, theme settings beyond
  `theme.json`, federation actor records).
- Parse content (heading extraction for TOC, link inventory, etc.).
- Integrate external protocols (ActivityPub, IndieWeb, REST APIs).

### Block registration nuance

WordPress allows blocks to be registered from theme `functions.php` —
the block name namespace can be the theme name. But block names are
stored in post content. If a block name later changes (e.g. when the
custom block is moved into a dedicated plugin), every saved post
becomes a migration concern.

Therefore: **a theme may temporarily register a custom block** for
experimentation, but **the durable owner of any custom block should
be a plugin**.

This is why `axismundi-pilot` (when it is built in v3.4.x) will register
zero custom blocks. Custom blocks are dispatched to
`axismundi-icons`, `axismundi-toc`, `axismundi-activitypub`, etc.

## §5 — Forbidden ancestor list

Lockstep with `BEER-CSS-INTAKE.md` §1. If this list changes, both
documents must be updated together.

```
.prose
.wp-block-post-content
.entry-content
[contenteditable]
```

### Rule

> Chrome interactions must not attach inside prose or editable content.

### What this means in practice

Theme interactions (D-bucket items: ripple, theme toggle, search
expansion, future popover triggers in chrome) must include a
`closest(<forbidden-list>)` bail-out in their event handlers, and
must not have CSS selectors that match descendants of the forbidden
ancestors.

Content-authored blocks (A/B/C/F-bucket items) MAY exist inside post
content provided they are portable and semantically valid. A
WordPress core button block inside a paragraph is fine; a theme-level
ripple-attaching event listener that fires on that button when the
user clicks inside long-form content is not.

### Exception

Theme state tokens (color, surface, link, code, blockquote) cascade
into prose and post content by design — see §2. The forbidden-ancestor
list applies to **interactive controls**, not to **state cascading
through CSS variables**.

## §6 — Federation portability

> If content will be serialized into ActivityPub, RSS, an excerpt, or any
> remote-client view, it must not depend on theme-only JS, ligature icon
> fonts, or private CSS class semantics.

### Implications

| Property | Federated content | Theme chrome / styleguide |
|---|---|---|
| Theme JS required to read content | NO | OK |
| Icon font ligature glyph in content | NO (use SVG or inline text fallback) | OK |
| Private `.ax-*` class for layout | NO (use semantic HTML) | OK |
| Carousel JS-driven slide UI | NO (use gallery fallback) | OK in theme chrome |
| Ripple animation | N/A (no buttons inside federated content) | OK |
| Material Symbols icon glyph | NO (font may not be available remotely) | OK |
| SVG icon as inline markup | OK (portable) | OK |

### Why icon font is forbidden in federated content

Material Symbols renders text strings (`"home"`, `"search"`) as
glyphs via a variable font. A federated consumer (Mastodon, etc.)
that does not load that font sees the literal word "home" in the
middle of a sentence. This is the same kind of regression that
caused the prose §12 icon-scope policy in v3.2.1 (`.prose
[class*="material-symbols"] { font-family: inherit !important }`)
— enforced at the prose layer to keep the font from leaking into
long-form content even when the markup accidentally includes it.

Federation makes this stricter: not just visually inheriting prose
font, but **omitting the icon font entirely** from any content path
that may be serialized to a remote consumer.

## §7 — Frontier-theme failure-mode policy

Axismundi is positioned as a frontier theme. Modern CSS features
(logical properties, `:has()`, container queries, color-mix, anchor
positioning where supported, etc.) are allowed. Progressive
enhancement is the preferred posture; carrying legacy weight to
support obsolete browsers is not.

### Allowed failure modes

- Visual enhancement missing (e.g. ripple animation does not appear
  because the browser does not support `@keyframes` on a
  pseudo-element, or `color-mix` is not supported).
- Optional motion absent (`prefers-reduced-motion: reduce` honored;
  also acceptable when the browser silently fails to animate).
- Decorative chrome simplified (e.g. icon font glyph falls back to
  the ligature string visible as text — undesirable but not
  catastrophic).
- Subtle layout regression (e.g. `:has()` not supported, suffix
  alignment fails — content still readable).

### Forbidden failure modes

- Content inaccessible (text not visible, image alt missing, form
  not submittable).
- Controls unusable (button does nothing, link does not navigate,
  form does not accept input).
- Layout destroys reading (content overlaps, gets clipped, becomes
  unreachable by scrolling).
- Focus lost (keyboard user cannot reach interactive elements).
- Federation breaks (post content cannot be syndicated).

### Short form

```
Allowed failure:        visual enhancement missing
Forbidden failure:      content inaccessible
                        controls unusable
                        layout destroys reading
                        focus lost
                        federation breaks
```

Every module audit's promotion criteria check (currently a five-row
table) implicitly tests for the forbidden failure modes. The two
lists are equivalent: "no forbidden failure" ↔ "passes five
criteria".

## §8 — Pattern documentation UX

A practical UX rule discovered during v3.3.2 carousel and confirmed
by the v3.4.0 restructure: the canonical styleguide
(`style-guide.html`) holds **final demos**; lab modules hold
**rationale, ontology, fallback, and QA**.

When a module pattern HTML duplicates the styleguide's component
section (as `lab-carousel-pattern.html` currently does), the
canonical styleguide should *link out* to the module rather than
trying to render the full rationale inline. Example shape:

```html
<!-- style-guide.html — final demo only -->
<section id="carousel">
  <h2>Carousel</h2>
  <p>Gallery-based carousel candidate. See lab module for ontology, fallback, and QA.</p>
  <a href="./modules/carousel/lab-carousel-pattern.html">
    Open Carousel Lab Module
  </a>
  <!-- final demo here -->
</section>
```

This keeps the styleguide focused on "what does this look like" and
moves "why does this exist, what are the tradeoffs, how do we know
it's safe" into the module. The two surfaces remain in sync via
the publish-mirror tooling.

This UX is a recommendation for v3.4.x + onward; existing modules
may be retrofitted incrementally.

## §9 — Living document policy

This charter is a living document. New clauses may be added when:

- A new layer surfaces (e.g. service worker / offline cache, edge
  rendering) that does not fit the current four layers.
- A new failure mode is discovered that needs a forbidden / allowed
  classification.
- A new ancestor or selector becomes a federation / a11y boundary.

Existing clauses change only with explicit version bump entries in
the change log below. Per-module audits cite charter clauses by
section number (`§2`, `§5`, etc.); when a clause moves, audits get
a search-and-replace pass.

## Change log

- **v3.4.1 — initial draft.** Four-layer model, theme-state-vs-control
  rule, seven-bucket reclassification scheme, theme-can/plugin-should
  split, forbidden-ancestor list (locked with `BEER-CSS-INTAKE.md`),
  federation portability rule, frontier-theme failure-mode policy,
  pattern-doc UX recommendation. No code changes — pure governance.
  Triggered by the cumulative weight of upcoming v3.4.x work (icon
  system, popover, TOC, date/time, ActivityPub) all asking the same
  layer-classification question.

Future revisions append entries here.
