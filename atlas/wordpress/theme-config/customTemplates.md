---
rule_id: theme-config.json-customTemplates
domain: theme-config
topic: top-level
field_cluster: registration
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#customtemplates
    section: "theme.json — customTemplates (top-level field, 3 properties: name/title/postTypes)"
    captured: 2026-05-09
related:
  - theme-config.json-templateParts        # adjacent top-level — partial-region counterpart (vs whole-template here)
  - theme-config.json-patterns             # adjacent top-level — multi-block snippet counterpart (vs whole-template)
  - block.json-hierarchy-constraints       # parallel governance pattern at block scale (parent/ancestor/allowedBlocks)
  - plugin-dev.custom-post-types           # cross-context: postTypes references registered post types
---

# RULE — `customTemplates` (theme.json top-level) — document archetype + routing authority

## WHEN

Configuring a theme's `theme.json` to **register metadata for
custom page templates** defined in the theme's `/templates/` folder
that should be **user-selectable for specific post types**. Use to:

- Declare which custom template files exist (beyond the standard
  template hierarchy archetypes like `singular.html`, `index.html`).
- Provide a human-readable title for editor display.
- **Constrain which post types may select this template** (via
  `postTypes`) — first explicit ROUTING / ASSIGNMENT GOVERNANCE in
  theme.json.

This chunk closes the **3-field top-level filesystem-coupled
metadata registry pattern** (patterns / templateParts /
customTemplates) AND introduces **TWO new ontology axes** to KB:

1. **Document-archetype authority** — patterns/templateParts were
   composition primitives; customTemplates declares WHOLE-DOCUMENT
   contracts (entire page layouts).
2. **Routing/assignment governance** — `postTypes` is the FIRST
   theme.json field that gates EXPOSURE / selection authority,
   not just declaration. Connects WordPress template hierarchy
   resolution to theme.json registration.

## SHAPE

### 3 fields per template entry

```json
{
  "customTemplates": [
    {
      "name":      "page-with-sidebar",
      "title":     "Page with Sidebar",
      "postTypes": [ "page" ]
    },
    {
      "name":      "full-width",
      "title":     "Full Width",
      "postTypes": [ "page", "post" ]
    },
    {
      "name":      "landing",
      "title":     "Landing Page"
    }
  ]
}
```

| Property | Type | Notes |
|---|---|---|
| `name` | `string` | Filename WITHOUT extension of the template in `/templates/{name}.html`. **Filesystem reference.** |
| `title` | `string` | Display title (translatable). Shown in the post-editor template selector. |
| `postTypes` | `[ string ]` | List of post type slugs that may select this template. **Routing/assignment governance.** |

### What's NOT in theme.json

- The actual block markup of the template (lives in
  `/templates/{name}.html`).
- Template hierarchy keywords (e.g., this is NOT how you declare
  `single-{post-type}.html` automatic resolution — that's WordPress's
  template hierarchy conventions, not customTemplates).
- Conditional template assignment (no `if user has X capability`
  gates; postTypes is the only documented selection gate).
- Per-template styling / settings (those use the standard
  `/templates/{name}.html` content + per-block-type styles).

theme.json's `customTemplates` field is **metadata + assignment
governance** over filesystem-defined template HTML files.

## REQUIRES

- Field MUST be a top-level entry in theme.json (sibling of
  `settings`, `styles`, `templateParts`, `patterns`).
- For each `name`, a corresponding `/templates/{name}.html` file
  MUST exist. theme.json declares metadata FOR these files;
  missing files → silent skip / template not selectable.
- `postTypes` array entries MUST be valid registered post type
  slugs (e.g., `"post"`, `"page"`, custom CPT slugs registered
  via `register_post_type()`).
- Omitting `postTypes` (per the third example above) means... the
  template's selection visibility is unspecified. ⚠ Whether
  omitting `postTypes` makes the template available to ALL post
  types, or to NO post types, or restricted by other means is
  not crisply documented. Treat as verification-needed; explicit
  `postTypes` is safer.
- Custom templates differ from STANDARD template hierarchy entries
  (`single.html`, `archive-{post-type}.html`, etc.) which are
  resolved automatically by WordPress's template hierarchy logic.
  customTemplates registers ALTERNATIVES that the user explicitly
  picks, beyond the auto-resolved hierarchy.

## INVARIANTS

- **Document-archetype authority is a NEW ontology layer.**
  patterns/templateParts dealt with COMPOSITION PRIMITIVES (snippets,
  regions). customTemplates deals with WHOLE-DOCUMENT CONTRACTS —
  entire page layouts that frame the post content. The authority
  scale escalates:

  | top-level field | scope | unit |
  |---|---|---|
  | `patterns` | snippet | multi-block tree, copy semantics |
  | `templateParts` | region | named area, reference semantics |
  | `customTemplates` | document | whole-page archetype + selection routing |

- **postTypes — first ROUTING / ASSIGNMENT GOVERNANCE in theme.json.**
  This is qualitatively different from settings/styles' authority:
  - settings = "what tokens / capabilities EXIST for users"
  - styles = "what values are realized at theme/block scope"
  - **customTemplates.postTypes = "which post types may SELECT this
    template at editor time"**

  postTypes gates SELECTION, not declaration. The template HTML
  file exists regardless; postTypes controls who may bind it to
  their content.
- **postTypes parallels block.parent/ancestor at template scale.**
  Block-level governance has `parent` (must be DIRECT child of)
  and `ancestor` (must be SOMEWHERE descendant of) for insertion
  topology. customTemplates.postTypes is the same governance
  shape at a different scale:

  | Mechanism | Scope | Constraint |
  |---|---|---|
  | `block.parent` | block in editor | must be DIRECT child of named block(s) |
  | `block.ancestor` | block in editor | must be SOMEWHERE descendant of named block(s) |
  | `block.allowedBlocks` | block container | only these block types allowed as direct children |
  | **`customTemplates.postTypes`** | **template selection** | **only these post types may SELECT this template** |

  Same "selection-surface restriction" pattern across scales.
- **System-wide invariant: declaration ≠ exposure.** customTemplates
  is the latest example of a Gutenberg cross-system pattern:

  | Mechanism | Declaration (exists) | Exposure (selectable) |
  |---|---|---|
  | capabilities | block.json supports declares | appearanceTools / settings gates expose |
  | transforms | transform declared in transforms array | isMatch function gates availability |
  | variations | variation registered | scope arrays + isActive gate visibility |
  | presets | preset exists in registry | custom/default gates control selection |
  | **templates** | **/templates/{name}.html exists** | **postTypes array gates which post types may select** |

  Each layer has BOTH a declaration mechanism AND an independent
  exposure mechanism. They can drift independently.
- **First "frontend routing" / template-hierarchy bridge in KB.**
  Until now KB has operated mostly in editor + theme ontology.
  customTemplates touches WordPress's template hierarchy
  resolution system — the frontend routing logic that picks
  which template renders a given URL. customTemplates registers
  USER-SELECTABLE alternatives beyond the auto-resolved
  hierarchy. This is the FIRST KB chunk to brush against
  WordPress's CMS framing (post types, template hierarchy,
  routing).
- **Filesystem-coupled metadata registry pattern (3rd instance).**
  Same pattern as patterns / templateParts:
  - theme.json contains pointer + metadata only.
  - Actual content (template HTML) lives in
    `/templates/{name}.html`.
  - Database may store user edits as `wp_template` post-type
    entries (after first edit in Site Editor).
- **Custom templates ≠ standard template hierarchy.** WordPress's
  template hierarchy auto-resolves which template renders for a
  given URL (e.g., `single-{post-type}.html` → `single.html` →
  `singular.html` → `index.html`). customTemplates registers
  ADDITIONAL templates that users can EXPLICITLY pick from the
  template selector — they don't auto-resolve to URLs.
- **Title is translatable; name and postTypes are not.** Same
  pattern as templateParts. Translation via standard WordPress
  i18n functions on the title string.
- **Database persistence: wp_template post-type entries** (after
  first user edit). Same 3-layer authority pattern as
  templateParts:
  1. theme.json: registration + metadata + selection gate.
  2. /templates/{name}.html: initial / fallback content.
  3. wp_template posts: live edit state (overrides filesystem).
- **Multiple templates may share the same `postTypes`.** A theme
  can register many alternatives for "page" (page-with-sidebar,
  full-width, landing, etc.); the user picks one per page
  instance.
- **Post-type-specific templates resolve via standard hierarchy
  too.** `single-product.html` for the `product` post type would
  be auto-resolved by template hierarchy without registration in
  customTemplates. customTemplates is for ALTERNATIVES, not for
  hierarchy entries.
- ⚠ **Minimum WP version unknown.** customTemplates arrived with
  Full Site Editing / block themes (likely WP 5.9+ era).
  Frontmatter `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Putting block markup / template content in theme.json. Content
  lives in `/templates/{name}.html` files; theme.json contains
  metadata only.
- ❌ Using customTemplates to declare standard template hierarchy
  entries (`single.html`, `archive.html`, etc.). Those are
  auto-resolved by WordPress's template hierarchy convention;
  registering them in customTemplates makes them user-selectable
  alternatives, NOT hierarchy fallbacks.
- ❌ Setting `postTypes: []` (empty array) and expecting "all post
  types may use this". Empty array likely means NO post types may
  select it — making the template effectively unselectable.
  Use omission OR an explicit list.
- ❌ Setting `postTypes: ["any"]`. There is no `"any"` post type
  slug; use the explicit list of slugs you want to enable.
- ❌ Using post type slugs that aren't registered. Templates
  remain registered but are unselectable for any post type
  (no post type has that slug).
- ❌ Renaming a custom template's `name` after users have selected
  it for posts. Selected templates are tracked by name; rename
  orphans the assignments and posts revert to the standard
  hierarchy.
- ❌ Treating customTemplates as a styling registration. Templates
  declare STRUCTURE and CONTENT (which blocks compose the page),
  not values. Styling lives in settings/styles + per-template
  styles.{name}.* if any.
- ❌ Confusing customTemplates with templateParts. Custom
  templates are WHOLE PAGES; template parts are REGIONS WITHIN a
  page (header / footer / sidebar). They compose differently:
  templates contain template parts, not vice versa.
- ❌ Using customTemplates for runtime conditional templates
  (e.g., "show this template only on Tuesdays"). The
  postTypes constraint is the only documented selection gate;
  runtime conditional template selection requires PHP filters
  (`template_include`, etc.), not theme.json.
- ❌ Putting the file in `/parts/` instead of `/templates/`.
  Template parts go in `/parts/`; custom templates go in
  `/templates/`. The folder convention is enforced by core.

## RELATED

- `theme-config.json-templateParts` — adjacent top-level
  filesystem-coupled metadata registry. Templates COMPOSE
  template parts via the `wp:template-part` block at the
  template HTML level. Scale relationship: customTemplates
  (whole page) > templateParts (page region) > patterns
  (multi-block snippet).
- `theme-config.json-patterns` — adjacent top-level filesystem/
  directory-coupled metadata registry. Composition unit
  scale: smallest in the trio.
- `block.json-hierarchy-constraints` — parallel governance
  pattern at block scale. parent/ancestor/allowedBlocks
  constrains block insertion; customTemplates.postTypes
  constrains template selection. Same "selection-surface
  restriction" pattern.
- `plugin-dev.custom-post-types` (cross-context, planned) —
  postTypes references post type slugs registered via
  `register_post_type()` PHP. Custom post types from plugins
  can be valid postTypes entries.
