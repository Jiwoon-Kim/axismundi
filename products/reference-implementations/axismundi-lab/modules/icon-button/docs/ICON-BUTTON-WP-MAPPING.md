# Icon button — WordPress Mapping Audit (v3.5.2 Phase 1)

> **Status**: Phase 1 WordPress mapping body authored. Implementation not started.
> **Component**: Icon button #2
> **Companion docs**: `ICON-BUTTON-SPEC-AUDIT.md`, `ICON-BUTTON-MEASUREMENT-AUDIT.md`

---

## §1 — Critical Framing

Icon button maps less cleanly to a single WordPress core block than Button #1.

Button #1 has a direct block analog:

```txt
core/button + core/buttons
```

Icon button's natural WordPress surfaces are mostly editor chrome, theme action slots, navigation actions, search affordances, social/federation controls, and plugin UI. Therefore this mapping is primarily a boundary and anti-pattern audit.

Core rule:

```txt
Icon-only action control = native button + accessible name + aria-hidden glyph.
```

---

## §2 — Core / Block Context Inventory

| Surface | Icon button relevance | Phase 1 mapping |
|---|---|---|
| `core/buttons` / `core/button` | Authors may try icon-only buttons with hidden labels. | Not primary. Use label-bearing Button for `core/button`; route icon-only controls to Icon button pattern/custom block. |
| `core/navigation` | Navigation items can include icons; action controls in app bars/nav shells often use icon buttons. | Links may use `<a>` only for real navigation. Action icon buttons remain `<button>`. |
| `core/social-links` | Social icons are link-like, not generic action buttons. | Link boundary: use anchors for outbound profiles; do not use action-button semantics. |
| `core/search` | Submit/search/voice affordances naturally use icon buttons. | Primary theme-side candidate: submit/search action button with accessible label. |
| `core/post-comments-form` | Reply/share/moderation affordances may be icon-only in theme/plugin UI. | Indirect mapping; behavior is plugin/core territory. |
| Editor toolbar / block controls | Most natural WordPress icon-button surface. | Primary mapping: native button, accessible name, icon-system glyph. |
| Inspector/sidebar controls | Repeated icon-only action controls. | Primary mapping in editor/plugin UI. |
| Pattern composition | App bars, cards, toolbars, social rows. | Pattern-level use only; no baseline registration in Phase 1. |

---

## §3 — Accessible Name Contract

Icon-only controls require a programmatic name.

Accepted patterns:

```html
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="Search">
  <span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true" draggable="false">search</span>
</button>
```

```html
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-labelledby="search-button-label">
  <span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true" draggable="false">search</span>
</button>
```

Required:

```txt
- aria-label, aria-labelledby, or equivalent accessible name
- glyph span aria-hidden="true"
- glyph is not the accessible name
- tooltip text does not replace the accessible name unless correctly wired
```

Failure:

```html
<button class="ax-icon-button is-standard">
  <span class="material-symbols-rounded">search</span>
</button>
```

This is a Principle 1 failure.

---

## §4 — Theme-Side Rendering Paths

### §4.1 — Pattern Composition

Themes can compose Icon buttons inside:

```txt
app bars
toolbars
search forms
card action rows
social/action rows
navigation-adjacent controls
```

Phase 1 records the pattern; it does not create a block pattern.

### §4.2 — Block Style / Variation

Icon button is not a clean `core/button` block style because `core/button` is label-first and often link-oriented.

Recommended boundary:

```txt
Use core/button for label-bearing buttons.
Use Icon button pattern/custom block for icon-only action controls.
```

### §4.3 — Custom Block / Plugin UI

Plugin/editor surfaces may need an explicit icon-button control.

Requirements:

```txt
- render native <button type="button"> for actions
- expose accessible name
- use icon-system glyph policy
- preserve disabled/aria-disabled distinction
- do not implement behavior in theme CSS
```

---

## §5 — Disabled And `aria-disabled` Contract

Native disabled:

```html
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="Search" disabled>
  <span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true" draggable="false">search</span>
</button>
```

Plugin-managed aria-disabled:

```html
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="Search" aria-disabled="true">
  <span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true" draggable="false">search</span>
</button>
```

Required note:

```txt
aria-disabled styles the state and exposes semantic state, but does not
block click activation. The plugin/app must guard event handling.
```

Phase 2 pattern should use separate `§5` and `§5a` sections, matching Button v3.5.1.

---

## §6 — Anti-Pattern Inventory

| Anti-pattern | Why it is wrong |
|---|---|
| Icon button without `aria-label` / equivalent name | The visible glyph is aria-hidden and cannot name the control. |
| Using `core/button` as an icon-only control | `core/button` is label-first; icon-only behavior needs explicit contract. |
| Hardcoded SVG bypassing `icon-system/` | Skips Material Symbols/SVG policy and shared glyph hardening. |
| Decorative emoji as the only glyph | Unstable rendering and poor semantic control naming. |
| `<a class="ax-icon-button">` for non-navigation action | Links navigate; actions need buttons. |
| `<div role="button" class="ax-icon-button">` | Reimplements native button behavior badly. |
| `aria-disabled` without event guard | Looks disabled but remains activatable. |
| Tooltip-only label | Tooltip is not a reliable accessible name unless explicitly wired. |
| Glyph text used as accessible label | Ligature names such as `more_vert` are not user-facing labels. |
| Current ripple wired ad hoc | Animated ripple is deferred to Ripple v2. |

---

## §7 — ActivityPub / Social CMS Note

Axismundi's social/CMS surface will likely use Icon button for:

```txt
like
reply
repost
share
bookmark
more actions
notifications
```

Phase 1 does not implement those behaviors. It records the control contract those surfaces must consume:

```txt
native action button
accessible name
aria-hidden glyph
icon-system glyph policy
plugin-managed disabled handling when needed
```

---

## §8 — Runtime Audit Migration Note

`ICON-BUTTON-RUNTIME-AUDIT.md` remains at:

```txt
modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
```

Phase 1 mapping treats it as canonical historical evidence for the SVG-to-Material-Symbols conversion and glyph hardening. It does not move the file.

Future approved work may move/copy the audit under:

```txt
modules/icon-button/docs/
```

with a stub or cross-reference left in `icon-system/docs/`.

---

## §9 — Mapping Verdict

| # | Criterion | Verdict | Notes |
|---:|---|:---:|---|
| 1 | Core/block context inventory | PASS | Direct, indirect, and non-mapping surfaces are separated |
| 2 | Accessible name contract | PASS | Mandatory naming requirement recorded |
| 3 | Anti-pattern inventory | PASS | 10 icon-button-specific anti-patterns recorded |
| 4 | Theme/plugin boundary | PASS | CSS/theme look separated from plugin/editor behavior |
| 5 | Runtime audit disposition | PASS | Reference kept; migration deferred |
| 6 | Ripple deferral | PASS | No ad hoc ripple wiring |

---

## §10 — Cross-References

```txt
Companion docs:
  ./ICON-BUTTON-SPEC-AUDIT.md
  ./ICON-BUTTON-MEASUREMENT-AUDIT.md

Phase docs:
  docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md
  docs/v3.5.2/ICON-BUTTON-PHASE-1-PLAN.md

Precedents:
  ../button/docs/BUTTON-WP-MAPPING.md
  ../button/docs/BUTTON-SPEC-AUDIT.md
  ../chip/docs/CHIP-WP-MAPPING.md

Icon-system:
  ../icon-system/docs/ICON-SYSTEM-AUDIT.md
  ../icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
  ../icon-system/docs/ICON-FONT-POLICY.md
```

---

## §11 — What This Mapping Audit Does NOT Do

- Does not modify `theme.json`.
- Does not register block styles.
- Does not create a custom block.
- Does not edit WordPress PHP or plugin code.
- Does not edit `style-guide.html`.
- Does not move `ICON-BUTTON-RUNTIME-AUDIT.md`.
- Does not implement ActivityPub/social actions.
- Does not implement icon picker UI.
- Does not implement ripple wiring.
- Does not address admin-side UI beyond mapping notes.
