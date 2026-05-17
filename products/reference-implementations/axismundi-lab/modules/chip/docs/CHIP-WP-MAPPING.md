# Chip WordPress Mapping Audit — v3.4.9

> Bucket: E (Component module — WordPress binding audit)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §4 (theme can / plugin should)
> Strategy reference: see `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md`
>
> First WordPress mapping audit. Establishes the template for future
> Component module WP-MAPPING docs (text-field, FAB, etc.).
>
> Phase 1 — skeleton. Phase 2 implementation may add concrete block-style
> registrations or pattern HTML demonstrations.

## §1 — Critical framing

This document maps the Axismundi chip primitive to its WordPress / Gutenberg surfaces. Three questions guide each mapping:

1. **Where does a chip naturally appear in a WordPress site?** (theme contexts)
2. **Which WordPress core blocks render chip-shaped content?** (block contexts)
3. **Where does chip rendering cross the Charter §4 theme/plugin boundary?** (mapping limits)

The audit is descriptive, not prescriptive. It records the mapping as it stands today; it does not register block styles or modify theme.json. Phase 2 may add a minimal `register_block_style()` demonstration in `lab-chip-pattern.html` if that helps validate the mapping.

## §2 — Charter §4 application

```
Theme can:
  - Render chip-shaped UI via .chip + .chip--* classes
  - Provide chip rendering for the few core blocks where chip-shape is the
    obvious M3 expression (tag list, search filter, applied filter pill)
  - Register WordPress block styles that opt blocks into chip rendering

Theme should NOT:
  - Implement chip data sourcing (taxonomy queries, filter state, applied
    filter persistence) — plugin/editor territory
  - Add custom blocks for chip rendering — plugin territory
  - Provide chip color customization UI in Gutenberg — that requires
    BACKLOG #21 Interpreter Plugin

Plugin should:
  - Source the data that fills chip surfaces (taxonomy terms, filter
    facets, applied filter state)
  - Provide editor sidebar controls if dynamic chip configuration is
    needed
  - Add custom blocks if chip rendering needs custom save markup
```

The chip primitive itself is firmly on the theme side. What *fills* the chip — text content, link target, applied state — is largely plugin territory.

## §3 — WordPress core block context inventory

The following core blocks plausibly render chip-shaped content. Each row records:

- **Block**: the core block name
- **Current relationship**: how chip rendering relates to the block today
- **Mapping action at v3.4.9**: what (if anything) this audit recommends

| Core block | Current relationship | Mapping action |
|---|---|---|
| `core/tag-cloud` | Renders terms as inline links. Visually chip-adjacent. Block style "Chip cloud" would render as chip variants. | **Documentation only** — register block style demonstration in `lab-chip-pattern.html` (not in baseline `functions.php`). |
| `core/search` | Search form has leading icon (already extracted in icon-system module v3.4.4). Search input is text field territory, NOT chip. | **No chip mapping** — search field belongs in future text-field component module |
| `core/post-terms` | Renders taxonomy terms as inline elements. Could render as filter or suggestion chips depending on context. | **Documentation only** — block style "Term chips" demonstration |
| `core/categories` | Renders category list. Hierarchical context complicates chip mapping (chips are typically flat). | **Out of scope** — hierarchical render is not a chip surface |
| `core/navigation` | Renders nav menu. M3 has its own nav surfaces (top-app-bar, navigation-bar, navigation-rail, navigation-drawer) — chip is not the right primitive. | **No chip mapping** |
| `core/group` | Generic container. Chip groups (filter row, tag row) are typically a `core/group` wrapping multiple chip-rendered inner blocks. | **No mapping action** — chip groups emerge from existing block composition; no special handling |
| `core/buttons` / `core/button` | Buttons are NOT chips. M3 button and M3 chip have separate specs and different roles. Allowing a button to be styled "like a chip" would create ambiguity. | **Explicitly NOT mapped** — see §6 boundary violations to avoid |

## §4 — Theme-side chip rendering paths

Three concrete paths a theme can take to render chip-shaped content:

### §4.1 Block style variation (recommended primary path)

WordPress `register_block_style()` lets a theme register a CSS class to apply to a block. This is the cleanest theme-side chip surface:

```php
/* In functions.php — NOT added at v3.4.9; example only */
register_block_style(
    'core/tag-cloud',
    array(
        'name'  => 'chips',
        'label' => __( 'Chips', 'axismundi' ),
    )
);
```

Then in theme CSS:

```css
.wp-block-tag-cloud.is-style-chips a {
  /* apply .chip + .chip--suggestion equivalent styling */
}
```

**Pros**: No save markup change. Block style picker exposes the option in the editor. Reversible (user can unset).

**Cons**: Block style applies to all child links uniformly; no per-tag chip variant choice without a custom block.

**v3.4.9 action**: `lab-chip-pattern.html` includes a section demonstrating this pattern as a code snippet, but the actual `register_block_style()` call is NOT added to baseline `functions.php`. Promotion is a separate Charter §1 decision.

### §4.2 Pattern-based composition

WordPress block patterns are pre-composed block arrangements that authors can insert as a starting point. A "Filter chip row" pattern could compose `core/group` + multiple inner blocks rendered as filter chips.

**Pros**: Author retains full control; chips are real `core/*` blocks.

**Cons**: Each chip is a separate block; updating one doesn't update siblings. Selected state is not naturally wired (would require Principle 2 native form wrapping per `CHIP-SPEC-AUDIT.md §7`).

**v3.4.9 action**: Documentation only. Pattern authoring is part of Pilot Block Theme Probe (separate ROADMAP item).

### §4.3 Custom block (plugin territory)

For dynamic chip surfaces (live filter UI, applied filter pills with dismiss), a custom block is more appropriate. This crosses into plugin territory.

**Pros**: Full data sourcing, editor UI, save format under plugin control.

**Cons**: Plugin dependency.

**v3.4.9 action**: Out of scope. Recorded in BACKLOG #21 (Interpreter Plugin) scope.

## §5 — theme.json contract (current state)

The Axismundi pilot's `theme.json` does NOT currently expose chip-specific tokens to the editor UI. Chip styling resolves entirely through CSS class application in `components.css §11`. Specifically:

- No chip-specific palette slugs in `settings.color.palette` (chip uses generic `secondary-container` / `on-secondary-container` / `outline-variant` slugs)
- No chip-specific typography slugs (chip uses `label-large` typescale)
- No chip-specific border/shape slugs (chip uses `corner-small` shape)

This is the **correct** binding posture per the binding strategy memo (`bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §2`):

```
theme.json = Gutenberg UI contract layer (slug registry)
tokens.css = actual design system runtime
--wp--preset--color--* = bridge between them
```

Chip is a downstream consumer of the color / typography / shape token graph, not a token producer. There is nothing to add to `theme.json` for v3.4.9.

## §6 — Mapping boundary violations to avoid

These patterns are explicitly NOT recommended:

| Anti-pattern | Why avoid |
|---|---|
| `core/button` styled "like a chip" | M3 button and M3 chip have distinct roles. Mixing them creates ambiguity for both designers and screen reader users. Use `core/buttons` for buttons; render chips via block style on tag-cloud or via custom block. |
| `theme.json` adding chip-specific palette slug (e.g. `chip-selected`) | Would flatten the M3 role graph. Chip selected state should derive from `secondary-container` (which is the existing M3 sys color role), not a chip-specific slug. |
| Hardcoded chip rendering inside `block.json` for a core block | Would tie chip rendering to a specific block. Use `register_block_style()` instead — opt-in, reversible, multi-block. |
| Gutenberg color picker exposed without M3 interpreter bridge | Violates "Visible control must map to real runtime behavior" (BACKLOG #20). Until BACKLOG #21 Interpreter Plugin lands, `settings.color.custom = false` is the honest default. |
| Filter chip `<button aria-pressed>` without state propagation | Violates Principle 1. Use `<input type="checkbox">` + `<label>` per Principle 2 (`CHIP-SPEC-AUDIT.md §7`). |

## §7 — Plugin-side surfaces (out of scope for v3.4.9)

Future plugin work that consumes the chip primitive:

| Plugin surface | Description | BACKLOG ref |
|---|---|---|
| Faceted search filter UI | Custom block rendering applied filter pills with dismiss | BACKLOG #21 |
| Taxonomy term browser | Custom block rendering term hierarchy with chip selection | BACKLOG #21 |
| Editor sidebar chip role panel | InspectorControls panel for assigning chip role to selected block | BACKLOG #21 |
| ActivityPub tag chips | Tag rendering in federation viewer / composer | Future federation work, not yet on BACKLOG |
| Form block chip input | User-entered tag-style chip input | Possibly related to BACKLOG #17 Text Input Audit |

None of these are v3.4.9 work. The mapping audit records them so that future plugin scope discussions can refer back to a single map.

## §8 — Mapping verdict

| # | Criterion | Status |
|---:|---|:---:|
| 1 | **No baseline changes** — `components.css §11`, `style-guide.html`, `theme.json`, `functions.php` all unchanged at v3.4.9 | ✓ PASS |
| 2 | **Documentation completeness** — three concrete theme-side rendering paths recorded with pros/cons (`§4.1` block style, `§4.2` pattern, `§4.3` custom block) | ✓ PASS |
| 3 | **Anti-pattern inventory** — five forbidden mappings recorded with rationale (`§6` table) | ✓ PASS |
| 4 | **Plugin surface index** — five future plugin surfaces indexed with BACKLOG cross-refs (`§7` table) | ✓ PASS |
| 5 | **Charter §4 alignment** — theme-can / plugin-should split confirmed for each rendering path (`§2` block) | ✓ PASS |

### Verdict

```
PASS as the first Component module WordPress mapping audit.

The audit confirms that chip rendering belongs cleanly on the theme
side via block style variations, with chip data sourcing belonging
to plugin territory. theme.json requires no chip-specific additions
at v3.4.9. Five anti-patterns are documented to prevent boundary
violations in future work. The mapping audit establishes the
template for future Component modules' -WP-MAPPING.md docs
(text-field, FAB, etc.).
```

한글 요약:

```
v3.4.9는 첫 Component module WordPress mapping audit로 PASS다.

Chip 렌더링은 block style variation을 통해 theme side에 깔끔하게
위치하며, chip 데이터 소싱은 plugin territory에 속한다. theme.json은
chip-specific 추가가 v3.4.9에서 필요 없다. 다섯 가지 anti-pattern이
기록되어 향후 작업의 boundary 위반을 방지하며, 이 mapping audit이
향후 Component module의 -WP-MAPPING.md 템플릿이 된다.
```

## §9 — What this mapping audit does NOT do

- Does not modify `theme.json`.
- Does not call `register_block_style()` in baseline `functions.php`.
- Does not author block patterns (Pilot Block Theme Probe scope).
- Does not implement any plugin code (BACKLOG #21).
- Does not decide between Gutenberg color customization modes (BACKLOG #20).
- Does not pre-decide v3.5.x+ Interpreter Plugin architecture.
- Does not address chip rendering in ActivityPub composer or federation viewer.
