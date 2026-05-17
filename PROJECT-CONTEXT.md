# PROJECT-CONTEXT.md — Axismundi long-horizon architecture

> **Purpose**: stable architectural reference. Read once per session. Should change rarely.
> **For volatile state** (current release / phase / next action): see `CURRENT-STATE.md`.
> **For canonical authority**: `CONSTITUTION.md` (12 articles) — this file is a digest, not the authority.
> **Last updated**: 2026-05-16 (v3.5.1 Phase 0.5 — Root Context Pack)

---

## What Axismundi is

Axismundi is **not a WordPress theme**. It is an **ontology-driven layered architecture** for building WordPress block themes against interchangeable design systems, with explicit translation layers and provenance.

Current scope spans:

- Design system (Material Design 3 as current design layer)
- WordPress block theme generation (M3 → theme.json + block patterns)
- Lab implementation surface (validated patterns before public exposure)
- Future: ActivityPub microblog / federated social CMS surface
- Future: M3 Interpreter plugin (binding-driven plugin generation)

**Author**: KIM Ji-woon (Seoul). Personal project. LLMs (GPT, Claude, Gemini) are amplification tools; all architectural / strategic / value decisions are the author's. See `AUTHORSHIP.md` for decision territory.

**Language posture**: Korean is first-class. Bilingual policy text (EN + KO) is the convention for normative statements.

---

## A–F architecture (Constitution Article 1)

Six conceptual layers, each with distinct authority:

```
A. Corpus     corpus/       source-of-truth (upstream raw + refined docs)        근거층
B. Atlas      atlas/        rule-based knowledge (DDD-partitioned)               판단층
C. Core       core/         formal ontology (typed JSON-LD)                      구조층
D. Bindings   bindings/     cross-ontology translation (confidence-scored)       번역층
E. Products   products/     reference implementations + distributables           산출층
F. Tools      tools/        builders, validators, generators                     자동화층
```

Pipeline:

```
A Corpus → B Atlas → C Core → D Bindings → E Products
                                              ↑
                                         F Tools (validates / builds / publishes A–E)
```

These layers are **not collapsible**. Each layer has its own form of correctness; reducing one to another loses information. See `CONSTITUTION.md` Article 1 for authoritative definitions and Article 4 for the F-layer caveat ("tools operate on layers; tools are not a layer").

---

## v3.5.0 — Public Surface Reframe (framework release)

**Status**: frozen 2026-05-15. Five policy documents under `docs/v3.5.0/` define the operating rules for all subsequent Wave 1+ component work.

### 3-axis component ontology

Every component entry in the canonical matrix has three independent axes:

1. **TOC Group** (8 functional families): Foundation / Actions / Containers / Navigation / Inputs / Selection / Feedback / Display
2. **Category** (4 module shapes): Component Full-Spec / Interaction Runtime / Baseline-only Record / Plugin-territory Mapping
3. **Dependency** (consumer/provider edges): infrastructure consumed (`ripple/`, `icon-system/`, `popover/`) + state

These three axes are independent. A component's TOC Group does not determine its Category; its Category does not determine its dependencies.

### 4-tier architecture

```
Public surface        ← styleguide/ (mirror) + index.html
                          ↑
Lab surface           ← products/reference-implementations/axismundi-lab/
                          ↑
Baseline + Plugin     ← components.css + bindings/wordpress-material3/
```

Each tier has surface-specific meaning locked in `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md`:

- `style-guide.html` = **baseline catalog**, NOT final app
- `components.css §1–§34` = **visual primitive source**, NOT runtime
- `lab/modules/*` = **validation surface**, NOT public contract
- `bindings/` = **plugin-territory mapping**

### 37-entry canonical matrix

- 34 TOC component rows + 3 infrastructure provider rows
- 12 columns per row (incl. Dep Type / Provider / Consumers axis)
- Status distribution at v3.5.0 freeze: **3 DONE** (Chip, Snackbar, Tooltip) / **4 PARTIAL** (Icon button, Search bar, Date+Time, Carousel) / **24 TODO** / **3 RECORD** (Avatar, Divider, Badge)
- Canonical file: `docs/v3.5.0/MODULE-STATUS-MATRIX.md`

### DISTINCT but COUPLED principle

Infrastructure providers (`ripple/`, `icon-system/`, `popover/`) and their consumers are **separate modules** with **explicit contracts**, not collapsed dependencies. Stated in `PUBLIC-SURFACE-CHARTER.md` §Infrastructure dependency principle (EN + KO + WAI-ARIA APG Menu Button pattern alignment).

`MAY` / `MUST NOT` enforcement language is spelled out in `docs/v3.5.0/PROMOTION-CRITERIA.md`.

### G1–G26 validation gates

Universal (G1–G10) + category-specific (G11–G20) + infrastructure (G21–G26). Component releases must pass applicable gates before promotion. Defined in `docs/v3.5.0/PROMOTION-CRITERIA.md`.

---

## Wave structure

Wave 1+ component releases are scheduled as discrete mini-releases under v3.5.x. Wave 1 contains 9 entries (3 PARTIAL leverage + others). Wave 2 contains 14 entries (largest). Wave 3 contains 3 entries (smallest). See `docs/v3.5.0/COMPONENT-COVERAGE-MAP.md` Map 2.

Each Wave item follows the same release pipeline:

```
Phase 0 — Inventory + dependency scan + risk identification
Phase 1 — Audit doc bodies (3 docs: SPEC + MEASUREMENT + WP-MAPPING)
Phase 2 — Implementation (lab CSS / pattern HTML / optional JS)
Phase 3 — Static Visual QA Gate (10-point checklist)
Phase 5 — Mechanical close (CHANGELOG / ROADMAP / BACKLOG / memory / package)
```

(Phase 4 is reserved for category-specific deeper QA when applicable — e.g., a11y audits, runtime tests.)

Reference template: `lab/modules/chip/docs/CHIP-*-AUDIT.md` (v3.4.9 — first Component Full-Spec module under the v3.5.0 framework).

---

## Workflow orchestration (multi-agent)

```
User (Ji-woon)   Direction · Philosophy · Final decisions · Ontology authority
GPT              Strategy review · Risk control · Cross-model evaluation
Claude Opus      High-context execution (this lane: Cowork + Local Claude Code)
Codex            Plan-first executor · Local mechanical patches · Validator gate
Claude Design    Prototype / reference surface (separate lane, doesn't pollute core)
```

Principle: **judgment and execution are separated.** Execution agents (Opus, Codex) run validators after every change. GPT reviews execution output. User has final authority.

**Single source of truth** is the repo's documented context pack, not chat memory:

```
CLAUDE.md         AGENTS.md           ← agent-specific operational rules
PROJECT-CONTEXT.md (this file)        ← long-horizon architecture
CURRENT-STATE.md                      ← volatile current state
NEXT-SESSION.md                       ← next-session execution plan + Codex queue
CONSTITUTION.md                       ← canonical authority (12 articles)
BACKLOG.md  CHANGELOG.md  ROADMAP.md  ← release mechanics
docs/v3.5.0/*  docs/v3.5.1/*          ← framework + Wave docs
```

---

## Quick orientation by question

| Question | Authoritative source |
|---|---|
| What are the six layers? | `CONSTITUTION.md` Article 1 |
| What's a component module supposed to look like? | `docs/v3.5.0/PROMOTION-CRITERIA.md` (category criteria) |
| Which components are DONE / PARTIAL / TODO? | `docs/v3.5.0/MODULE-STATUS-MATRIX.md` |
| How do infrastructure modules relate to consumers? | `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md` (DISTINCT but COUPLED section) + `docs/v3.5.0/COMPONENT-COVERAGE-MAP.md` Map 3 |
| What's a publishing surface vs. an authority? | `CONSTITUTION.md` Article 12 |
| What were the v3.5.1 Button Phase 0 findings? | `docs/v3.5.1/BUTTON-PHASE-0-REPORT.md` |
| Who decided what? | `AUTHORSHIP.md` (decision tiers) |

---

End of file. Read `CURRENT-STATE.md` next.
