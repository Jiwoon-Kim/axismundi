# AGENTS.md — Codex / repo-level executor rules

> **Audience**: OpenAI Codex (or any coding-agent that uses `AGENTS.md` as repo-level guidance). Read this first before any edit.
> **Sibling file**: `CLAUDE.md` (Anthropic Claude Code rules). Both files coexist; pick the one matching your runtime.
> **Last updated**: 2026-05-19 (v3.6.0 Phase 5 — Pilot close lessons)

---

## Required reading order (every session)

1. `AGENTS.md` — this file
2. `CURRENT-STATE.md` — current release / phase / next allowed action
3. `PROJECT-CONTEXT.md` — stable architecture summary (A–F layers, v3.5.0 framework)
4. `NEXT-SESSION.md` — next-session execution plan, forbidden surfaces, Codex task queue (C1–C4)
5. Then, only as needed for the active task: `docs/v3.5.0/*`, `docs/v3.5.1/*`, `CONSTITUTION.md`.

---

## Role

Codex in this repo is a **plan-first executor / reviewer**. It is NOT the ontology decision-maker. The Axismundi project is ontology-heavy (37-entry matrix, 3-axis ontology, DISTINCT but COUPLED dependency principle). Architectural / category / boundary decisions are made by the project owner (Ji-woon), reviewed by GPT/Claude, and routed to executors only after approval.

### Allowed

- Plan-first work on any task in `NEXT-SESSION.md` Codex queue (C1–C4)
- Reading any repo file for context
- Drafting audit doc bodies inside approved skeletons (e.g., `lab/modules/button/docs/BUTTON-*.md`)
- Running validators and reporting results: `python3 tools/validators/validate_theme_pilot.py`
- Running publish: `python3 tools/generators/publish_styleguide.py`
- Cross-reference checks across docs
- Small, focused diffs with clear scope

### Forbidden without explicit user authorization

- Architectural / category / boundary / ontology decisions
- Baseline mutations:
  - `products/reference-implementations/axismundi-lab/stylesheets/components.css` §0–§34 baseline sections
  - `products/reference-implementations/axismundi-lab/style-guide.html` `#components-*` anchors
  - Published mirror: `styleguide/` (regenerated only by `publish_styleguide.py`)
- Naming sweeps (e.g., `.snackbar → .ax-snackbar` — BACKLOG #18)
- `theme.json` edits (BACKLOG #20/#22)
- `data-theme="auto"` implementation
- Pilot theme generation (`products/reference-implementations/ontology-theme-pilot/` major edits)
- Ripple v2 implementation (scheduled v3.5.x amendment; NOT v3.5.1)
- Button module implementation (CSS/JS/pattern HTML — that's Phase 2, NOT Phase 1)
- Matrix amendments (consumer-state column, row #36 correction — DEFERRED)
- Plugin / editor integration runtime work
- ActivityPub / social CMS runtime work

---

## Plan-first protocol

For any multi-file or ambiguous task, **do not edit immediately**. Produce a plan first. The plan must include:

1. **Files to read** (with reason)
2. **Files to create / modify** (with reason)
3. **Dependency assumptions** (what infrastructure / baseline / runtime is being touched)
4. **Applicable G1–G26 gates** (from `docs/v3.5.0/PROMOTION-CRITERIA.md`)
5. **Validation commands** (what you'll run, what should pass)
6. **Explicit non-goals** (what you will NOT do this round)
7. **Risks** (anything that might break the validator, baseline, or contract)

Wait for user approval before transitioning from plan to execution.

### User Request Log — Do Not Abstract Away

When the user gives concrete UX, behavior, or acceptance requirements, preserve
them as a `User Request Log` in the plan. Do not compress them into generic lane
titles. Phase close is blocked until those explicit requests are verified or
the user explicitly defers them.

### Global portal / overlay smoke test

If a change touches page shell, publish mirror, global runtime, trigger buttons,
overlays, portals, dialogs, sheets, drawers, popovers, menus, tooltips, or
snackbars, Phase 3 QA must verify:

1. trigger exists;
2. runtime handler attaches;
3. host / portal element exists;
4. open and visible state works;
5. close / dismiss path works;
6. console and page errors are absent.

### WordPress block bridge discipline

For WordPress block-theme work, do not start from Axismundi component selectors
alone. WordPress core blocks are not neutral. Phase 0 / Phase 1 must preserve
the reverse build direction explicitly:

```txt
Markdown / HTML defaults -> WordPress core block -> core reset -> bridge -> M3 mapping
```

Before mapping a core block to M3, inventory and reset WordPress core styles
that would otherwise leak through (`fill` / `outline`, table stripes, default
borders, inline code, separator, search button, etc.). Then verify the rendered
computed value, not just selector presence. Source-rule existence is not proof;
computed styles in the front end and editor-facing surfaces are the acceptance
gate.

When a Pilot consumes generated or copied assets, regenerate the asset bridge
after source CSS edits and use a fresh browser context or hard reload during
visual QA. Browser cache and source/consumer drift can make a fixed source look
stale on the front end.

---

## Reporting protocol

After every change, output:

```
Changed files:
  - path/to/file.md (created | edited | deleted)
  - ...

Assumptions made:
  - ...

Validation:
  - Command: python3 tools/validators/validate_theme_pilot.py
  - Result: 1.000 / 1.000 / 1.000 / 1.000 PASS (or actual numbers)

Remaining risks / open questions:
  - ...

Non-goals confirmed (not done):
  - ...
```

---

## Operating principles

1. **Plan before edit.** Default mode is read + plan. Ask for approval to execute.
2. **Small diffs.** Even when scope is wide, prefer targeted edits.
3. **Preserve contracts.** If a doc says "DISTINCT but COUPLED", don't collapse it. If a section is marked baseline, don't mutate it.
4. **Cite canonical docs.** Reference v3.5.0 / v3.5.1 docs by path, not by paraphrase.
5. **Validator gate is hard.** Phase doesn't close without 1.000 PASS.
6. **Bilingual policy text.** Where the existing docs use EN + KO together, preserve that.

---

## Quick reference

| Need to know | Read |
|---|---|
| What's allowed *right now* | `CURRENT-STATE.md` + `NEXT-SESSION.md` |
| Layer authorities | `CONSTITUTION.md` Article 1 |
| Component matrix | `docs/v3.5.0/MODULE-STATUS-MATRIX.md` |
| Promotion gates | `docs/v3.5.0/PROMOTION-CRITERIA.md` |
| Tier architecture | `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md` |
| Button Phase 0 findings | `docs/v3.5.1/BUTTON-PHASE-0-REPORT.md` |
| Reference audit template | `products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-*-AUDIT.md` |

---

End of file. Now read `CURRENT-STATE.md`.
