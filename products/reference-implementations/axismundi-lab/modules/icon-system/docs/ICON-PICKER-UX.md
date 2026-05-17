# Icon Picker UX — v3.4.2

> Bucket: F (plugin territory, with D-bucket consumption surface)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §4 (plugin should host editor UI)

> UX sketch for a future block-editor icon picker. The picker itself
> lives in the `axismundi-icons` plugin (not yet extracted); this
> document specifies the surface area and the interaction model so
> the plugin can be built against a stable contract when the time
> comes. The theme provides only the rendering of the chosen icon
> (icon font for chrome, SVG for content) — never the picker UI.

## Scope statement

The picker is plugin-side editor UI. It does three things:

1. Let the author **search and select** a Material Symbols glyph by name, category, or visual browse.
2. Let the author **adjust variable axes** (FILL, weight, optical size) per selection. GRAD is theme-scope (charter §2 — theme state) and is NOT exposed on the picker.
3. Let the author **insert an SVG icon** as an alternative path (custom upload, paste, or pick from a curated SVG library) when an icon font glyph is not appropriate (content blocks, custom social icons, brand icons).

Anything beyond these three responsibilities is out of scope for the
picker plugin — see "Plugin territory boundary" below.

## Surface model

The Figma Material Symbols plugin is the closest design-tool analogue.
Its key insight is that **axis adjustment is a separate sub-surface
from icon selection**: you pick the glyph first, then you tune its
variable axes. WordPress block editor naturally has two corresponding
surfaces:

```
Block Toolbar       →    Icon selection (quick insert, toggle, replace)
Sidebar Inspector   →    Variable axis controls (FILL, wght, opsz, label)
```

### Block Toolbar (`ToolbarItem`)

The toolbar is the surface for quick, in-flow operations. Specifically:

- **Icon button** (in the block toolbar) — opens the icon picker popover.
- **Quick toggle** — if the block has an "icon" attribute, a toolbar button toggles it on / off. Off means the icon is removed; on with no icon yet means the picker opens.
- **Replace** — when an icon is already set, clicking the toolbar button opens the picker pre-filtered to the current icon's category.

What does NOT go in the toolbar:
- Variable axis sliders (sidebar territory)
- The full icon grid browser (popover triggered FROM the toolbar, not embedded in it)
- Label / aria-label editing (sidebar territory)

### Sidebar Inspector

The sidebar is the surface for precise, multi-property adjustment:

- **Icon name** (read-only display + "Change" button that reopens the picker; same as the toolbar Replace action)
- **Style** (Rounded / Outlined / Sharp, when plugin loads Outlined / Sharp; defaults to Rounded since theme ships only Rounded)
- **FILL** (toggle 0 / 1, or a slider 0–1 for animated states)
- **wght** (slider 100–700, snap to 100, 300, 400, 500, 700 by default; freeform for power users)
- **opsz** (slider 20 / 24 / 40 / 48, snap; or "follow size" automatic mode)
- **Label** (the accessible name on the parent control, not on the glyph itself — see `ICON-FONT-POLICY.md`)
- **Type override** (Material Symbols / SVG / inline string — see "Type override" section below)

### Icon Picker popover (triggered from toolbar)

The picker popover is the third surface — it pops up over the canvas
when the author clicks the toolbar's icon button. Structure:

```
+-------------------------------------------+
| Search:  [ ............................] |
+-------------------------------------------+
| Category: [Common] [Actions] [Comm.] [..] |
+-------------------------------------------+
| Icons (24px tile grid, 6 columns):        |
|   [home] [search] [menu] [close] [...]    |
|   [edit] [delete] [save] [share] [...]    |
|   ...                                     |
+-------------------------------------------+
| Recent: home, search, settings, ...       |
+-------------------------------------------+
| Tab: Material Symbols  |  Custom SVG      |
+-------------------------------------------+
```

The tabs at the bottom switch between the two engines:

- **Material Symbols tab** — the default. Search by name, browse by category, recent picks.
- **Custom SVG tab** — paste SVG markup, upload .svg file, or pick from a curated registry of brand / social icons. Sanitization runs on paste / upload (see `SVG-ICON-POLICY.md §Sanitization baseline`).

## Icon registry shape

The registry is plugin-internal data. Sketched here so the picker UX
above has a known input shape:

```jsonc
{
  "name": "search",
  "label": "Search",
  "category": "common-actions",
  "tags": ["find", "lookup", "query", "magnifier", "검색", "찾기"],
  "styles": ["rounded", "outlined", "sharp"],
  "defaultAxes": {
    "FILL": 0,
    "wght": 400,
    "GRAD": 0,
    "opsz": 24
  }
}
```

Each registry entry is one icon. Tags are multilingual where possible
(Korean tags listed alongside English help Korean-first authors find
icons by their native vocabulary). Categories follow the Google Fonts
Material Symbols categorization (Common, Action, Alert, AV, Communication,
Content, Device, Editor, File, Hardware, Home, Image, Maps, Navigation,
Notification, Places, Social, Toggle).

The registry is plugin-shipped data. Loading is async (so the picker
opens immediately while the registry resolves in the background) and
the registry can be split per-category to keep the initial payload
small.

## Theme state vs picker state

Per charter §2: theme state is global, theme controls are chrome-only.

- **`--md-grade`** is theme state (shared between text and icon font, set by the theme switcher in chrome) → NOT exposed in the picker
- **`--md-icon-fill`**, **`--md-icon-weight`**, **`--md-icon-opsz`** are block-scope state (chosen per insertion) → exposed in the picker sidebar

A picker that exposes `--md-grade` per block would violate charter §2
because the icon's GRAD would drift away from the surrounding text's
GRAD as the user darkens / lightens the theme. The cross-axis sync
is the whole point of the v3.2.1 shared-`--md-grade` design.

## Type override (Material Symbols / SVG / inline)

A power-user case: the author wants to use a custom SVG for one
specific block even though the design system defaults to icon font.
The picker's sidebar has a single "type" radio:

| Type | Storage | Render |
|---|---|---|
| **Material Symbols** | Block attribute stores the icon name + axis overrides | Frontend renders `<span class="material-symbols-rounded">name</span>` with `style="--md-icon-fill: ...; --md-icon-weight: ...;"` |
| **Custom SVG** | Block attribute stores the sanitized SVG markup | Frontend renders the SVG inline. No axis controls (SVG doesn't have variable axes). |
| **Inline string** | Block attribute stores a short text string | Frontend renders the string inside the icon slot (fallback path; rarely needed). |

The "Material Symbols" type is the default. The "Custom SVG" type is
the path that enables `SVG-ICON-POLICY.md` use cases (Social Icons,
brand glyphs, content-portable icons).

## Plugin territory boundary

What the picker plugin does:

- Renders the picker UI (toolbar item, sidebar inspector, popover).
- Maintains the icon registry.
- Provides the "Custom SVG" upload / paste / sanitize pipeline.
- Stores icon choices as block attributes.
- Provides PHP-side rendering for the chosen icons (server-rendered blocks).
- Optionally adds Outlined / Sharp styles via additional `@font-face` declarations.

What the picker plugin does NOT do:

- Render chrome glyphs (theme territory — `lab/stylesheets/icons.css`).
- Define the variable-axis defaults (theme territory — already defined in `icons.css §2`).
- Implement the theme-state cascade (theme territory — `--md-grade` is set by the theme switcher in chrome).
- Federation transformation (each block decides if it serializes its icon as SVG fallback for ActivityPub; the picker just stores the choice).
- Custom block registration outside the icon picker (other plugins register their own blocks; icon picker only provides the icon-attribute pattern for them to consume).

## Federation behavior

For content blocks that store an icon attribute and may be syndicated
(post body, comment widget, etc.), the icon attribute's storage and
the icon's runtime rendering are decoupled. The author picks
"Material Symbols: home". On the frontend, this renders as the icon
font glyph for the local visitor. For federated consumers, the
block's PHP rendering MUST also serialize a fallback path — either:

- Inline SVG version of the glyph (using a server-side SVG library that maps Material Symbols names to SVG paths), OR
- Plain text fallback (the icon's `label` from the registry, e.g. "Home"), OR
- Skip the icon entirely (for purely decorative content where the surrounding text is sufficient)

The choice is per-block-type — the picker plugin documents the
options; each consuming block makes its own federation policy
choice. This is charter §6 (federation portability) applied at the
block-rendering layer.

## Korean-first authoring

The picker MUST support Korean search. Tags in the registry include
Korean equivalents (`검색`, `찾기`, `홈`, `메뉴`). The picker's search
field accepts mixed-script input and matches across all tag languages.

For categories, both English and Korean labels are shown:

```
Common · 자주 사용 | Actions · 동작 | Communication · 커뮤니케이션 | ...
```

This matches the project's existing Korean-first text-field demos and
typography axis showcase pages.

## Open questions

These are deliberately left undecided until plugin extraction:

1. **Should the picker live in a stand-alone `axismundi-icons` plugin or be part of a larger `axismundi-toolkit` plugin?** — leans stand-alone for clarity; aggregation could happen later.
2. **Should the SVG registry curate Mastodon / Bluesky / GitHub / etc., or rely on the user to paste them in?** — leans curated, with sanitization on registration.
3. **Should the picker expose Outlined / Sharp styles, or wait for design feedback that Rounded-only is insufficient?** — leans wait. Rounded-only matches M3 baseline; adding styles before there's a concrete need would bloat the picker.
4. **Should there be a "favorite icons" feature?** — possibly; defer to plugin v0.2.

## Cross-references

- Icon font track contract: `ICON-FONT-POLICY.md` (sibling)
- SVG track contract: `SVG-ICON-POLICY.md` (sibling)
- Conversion inventory: `INLINE-SVG-INVENTORY.md` (sibling)
- Charter on theme-state vs theme-control: `lab/docs/ARCHITECTURE-BOUNDARIES.md §2`
- Charter on theme can / plugin should: `lab/docs/ARCHITECTURE-BOUNDARIES.md §4`
- Existing infrastructure: `lab/stylesheets/icons.css` §2 (CSS custom properties for axis control)

## Change log

- **v3.4.2 — initial sketch.** Picker UX surface model defined
  (toolbar item, sidebar inspector, popover with two tabs). Icon
  registry shape sketched with Korean-multilingual tags example.
  Theme state vs picker state boundary codified (no `--md-grade` in
  picker per charter §2). Type override path (Material Symbols /
  Custom SVG / Inline string) specified. Federation behavior
  decoupled (store choice, render fallback per-block-type).
  Plugin territory boundary explicit. Four open questions named
  for plugin-extraction decision time.
