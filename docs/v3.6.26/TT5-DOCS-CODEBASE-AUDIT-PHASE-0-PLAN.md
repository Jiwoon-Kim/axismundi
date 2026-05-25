# v3.6.26 Phase 0 Plan — TT5 Docs + Codebase Audit

**Status:** Phase 0 — Plan Only (Reviewer-approved)
**Cycle:** v3.6.26 / 16th overall / 11th impl-cycle
**Variant:** narrow documentation/ontology audit
**Date:** 2026-05-25

---

## Verdict

Proceed to Phase 0 plan-first only.

v3.6.26 audits TT5 as a reference corpus and implementation comparator,
not as a template source to copy. The purpose is to test the v3.6.25
Decision Matrix against a real WordPress default theme and decide what
TT5 can safely inform before any Pilot template or Google Sites extraction
work begins.

---

## Scope

Phase 0 prepares a read-only audit of the following TT5 surfaces:

1. TT5 template hierarchy
2. TT5 patterns / parts / styles / theme.json structure
3. TT5 documentation and readme framing
4. TT5 selector/schema patterns relevant to Axismundi
5. TT5 release-readiness artifacts
6. TT5 boundaries: what is reusable evidence vs non-authoritative design choice

### Local TT5 File Inventory (confirmed 2026-05-25)

Base path: `../twentytwentyfive.1.5/twentytwentyfive/`
(relative to axismundi repo root: `C:\Users\thaum\dev\twentytwentyfive.1.5\twentytwentyfive\`)

| Surface | Count | Files |
|---|---|---|
| templates/ | 8 | 404, archive, home, index, page-no-title, page, search, single |
| parts/ | 7 | footer, footer-columns, footer-newsletter, header, header-large-title, sidebar, vertical-header |
| patterns/ | 98 | (full list deferred to Phase 1) |
| styles/ (variations) | 8 | 01-evening through 08-midnight |
| styles/blocks/ | 4 | display, subtitle, annotation, post-terms-1 |
| styles/sections/ | 5 | section-1 through section-5 |
| styles/typography/ | 7 | typography-preset-1 through -7 |
| styles/colors/ | 8 | matching variation slugs |
| Root files | — | theme.json, style.css, style.min.css, functions.php, readme.txt, screenshot.png |

### Official Documentation Source (confirmed 2026-05-25)

- URL: https://wordpress.org/documentation/article/twenty-twenty-five/
- Authority class: official_source for TT5 user-facing theme behavior
- Not source authority for Axismundi visual design or implementation copying

Key surfaces described in official doc:
- Quick specs: WordPress 6.7+, PHP 7.2+
- Site editing: Site Editor, Styles, Templates, Patterns
- Template design families: Personal Blog, Text-Only Blog, Photo Blog, News Blog + additional
- Style variations: 9 variations + palette/font-pair presets
  (Default = theme.json root + 8 named JSON files: Evening, Noon, Dusk, Afternoon, Twilight, Morning, Sunrise, Midnight;
  local `styles/` has 8 files — not a discrepancy, default is theme.json itself)
- Patterns: 70+ patterns (section patterns, full-page/landing patterns)
- Section styles: Group/Columns section styles with accessible color combinations
- Text styles: Display / Subtitle / Annotation
- Block style variations: e.g. list checkmark, pill-shaped tags
- Post Formats: all post formats + Query Loop filtering guidance

### Primary Axismundi Input Files

```
corpus/webdesign-craftsman-written/matrix-seed.md
atlas/web-production-workflow/*
core/web-production/web-production-ontology.md
docs/v3.6.25/*
```

---

## Non-Goals

Phase 0 must not:

1. Modify Pilot templates.
2. Copy TT5 layout, CSS, patterns, or theme.json values into Axismundi.
3. Create distributable skeleton files.
4. Start Google Sites extraction.
5. Create a `decisions/` layer.
6. Promote M16 or M17 unless Phase 1/2 produces the second evidence cycle.
7. Treat PMBOK / MaRMI / SWEBOK as implementation authority.
8. Touch styleguide, runtime, products, bindings, or release artifacts.

---

## Phase 0 Questions

### Primary audit questions

**Q1.** Which TT5 surfaces are useful for audit: templates, parts, patterns, styles, theme.json, docs?

**Q2.** Which TT5 structures map cleanly to v3.6.25 matrix rows?

**Q3.** Which TT5 findings are selector/schema evidence rather than visual design authority?

**Q4.** Does TT5 provide evidence for M16 promotion by blocking over-modeling?

**Q5.** Does TT5 provide evidence for M17 promotion by reusing the layered matrix schema?

**Q6.** What should Phase 1 produce: diagnostic report only, comparison matrix, or routed decision report?

**Q7.** What evidence gate must exist before Pilot template implementation can consume TT5 findings?

### Official documentation questions (added from WordPress.org source)

**Q8.** How does the official TT5 documentation describe intended user-facing behavior, and where does that differ from code-level theme structure?

**Q9.** Which doc-described surfaces should be audited against local TT5 files: templates, patterns, style variations, section styles, text styles, block style variations, post formats, and Query Loop usage?

**Q10.** Which findings are official behavior evidence, and which require local code confirmation before becoming Axismundi matrix rows?

---

## Source Boundary

```
Official WordPress.org doc  →  behavior/documentation evidence
Local TT5 code              →  schema/selector/template evidence
Axismundi v3.6.25 matrix    →  evaluation frame
```

No direct copying from either doc or code into Pilot implementation during Phase 0.

---

## Expected Audit Matrix Schema

Uses v3.6.25 layered base fields with a TT5 extension:

### Base fields (from v3.6.25)

```
matrix_id
source_section
source_confidence
workflow_stage
decision_point
input_evidence
output_artifact
Axismundi_route
retention_policy
notes
```

### TT5 extension fields

```
TT5_surface
file_path
WordPress_surface
template_hierarchy_role
selector_or_schema_pattern
reusable_as
blocked_from_copying
Pilot_relevance
audit_disposition
```

---

## Routes

| Route | Description |
|---|---|
| A | Read-only Phase 1 diagnostic only |
| B | TT5 comparison matrix only |
| C | TT5 comparison matrix + atlas route notes |
| D | No-code layered decision report |
| E | Pilot implementation prep, but no implementation |

**Recommended:** Route C or D, depending on Phase 1 evidence.

Phase 1 should first be a read-only diagnostic that proves whether TT5
contributes reusable selector/schema evidence or only general reference
value. Route is fixed only after Q6 is answered.

---

## Memory Watch Gates

### M16 WATCH — web-production core ontology (12 entities + 7 non-entities)

Promote only if TT5 audit actually uses the 12 entities + 7 non-entities
to block over-modeling, especially PMBOK / MaRMI / SWEBOK / TT5 as full
core ontologies. Single-evidence state; this cycle may supply the second.

### M17 WATCH — layered Decision Matrix schema (base + section extension)

Promote only if the TT5 audit reuses the layered Decision Matrix schema
on a second corpus with clear practical value. Single-corpus state; this
cycle is the candidate second application.

---

## Phase 0 Exit Criteria

- [x] This file committed at `docs/v3.6.26/TT5-DOCS-CODEBASE-AUDIT-PHASE-0-PLAN.md`
- [x] Local TT5 file inventory verified (done: see table above)
- [x] Official WordPress.org source boundary documented (done: see above)
- [ ] Phase 1 entry: read-only diagnostic, no implementation

Phase 1 may begin immediately after this file is committed.
Phase 1 is read-only except for its report output.

---

## Reviewer Notes (from Phase 0 approval)

**R1 — Local TT5 file existence:** Confirmed 2026-05-25. All surfaces present.
See inventory table above. R1 resolved.

**R2 — Route decision deferred:** C vs D to be determined after Phase 1
diagnostic. This file records "recommended C or D" only.

**R3 — Q6 = Phase 1 exit gate:** Phase 1 first task is Q1–Q5 answer draft,
then Q6 judgment, then route decision.

**R4 — M16 activate observation:** If TT5 audit encounters an attempt to
adopt TT5 as a full Axismundi core ontology (alongside PMBOK/MaRMI/SWEBOK),
that event is the M16 promote trigger. No such attempt = watch continues.

---

## Citation

WordPress.org. (n.d.). *Twenty Twenty-Five*.
WordPress Documentation. https://wordpress.org/documentation/article/twenty-twenty-five/
