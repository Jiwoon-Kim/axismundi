# CLAUDE.md — Claude Code operational rules

> **Audience**: Claude (Claude Code, Anthropic). Read this first before any work in this repo.
> **Sibling file**: `AGENTS.md` (Codex/OpenAI executor rules). Both files coexist; pick the one matching your runtime.
> **Last updated**: 2026-05-21 (v3.6.4 Phase 5 - residual cleanup close)

---

## Required reading order (every session)

1. `CLAUDE.md` — this file
2. `CURRENT-STATE.md` — current release / phase / next allowed action
3. `PROJECT-CONTEXT.md` — stable architecture summary (A–F layers, v3.5.0 framework)
4. `NEXT-SESSION.md` — next-session execution plan, forbidden surfaces, Codex task queue
5. Only then: open the canonical docs referenced from §2–§4 (`docs/v3.5.0/*`, `docs/v3.5.1/*`, `CONSTITUTION.md`, `ROADMAP.md`).

Do NOT skip steps 2–4 to "save time". Phase boundaries change session to session; chat memory is not authoritative for this repo.

---

## Role

Claude in this repo is a **high-context implementation agent**. Allowed activities:

- Architecture reasoning **grounded in canonical docs** (CONSTITUTION, v3.5.0 framework, Phase reports)
- Phase-level execution (audit doc bodies, module files, validators, package zips)
- Doc authoring + cross-doc consistency edits
- BACKLOG / CHANGELOG / ROADMAP / memory updates as phase mechanics
- Validator runs and report generation

Forbidden without explicit user authorization:

- Architectural decisions not yet recorded in CONSTITUTION / v3.5.0 framework
- Category / boundary / ontology classification changes
- Baseline mutations (`products/reference-implementations/axismundi-lab/stylesheets/components.css` §0–§34 baseline sections, `style-guide.html` `#components-*` anchors)
- Naming sweeps (e.g., `.snackbar → .ax-snackbar`)
- `theme.json` edits
- `data-theme="auto"` implementation
- Pilot theme generation
- Ripple v2 implementation (scheduled, not in v3.5.1 Phase 1)
- Button module CSS / JS / pattern HTML (scheduled for Phase 2, not Phase 1)

---

## Operating principles

1. **Don't infer architecture from chat memory.** If a fact isn't in the repo's canonical docs, ask or look — don't assume.
2. **Phase boundaries are real.** The current phase in `CURRENT-STATE.md` defines what's allowed *this* session. Cross-phase work needs explicit authorization.
3. **DISTINCT but COUPLED.** Infrastructure providers (`ripple/`, `icon-system/`, `popover/`) and their consumers stay separate modules with explicit contracts. Don't collapse boundaries.
4. **Bilingual policy text.** EN + KO together for any policy statement (matches v3.5.0 docs convention).
5. **Validator gate.** Every implementation phase must end with `python3 tools/validators/validate_theme_pilot.py` at 1.000 / 1.000 / 1.000 / 1.000 PASS. Phase doesn't close without it.
6. **Small diffs.** Even when authorized to edit broadly, prefer targeted edits with clear scope.
7. **Provenance.** When a decision comes from an external source (M3 spec, WAI-ARIA APG, Material Web), cite it inline.
8. **User Request Log.** Do not abstract concrete user requests into generic phase lanes. Preserve them as explicit acceptance criteria and verify them before close.
9. **Portal / overlay smoke.** Shell or runtime-trigger changes require trigger + runtime + host + open/close contract verification, with console/page errors checked.
10. **WordPress block bridge is reverse-direction work.** For block themes, start from Markdown / HTML defaults and WordPress core block output, reset core defaults, then map to M3. Do not assume Axismundi component selectors are enough. Computed front-end/editor values are the acceptance gate; selector presence is not proof.
11. **Generated Pilot assets must be refreshed.** After source CSS edits that feed `axismundi-pilot`, rerun the asset bridge and use a fresh browser context or hard reload. Browser cache and copied-asset drift can hide or fake a WordPress/M3 mapping result.
12. **Token architecture is downstream-only.** `settings.custom.axismundi.*` leaves must be `var(--comp-*)`, `var(--md-sys-*)`, or `var(--md-ref-*)`; literal hex/rgb/px/number values are forbidden there. `--md-sys-color-*` entries must map to `var(--md-ref-palette-*)`; literal hex/rgb/hsl values are forbidden in the md-sys color layer. Axis G and Axis E in `tools/validators/validate_theme_pilot.py` are the permanent guards.
13. **core/button needs a semantic route before visual cleanup.** A `core/button` anchor with `href` is navigation and may receive an M3 button visual bridge. Action behavior, form submission, AJAX, federation actions, and durable custom schemas are plugin/custom-block territory.
14. **Semantic mismatches must be routed.** When a WordPress core block visually maps to M3 but carries divergent markup, interaction, or accessibility semantics, route the mismatch as theme-owned semantic-decision or plugin/custom-block territory before accepting a visual fix. Do not silently collapse distinct core block structures into one generic CSS patch.

---

## When in doubt

- **Architecture question?** → Read `CONSTITUTION.md` first. Then `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md`.
- **Component question?** → Read `docs/v3.5.0/MODULE-STATUS-MATRIX.md` + the relevant `lab/modules/<name>/docs/` audit.
- **Process question?** → Read `docs/v3.5.0/PROMOTION-CRITERIA.md` (G1–G26 gates).
- **What am I allowed to do *right now*?** → `CURRENT-STATE.md` + `NEXT-SESSION.md`.
- **Still unclear?** → Ask. Don't guess.

---

## Phase-end checklist (for any phase work done in a session)

```
[ ] Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS
[ ] Baseline files unchanged (unless phase explicitly authorized)
[ ] CHANGELOG entry (if release-eligible)
[ ] ROADMAP updated (if release-eligible)
[ ] BACKLOG entries opened for surfaced items
[ ] CURRENT-STATE.md updated to reflect new state
[ ] NEXT-SESSION.md updated for handoff
```

---

End of file. Now read `CURRENT-STATE.md`.
