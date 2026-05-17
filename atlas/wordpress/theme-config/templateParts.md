---
rule_id: theme-config.json-templateParts
domain: theme-config
topic: top-level
field_cluster: registration
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#templateparts
    section: "theme.json — templateParts (top-level field, 3 properties: name/title/area)"
    captured: 2026-05-09
related:
  - theme-config.json-patterns             # adjacent top-level — copy semantics counterpart (vs templateParts' reference semantics)
  - theme-config.json-customTemplates      # adjacent top-level — full-template counterpart (vs templateParts' partial-region)
  - block.variations                       # area="header"/"footer" have core block variations — semantic role recurrence
  - block.inner-blocks                     # template parts are typically containers with InnerBlocks
  - block.markup-representation            # template parts persist as separate post entities (wp_template_part post type) referenced from templates
---

# RULE — `templateParts` (theme.json top-level) — live-linked structural composition

## WHEN

Configuring a theme's `theme.json` to **register metadata for
template parts** defined in the theme's `/parts/` folder. Use to:

- Declare which template part files exist and their display titles.
- Assign a semantic `area` role (header / footer / etc.) for editor
  organization and core integration.
- Mark template parts as **live-linked structural composition
  units** — when edited, changes propagate to ALL templates that
  include them.

This chunk introduces **two new ontology axes** that subsequent
KB chunks will build on:

1. **Structural composition authority** (vs settings/styles' value
   authority) — theme.json reaches into site STRUCTURE, not just
   styling.
2. **Reference semantics** (vs patterns' copy semantics) — template
   parts are the LIVE-LINKED counterpart to patterns'
   COPY-ON-INSERT model.

## SHAPE

### 3 fields per template part entry

```json
{
  "templateParts": [
    {
      "name":  "header",
      "title": "Header",
      "area":  "header"
    },
    {
      "name":  "footer",
      "title": "Footer",
      "area":  "footer"
    },
    {
      "name":  "sidebar",
      "title": "Sidebar"
    }
  ]
}
```

| Property | Type | Notes |
|---|---|---|
| `name` | `string` | Filename WITHOUT extension of the template part in `/parts/{name}.html`. **Filesystem reference.** |
| `title` | `string` | Display title (translatable). Shown in the Site Editor's template parts list. |
| `area` | `string` | Semantic structural role. **Specially-handled values: `header`, `footer`** (core block variations exist for these). Other values accepted but without core integration. |

### What's NOT in theme.json

- The actual block markup of the template part (lives in
  `/parts/{name}.html`).
- Per-instance overrides (template parts are LIVE — no
  per-instance state separate from the part itself).
- Conditional registration (the array is unconditional).
- Block-area mapping (which blocks should appear in this area —
  governed by core's block-area conventions, not this field).

theme.json's `templateParts` field is **purely metadata extension**
over filesystem-defined template part HTML files.

## REQUIRES

- Field MUST be a top-level entry in theme.json.
- For each `name` value, a corresponding `/parts/{name}.html` file
  MUST exist in the theme. theme.json declares metadata FOR these
  files; missing files cause silent skip / error.
- `name` MUST match the filename exactly (case-sensitive on
  case-sensitive filesystems).
- `area` value SHOULD be one of the semantically-recognized values
  for core integration. Documented: `header`, `footer`. Other
  values (`sidebar`, `aside`, `region`) are accepted but lack the
  core's automatic integration (variations, inserter grouping).
- Template parts are stored as `wp_template_part` posts in the
  database after first edit; theme.json metadata serves as the
  initial registration, but user edits create database overrides.

## INVARIANTS

- **Reference semantics — live-linked propagation.** Unlike
  `patterns` (copy semantics — inserted blocks become independent),
  template parts are **referenced** from templates. When a user
  edits a template part:
  - The change propagates to EVERY template that includes the
    part.
  - All site pages using those templates show the updated part on
    next render.
  - There is no per-instance fork / local override at the template-
    part level. Locality is at the template level (which template
    references this part).
- **Copy vs Reference semantics — KB-wide recurring axis.** Template
  parts and patterns formalize this distinction at the structural
  composition layer:

  | System | Copy form | Reference form |
  |---|---|---|
  | structural composition | patterns | **template parts** |
  | block content | inserted blocks | synced patterns / reusable blocks |
  | block identity | transforms (new type) | (no reference form) |
  | block role | variations (preset) | (no reference form) |
  | schema versioning | deprecations (migrated copy) | preserved identity |

- **Structural composition authority is a NEW ontology layer.**
  Settings/styles dealt with VALUES (color, sizes, etc.).
  templateParts deals with STRUCTURE — which regions exist on a
  page (header / footer / sidebar) and what blocks compose them.
  Authority shifts from "what values render" to "what structural
  arrangements exist".
- **`area` is a semantic structural role taxonomy, NOT just a
  label.** Source explicitly: *"Block variations for `header` and
  `footer` values exist and will be used when the area is set to
  one of those."*
  Implications:
  - Setting `area: "header"` activates core's header-specific block
    variations and editor integration (header-specific inserter
    suggestions, block area assignment).
  - Setting `area: "footer"` does the same for footer.
  - Other area values (`sidebar`, custom) do NOT receive this core
    integration — they're free-form labels with no special editor
    behavior.
  - This makes `area` participation in the **semantic role
    projection engine** that recurs across Gutenberg layers
    (block variations / styles.elements / supports.color.*).
- **Template parts are wp_template_part post-type entities.**
  After first edit in the Site Editor, WordPress creates a
  `wp_template_part` post storing the modified content. The
  theme.json registration declares the EXISTENCE; the database
  stores the LIVE STATE; the `/parts/{name}.html` file is the
  fallback / initial value. Three layers of authority:
  1. theme.json: metadata + registration.
  2. /parts/{name}.html: initial / fallback content.
  3. wp_template_part posts: live edit state (overrides
     filesystem fallback).
- **Filesystem-coupled metadata registry pattern (shared with
  patterns / customTemplates).** theme.json contains pointer +
  metadata; actual content lives in filesystem. See
  `theme-config.json-patterns` for parallel pattern.
- **Inclusion mechanism: block-side reference, NOT theme.json-
  side.** Templates reference template parts via the
  `wp:template-part` block in template HTML files (e.g.,
  `<!-- wp:template-part {"slug":"header","tagName":"header"} /-->`).
  theme.json doesn't declare which templates include which parts
  — that's template content concern.
- **Core block variations for area exist at the block level.**
  The `core/template-part` block has variations corresponding to
  the documented areas. When the area is set to `header` or
  `footer`, the editor uses these variations for picker /
  inserter integration.
- **NOT a CSS-styling concern.** templateParts is registration +
  metadata only. Visual styling of header / footer / sidebar
  comes from `/parts/{name}.html` content (and any styles
  applied within), not from theme.json `templateParts.*` itself.
  Compare with settings/styles which contain styling values.
- **Title is translatable; name and area are not.** Source:
  *"Title of the template, translatable."* Translation is via
  the standard WordPress i18n functions; the `__('Header')`
  pattern works for translatable titles.
- **Hierarchical / nested template parts not documented.**
  Whether a template part can include another template part is
  not explicitly addressed in the captured source. Inferred:
  yes (since `wp:template-part` blocks can appear in any block
  context, including inside other parts), but verify per
  use case.
- **Template parts compose with InnerBlocks at runtime.** A
  template part's `/parts/{name}.html` typically contains a
  block tree with InnerBlocks regions; consuming templates
  reference the part via `wp:template-part` block, which renders
  the entire subtree at the inclusion point.
- **Bridge to "Gutenberg = semantic role projection engine"
  framing.** The recurring "semantic role" concept (header /
  footer / button / link / heading / caption) appears across:
  - `block.variations` (variation categories)
  - `supports.color.{heading, button, link, caption}`
  - `styles.elements.{link, button, heading, caption}`
  - `templateParts.area` (this field)
  - core block variations for header / footer parts
  Each layer projects the same semantic role concept into its
  own concerns (color, typography, structure, registration). May
  unify in future chunks.
- ⚠ **Minimum WP version unknown.** templateParts arrived with
  Full Site Editing (WP 5.9+ era). Frontmatter `wp_min` is
  `"verification-needed"`.

## ANTIPATTERNS

- ❌ Putting template part HTML / block markup in theme.json.
  Block content lives in `/parts/{name}.html` files; theme.json
  contains metadata only.
- ❌ Setting `area: "Header"` (capitalized) instead of `"header"`.
  The documented integration values are lowercase
  (`header` / `footer`). Verification needed for case
  sensitivity but lowercase is safe.
- ❌ Using `templateParts` for snapshot inserts. The reference
  semantics mean editing the part affects ALL uses; if a user
  wants per-template independent copies, use `patterns`
  instead. Mixing the two semantics confuses authoring intent.
- ❌ Declaring template parts in theme.json without corresponding
  `/parts/{name}.html` files. The metadata registers but the
  part has no content; editor will show empty / placeholder
  state.
- ❌ Expecting `area: "sidebar"` to receive the same core
  integration as `header`/`footer`. Source documents block
  variations specifically for header and footer. Other area
  names work as free-form labels but don't get the variation
  treatment.
- ❌ Using non-translatable title strings when targeting
  international themes. Title supports translation; use
  `__('Title String')` pattern in PHP that generates the
  theme.json (or use a build-time i18n step for static
  theme.json).
- ❌ Renaming a template part's `name` after users have edited
  it in the Site Editor. The wp_template_part post is
  associated with the original slug; rename breaks the link
  and orphans the user's edits.
- ❌ Treating template parts as styling units. They're
  STRUCTURE — what blocks exist in the header / footer
  composition. Styling is a separate concern (settings /
  styles / styles.css).
- ❌ Hardcoding template-part inclusion in PHP templates
  outside the Site Editor system. Block themes inherit
  template-part inclusion via the `wp:template-part` block in
  template HTML; bypassing this loses Site Editor
  integration.
- ❌ Confusing template parts with patterns. Patterns =
  copy-on-insert (independent edits per insertion). Template
  parts = reference (edits propagate to ALL uses).

## RELATED

- `theme-config.json-patterns` — adjacent top-level
  filesystem/directory-coupled metadata registry. Same
  registration pattern, OPPOSITE composition semantics:
  patterns = copy / template parts = reference. Together they
  formalize the copy ↔ reference distinction at the structural
  composition layer.
- `theme-config.json-customTemplates` — adjacent top-level
  filesystem-coupled metadata registry. customTemplates
  registers full-template metadata (`/templates/`); templateParts
  registers partial-region metadata (`/parts/`). Templates
  COMPOSE template parts via the `wp:template-part` block.
- `block.variations` — `area` values `"header"` and `"footer"`
  are explicitly tied to existing core block variations.
  templateParts.area participates in the same "semantic role"
  taxonomy that variations express at the block level.
- `block.inner-blocks` — template parts typically wrap their
  content in InnerBlocks-containing structures
  (header / footer / sidebar are usually flexible containers).
- `block.markup-representation` — template parts persist in
  the database as `wp_template_part` post-type entities after
  first edit. Three-layer authority: theme.json registration +
  filesystem fallback + database live state.
