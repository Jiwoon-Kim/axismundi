# Superseded ultrareview reports

> **Status**: not preserved as current authority.
> **Retained value**: methodology only.

## What was removed

The detailed ultrareview report from Phase 2B γ-3 (2026-05-08) targeted the static prototype:
- 6-axis audit (token consistency / logical properties / a11y / cross-page sync / theme.js wiring / wp.org compliance)
- 36 issues identified → 25 immediate fixes + 11 deferred → 26 patches applied

The concrete findings are no longer current authority because:

1. The audit target — the static prototype — was demoted to legacy archive in v3.3.0 (`products/_archive/axismundi-prototype/`). Future Axismundi authority is lab (active visual QA) and the pilot/distributable block themes that derive from it.
2. The 25 immediate fixes were already applied to the prototype code at the time of γ-3 closure. That code is preserved in the archive; the report does not add information beyond what the code itself records.
3. The 11 deferred items were absorbed into the project's ROADMAP or implicitly handled as the architecture evolved (e.g., wp_nav_menu centralization is now a Phase 3 PHP integration concern, not a "deferred fix").
4. Future re-validation will be performed against the lab + pilot + distributable as those stabilize. Treating pre-monorepo audit findings as current authority risks anchoring to a superseded code state.

## What was retained

The **audit methodology** is preserved because re-audits are planned. The methodology consists of:

### The 6-axis approach

| Axis | What it checks |
|---|---|
| A — Token consistency | Are CSS custom properties defined where used? No hex literals in components. |
| B — Logical properties | RTL-readiness. `inline-start` / `block-end` instead of `left` / `bottom` etc., except WP-mandated physical exemptions. |
| C — Accessibility | WCAG AA contrast, skip-links, landmark hierarchy, H1 singularity, radiogroup semantics. |
| D — Cross-page structural coherence | Header/footer/sidebar consistency across templates. |
| E — JS architecture | Single source of truth (theme.js IIFE pattern). No inline duplicates. Keyboard contracts. |
| F — wp.org compliance | License, style.css metadata, readme.txt, theme.json minimum, templates/ stub. |

### The 3-phase audit pattern (Phase 8 KB closure)

The Phase 8 KB build also produced 3 audits that target *the ontology itself*, not the code:

| Audit | Question | Outcome |
|---|---|---|
| M1 — Structural Audit | How wide does this apply? | Breadth coverage map |
| M2 — Grammar Audit | What exactly counts as a fit? | Precision rules with anti-patterns |
| M3 — Topology Audit | At what governance scale does this hold? | Geometry of where the rule still functions |
| P1 — Frontier Mapping | Where does the next genuinely new scale begin? | Forward strategic options |

This pattern is reusable for future re-audits of the design system or the bindings layer.

## When to re-audit

Triggers:
- **Before pilot promotion** — minimal axismundi-pilot/ should pass a focused re-audit before promotion to distributable
- **Before public push** — distributable theme should pass full 6-axis + accessibility + WP.org compliance before WordPress.org submission
- **After major architectural change** — e.g., when adding a second design system at `core/design-systems/<name>/`, audit the bindings layer
- **After Phase 9 distributable construction** — re-audit produces the current authority replacing this superseded report

When triggered, follow the methodology above. Do not consult the pre-monorepo audit results as authority; they target code that no longer exists in active form.

## Related preserved documents

- `cowork-phase8-kb-build-closure.md` — full Phase 8 audit methodology archive (M1/M2/M3 + P1)
- `gpt-ontology-framing-development.md` — audit-era ontology development narrative
- `claudechat-reports-consolidated.md` — historical timeline + meta-observation patterns useful for understanding why certain decisions were made

These together provide enough archeological evidence to reconstruct *how* re-audits should be conducted, without preserving stale *what* was found.
