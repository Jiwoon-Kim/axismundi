# Icon Font Policy — v3.4.2

> Bucket: D (theme interaction)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §4 (theme can render M3 glyph system), §5 (forbidden ancestors), §6 (federation portability)

> The contract for Material Symbols icon font usage in Axismundi. The
> font is theme-chrome glyph machinery; this document specifies the
> hardening rules, the variable axes contract, the scope enforcement,
> and the accessible-name pattern. Any chrome surface that wants to
> render a Material Symbols glyph must follow this contract; anything
> outside the contract is a forbidden use.

## Scope statement

Material Symbols icon font is the **M3 chrome glyph engine**. It is
loaded into the theme via `@font-face` (`lab/stylesheets/fonts.css`)
and styled by `lab/stylesheets/icons.css`. It is intended for:

- icon buttons (`.ax-icon-button`)
- icons inside text buttons (`.ax-button`)
- FABs and FAB menus (`.ax-fab`, `.ax-fab-menu`)
- toolbar items (`.ax-toolbar`)
- navigation chrome (header / sidebar / footer / nav items)
- search bar leading + trailing slots (`.search-bar__leading-icon`, `.search-bar__trailing`)
- list leading + trailing icons (`.ax-list`)
- chips (`.chip`)
- status / state glyphs in cards / banners (chrome only)
- the theme switcher chip group
- the styleguide's own chrome (sg-* utility classes)

It is **forbidden** in:

- `.prose` (long-form authored content)
- `.wp-block-post-content` (rendered post body, classic + block themes)
- `.entry-content` (classic-theme post body)
- `[contenteditable]` (block editor canvas, comment composers, etc.)
- Anything that will be serialized into ActivityPub, RSS, excerpts, or
  any remote-client view (charter §6 — federated consumers may not
  load the font and will see literal text like "home", "search").

The scope enforcement is implemented in two places already:

1. **`lab/stylesheets/prose.css §12`** — `.prose [class*="material-symbols"] { font-family: inherit !important; ... }` — forces any icon glyph that *accidentally* lands inside prose to render as the underlying ligature text (which is also visible-as-text, signaling the leak rather than masking it).
2. **Future plugin** — when `axismundi-icons` ships its picker, the picker MUST refuse to insert an icon font glyph into post content. SVG track is what gets inserted for content; see `SVG-ICON-POLICY.md`.

## Hardening rules

The icons.css file's comments already reference these rules. The CSS
implementations are partially present (e.g. `font-feature-settings`)
but the protective hardening (`user-select`, `-webkit-user-drag`,
`pointer-events`, `draggable`) is **not yet codified** in icons.css —
it currently lives only in this policy document and in the markup
patterns this document specifies. Adding the CSS rules is a
v3.4.3 task (icon button runtime prototype).

### Required markup pattern

```html
<span
  class="material-symbols-rounded notranslate"
  translate="no"
  aria-hidden="true"
  draggable="false"
>
  search
</span>
```

Five mandatory attributes / classes:

| Attribute | Purpose |
|---|---|
| `class="material-symbols-rounded"` | Engages the icon font's CSS class (defined in `icons.css §1`) |
| `class="notranslate"` | Tells page-translation engines (Google Translate browser feature, etc.) to skip this element. Without it, "search" / "home" / "menu" gets translated into other languages, breaking the glyph mapping |
| `translate="no"` | HTML5 native equivalent of `notranslate`. Both belong on the element for maximum coverage |
| `aria-hidden="true"` | The icon does not have a meaningful name on its own; the parent control (button / link / etc.) owns the accessible name |
| `draggable="false"` | Prevents the user from accidentally dragging the glyph as text. Without it, a long-press on touch / a click-and-drag on desktop can detach the glyph as a draggable text fragment |

### Required CSS hardening

To be added to `lab/stylesheets/icons.css` in v3.4.3 (currently
documented here as the contract; CSS implementation pending):

```css
.material-symbols-rounded {
  /* Existing rules in icons.css §1: font-family, font-size, font-feature-settings, etc. */

  /* HARDENING — v3.4.3 will add: */
  user-select: none;
  -webkit-user-select: none;
  -webkit-user-drag: none;
  pointer-events: none;
}
```

### Why each hardening rule

- **`user-select: none`** — Without this, the user can select the glyph's underlying text ("search") via double-click or drag-select, then copy and paste it elsewhere as literal text. Particularly bad inside a long-form context where surrounding paragraphs ARE selectable: the icon would look selectable but its content would be a glyph keyword, not the visible glyph.
- **`-webkit-user-drag: none`** — Safari / WebKit-specific. Disables HTML5 native dragging of the glyph element. Even with `user-select: none`, a Mac trackpad force-touch or a tap-and-hold on iOS Safari can initiate a drag with the glyph text as the data payload. Setting this property explicitly disables that path.
- **`pointer-events: none`** — The glyph is not the click target. The parent control (`<button class="ax-icon-button">`, `<a class="ax-button">`, etc.) is. Setting `pointer-events: none` on the glyph ensures that pointer events pass through to the parent regardless of CSS stacking, and prevents the glyph from intercepting focus-visible / hover handlers that the parent owns.
- **`draggable="false"` (HTML attribute)** — Complements `-webkit-user-drag: none`. Belt and suspenders.

### Accessible-name pattern

The icon glyph itself is `aria-hidden`. The accessible name belongs to
the parent control:

```html
<!-- Icon button with named action -->
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="검색">
  <span
    class="material-symbols-rounded notranslate"
    translate="no"
    aria-hidden="true"
    draggable="false"
  >
    search
  </span>
</button>

<!-- Text button with leading icon -->
<button class="ax-button is-filled has-state-layer t-label-large" type="button">
  <span
    class="material-symbols-rounded notranslate"
    translate="no"
    aria-hidden="true"
    draggable="false"
  >
    download
  </span>
  <span class="ax-button__label">다운로드</span>
</button>
```

Exception — when the icon is itself the primary semantic content of a
status indicator (not a control), provide a parallel text label via a
screen-reader-only span:

```html
<!-- Status banner — icon glyph carries information -->
<span
  class="material-symbols-rounded notranslate"
  translate="no"
  aria-hidden="true"
  draggable="false"
>
  warning
</span>
<span class="screen-reader-text">주의</span>
```

This pattern is rare in chrome and most authors should default to the
"parent owns accessible name" pattern above.

## Variable axes contract

Material Symbols is a variable font with four axes. Axismundi already
maps these to CSS custom properties in `lab/stylesheets/icons.css §2`:

| Axis | CSS custom property | Default | Range |
|---|---|---|---|
| FILL | `--md-icon-fill` | `0` | `0` – `1` (outline → filled) |
| weight (wght) | `--md-icon-weight` | `400` | `100` – `700` |
| GRAD | `--md-grade` (shared with text!) | `0` | `-50` – `200` |
| optical size (opsz) | `--md-icon-opsz` | `24` | `20` – `48` |

**Important nuance — GRAD is shared with text.** v3.2.1 introduced
`--md-grade` as a **shared** custom property between text typography
(Roboto Flex GRAD axis) and icon font (Material Symbols GRAD axis), so
that a dark-mode `--md-grade: -25` adjustment applies to both icons and
body type in lockstep. This is the v3.2.x "GRAD sync" referenced in
project memory. Plugin authors who add axis controls in the editor MUST
NOT silently override `--md-grade` at the block level — it is theme-
scope, not block-scope.

The other three axes (`--md-icon-fill`, `--md-icon-weight`, `--md-icon-opsz`)
are block-scoped — a plugin sidebar inspector can override them
per-block without disturbing text typography.

### Axis values: M3 spec guidance

- **FILL 0** (default, outlined glyph) — appropriate for resting / inactive states.
- **FILL 1** (filled glyph) — appropriate for active / selected states. Toggle on hover / pressed for the M3 "expressive" motion (icons.css §6 has the foundation rule for this).
- **wght 400** (default) — matches the default body text weight; icons read as visually balanced with surrounding type.
- **wght 700** — for icons that need to read as "heavy" / "bold" / "primary action" indication.
- **GRAD** is automatic via the shared property; do not set per-block.
- **opsz** should match the icon's rendered pixel size — `opsz: 20` for 20px icons, `opsz: 24` for 24px (default), `opsz: 40` for 40px display-size, `opsz: 48` for FAB / hero glyphs. This is the "optical size" axis: the glyph's stroke ratios change to remain visually balanced at different rendered sizes.

## Styles: Rounded only ships with theme

Material Symbols comes in three styles: Rounded, Outlined, Sharp.
**Axismundi theme ships only Rounded** because:

1. Rounded matches the M3 baseline visual identity (M3 design language strongly favors rounded glyphs to harmonize with rounded corner-radius tokens).
2. Shipping all three styles triples the font payload for content that only consumes one style in practice.
3. Plugins (`axismundi-icons`) can add Outlined and Sharp as opt-in via theme.json or via plugin-injected `@font-face` declarations.

If a future site needs Outlined or Sharp, the plugin extraction is the
correct surface — not a theme-side addition that all sites pay the
download cost for.

## Failure modes

Per charter §7 frontier-theme policy:

| Failure mode | Classification |
|---|---|
| Material Symbols font fails to load (slow network, font CDN down) | **Allowed** (visual enhancement missing) — see fallback policy below |
| User has `Reduce motion` enabled and the icon's parent component (e.g. icon button) animates | **Allowed** — parent's reduced-motion handling, not the icon's concern |
| User's browser does not support `font-variation-settings` (very old browser) | **Allowed** — fallback to default axis values renders an acceptable static glyph |
| Translation engine translates "search" to other language and breaks glyph mapping | **Forbidden** — `notranslate` + `translate="no"` prevents this |
| User drags the glyph out as text fragment | **Forbidden** — `draggable="false"` + `-webkit-user-drag: none` prevents this |
| User selects the glyph text via double-click | **Forbidden** — `user-select: none` prevents this |
| Icon glyph appears inside `.prose` as literal "search" text after translation | **Forbidden** — prose.css §12 catches this server-side; hardening prevents it client-side |
| Screen reader announces "search" as part of the button's accessible name | **Forbidden** — `aria-hidden="true"` prevents this; parent's `aria-label` is the canonical name |

## Fallback policy

When Material Symbols fails to load, the underlying ligature text
("search", "home", "menu") renders in the surrounding default font.
Without a fallback the user would see the literal word inside a
button-shaped surface — readable but not a glyph.

Recommended fallback for high-traffic chrome (theme switcher, primary
nav, header search):

```html
<!-- Chrome glyph with inline-SVG fallback for slow-network resilience -->
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="검색">
  <span
    class="material-symbols-rounded notranslate"
    translate="no"
    aria-hidden="true"
    draggable="false"
  >
    search
    <!-- Optional: inline SVG fallback hidden by CSS when the font has loaded.
         Visible only if @font-face fails (CSS rule omitted here for brevity). -->
  </span>
</button>
```

This is **optional**, not required. Per charter §7, visual enhancement
missing is an allowed failure mode. Most chrome glyphs can simply
render as ligature text on font-failure and remain functional (the
button still works; only the icon's visual is degraded). Inline-SVG
fallback is reserved for chrome surfaces where the icon's absence
would make the control's purpose unrecoverable.

## Cross-references

- Existing infrastructure (theme-shipped CSS): `lab/stylesheets/icons.css`, `lab/stylesheets/fonts.css §Material Symbols Rounded`
- Forbidden ancestor list (locked): `lab/docs/ARCHITECTURE-BOUNDARIES.md §5`, `lab/docs/BEER-CSS-INTAKE.md §1`
- Federation portability rule: `lab/docs/ARCHITECTURE-BOUNDARIES.md §6`
- Prose-side scope enforcement: `lab/stylesheets/prose.css §12 Icon font scope policy`
- Existing icon-scope doctrine document: `atlas/material/icon-font-scope-policy.md` (pre-charter; will be cross-referenced after v3.4.2 freeze)
- Plugin track: `SVG-ICON-POLICY.md` (sibling)
- Picker UX: `ICON-PICKER-UX.md` (sibling)
- Component inventory: `INLINE-SVG-INVENTORY.md` (sibling)

## Change log

- **v3.4.2 — initial draft.** Hardening rules codified (5 markup
  attributes + 4 CSS rules). Variable-axes contract documented
  including the v3.2.1 GRAD-shared-with-text nuance. Rounded-only
  shipping decision recorded. Failure modes classified per charter
  §7. Fallback policy specified as optional. Implementation of the
  4 CSS hardening rules deferred to v3.4.3 (icon button runtime
  prototype), where they become visually testable on a real component.
