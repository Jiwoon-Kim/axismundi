# FAB Family — WordPress Mapping Audit (v3.5.5 Phase 1)

> **Status**: Phase 1 WordPress mapping body authored. Implementation not started.  
> **Component family**: FAB #3 + Extended FAB #4  
> **Companion docs**: `FAB-SPEC-AUDIT.md`, `FAB-MEASUREMENT-AUDIT.md`

---

## §0 — Mapping Status

FAB is an action component with native button semantics, but it does not map naturally to a standard WordPress core block.

Reason:

```txt
FAB is placement-sensitive and action-context-sensitive.
It is not merely an inline button variant.
```

No WordPress PHP, block registration, block style, or plugin code is changed by this audit.

---

## §1 — WordPress Context Inventory

| Surface | FAB relevance | Phase 1 mapping |
|---|---|---|
| `core/button` | Shares native button semantics. | Weak/partial; does not own floating placement. |
| `core/buttons` | Can group ordinary inline CTAs. | Not a FAB primitive. |
| `core/navigation` | Navigation actions may contain icons. | Not FAB; avoid conflating nav links with primary floating action. |
| `core/social-links` | Icon-only links. | Not FAB; semantic purpose differs. |
| Template parts | Can place theme-owned static action surfaces. | Possible theme path. |
| Patterns | Can compose static CTA / quick-action layouts. | Plausible theme path. |
| Custom block | Can own app-like actions and behavior. | Plugin/custom-block territory. |

---

## §2 — No Natural Core Block Mapping

Decision:

```txt
No natural core/* block maps cleanly to FAB.
```

`core/button` can provide:

```txt
native button-like authoring affordance
label/content model
basic link/action expectation
```

But `core/button` does not provide:

```txt
floating placement contract
single primary screen action semantics
icon-system policy
Extended FAB label/icon structure
scroll/collapse behavior
FAB menu transition
```

Therefore:

```txt
core/button + is-style-fab is weak/partial and should not be treated as
the canonical mapping.
```

---

## §3 — Pattern Composition Path

Theme-can:

```txt
static CTA patterns
quick-action pattern examples
template-composed action surfaces
```

Pattern composition can demonstrate:

```txt
FAB visual primitive
Extended FAB static primitive
theme-owned placement shell
```

Pattern composition must not fake:

```txt
app state
scroll-aware behavior
modal/sheet transitions
editor-integrated actions
```

---

## §4 — Theme Template Part Path

Theme template parts may own static placement:

```txt
fixed or sticky placement shell
bottom/end visual region
non-dynamic front-end action affordance
```

Boundary:

```txt
The theme may render a static FAB surface.
The theme should not implement plugin-like application behavior with CSS.
```

If the action mutates application state, opens editor UI, coordinates with routing, or persists user data, it moves beyond a static theme primitive.

---

## §5 — Custom Block / Plugin Path

Plugin/custom block territory:

```txt
editor-integrated compose actions
dynamic post creation
scroll-aware auto-hide
Extended FAB collapse/expand
FAB menu transition
modal/sheet morph behavior
permission-aware action availability
```

Reason:

```txt
These require runtime state, event handling, data access, or editor
integration. A theme style alone should not own them.
```

---

## §6 — Placement And Positioning Boundary

FAB baseline comment says:

```txt
component itself is an inline-block element
page-level positioning is the author's job
```

Mapping consequence:

```txt
FAB primitive styles do not equal placement policy.
```

Phase 2 pattern docs should distinguish:

```txt
component specimen:
  inline demo surface for catalog comparison

placement specimen:
  optional static shell demonstrating where a FAB might sit
```

No global fixed-position utility is authorized by Phase 1.

---

## §7 — Accessible Name And Action Contract

FAB:

```txt
Icon-only, so aria-label or equivalent accessible name is required.
```

Extended FAB:

```txt
Visible label required.
Leading icon is decorative unless a future pattern explicitly says otherwise.
```

Action contract:

```txt
Use native <button type="button"> for actions.
Do not recreate button behavior with role/button divs.
Do not use navigation links for action behavior.
```

---

## §8 — Anti-Pattern Inventory

| Anti-pattern | Why it is wrong |
|---|---|
| Ordinary inline `core/button` restyled as FAB | Loses placement/action contract. |
| Non-positioned FAB in prose flow presented as production usage | FAB is placement-sensitive. |
| Icon-less FAB | FAB identity requires icon body. |
| Icon-only FAB without accessible name | Principle 1 / accessibility failure. |
| Extended FAB without visible label | Violates Extended FAB contract. |
| Hardcoded SVG as target pattern | Bypasses icon-system policy. |
| `<div role="button" class="ax-fab">` | Recreates native button behavior badly. |
| `aria-disabled` without event guard | Does not block activation. |
| FAB menu folded into FAB primitive | FAB menu is row #5 interaction component. |
| Scroll auto-hide treated as CSS-only static theme work | Behavior belongs to runtime/plugin territory. |
| Block style registration treated as Phase 1 scope | Phase 1 is documentation-only. |

---

## §9 — Behavior Pattern Deferrals

Deferred:

```txt
Extended FAB collapse/expand
FAB auto-hide on scroll
FAB-to-menu transition
modal/sheet morph
toolbar floating-with-FAB choreography
```

Disposition:

```txt
Potential future v3.5.x BACKLOG candidate.
Do not add in Phase 1 unless separately authorized.
```

This follows the Card #29 precedent: static primitive first, behavior-heavy patterns later.

---

## §10 — Mapping Verdict

| # | Criterion | Status | Notes |
|---:|---|:---:|---|
| 1 | Core/block inventory | PASS | core/button/buttons/navigation/social/template/pattern/custom block reviewed. |
| 2 | No-natural-core-block finding | PASS | core/button is weak/partial, not canonical. |
| 3 | Theme/plugin boundary | PASS | static placement vs runtime behavior separated. |
| 4 | Accessible-name contract | PASS | icon-only FAB requires accessible name; Extended requires label. |
| 5 | Anti-pattern inventory | PASS | 11 FAB-specific anti-patterns recorded. |
| 6 | Behavior deferrals | PASS | collapse/scroll/menu/toolbar deferred. |

---

## §11 — References

```txt
FAB-SPEC-AUDIT.md
FAB-MEASUREMENT-AUDIT.md
docs/v3.5.5/FAB-PHASE-0-REPORT.md
docs/v3.5.5/FAB-PHASE-1-PLAN.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
```
