# Pre-monorepo external analysis reports

This directory preserves external (GPT, ClaudeChat) analysis reports authored during the pre-monorepo and early-monorepo eras. They are not part of the monorepo's source authority — they are historical artifacts useful for understanding the project's intellectual development.

## What lives here

Reports from external sessions that were instrumental in shaping Axismundi's structure:

- **gpt-phase-8-closure.md** — Doctrinal Era closure, productization recommendation
- **gpt-ontology-framing-development.md** — Ontology evolution: style guide → product compiler substrate
- **gpt-backup-archive-policy.md** — Workstation backup/archive structure recommendation
- **cowork-phase8-kb-build-closure.md** — Phase 8 KB build closure (Cowork session, 2026-05-10). ~103 chunks across 14 bounded contexts. 23 meta docs. Constitution v2 (6 Laws + 6 Doctrines + Section X). 3 audits (M1/M2/M3) + 1 frontier map (P1). The most precise record of *what was built* during the KB era.
- **cowork-kb-operating-rules/** — *How* the KB was built. 11 numbered chunk-authoring rules, vision doc, language + layer separation rules. The procedural doctrine. **Still live for future KB extension** (ActivityPub, additional design systems, alternate platform ontologies). See subfolder README for current-monorepo mapping.
- **claudechat-reports-consolidated.md** — Three ClaudeChat session reports condensed (Phase 2B β / γ-2 / δ-2). Historical timeline + meta observation patterns. Audit-specific findings deferred to SUPERSEDED-ULTRAREVIEW.md.
- **SUPERSEDED-ULTRAREVIEW.md** — Methodology summary; concrete findings of pre-monorepo ultrareview are no longer authority (the prototype they targeted is archived). Re-audit triggers + 6-axis approach + Phase 8 M1/M2/M3 pattern preserved for future use.

## Why preserved

These reports synthesize 35+ chunks of doctrinal analysis, multiple ultrareview cycles, visual QA iterations, and architectural decisions that informed the current monorepo structure. The decisions they document are encoded in:

- `CONSTITUTION.md` (12 articles)
- `core/design-systems/material3/DESIGN-DOCTRINE.md` (6 doctrines + 8 locked decisions)
- `bindings/_spec/binding-schema.md`
- `atlas/material/icon-font-scope-policy.md`
- Various caveat documents (especially Phase 1B caveat 9.10)

The reports themselves are not authority — they are *records of how the authority came to be*.

## Authority relationship

Authority flows top → bottom:
```
PROJECT-REPORT.md (consolidated current report)
       ↑
CONSTITUTION.md + DESIGN-DOCTRINE.md (encoded decisions)
       ↑
(this directory: source analyses that produced those decisions)
```

When reading historical reports here, the current `PROJECT-REPORT.md` and Constitution are the present-day authoritative interpretation of what those reports concluded.

## Format note

Reports are preserved as-is in their original markdown format, including their original section structure and language conventions (mixed Korean + English, GPT-style headers, etc.). They are not edited for consistency. Editing would compromise their archival value.
