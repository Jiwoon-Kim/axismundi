# NEXT-SESSION.md - Post-v3.6.24 Handoff

> **Status**: v3.6.0-v3.6.24 are closed. The latest closed cycle is v3.6.24
> Core Block Style Guide Full Spec, which completed the v3.6.18 routed-forward
> catalog work across v3.6.23 + v3.6.24.
> **Use**: read at the start of the next Codex/Claude session.
> **Last updated**: 2026-05-24.

---

## 0) Current Reading Order Addendum

Read these current sources before using the historical reading order below:

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entries v3.6.24 / v3.6.23 / v3.6.22
5. ROADMAP.md current tail
6. BACKLOG.md #21 / #22 / #44 / #46 / #47 / #14
7. docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-5-CLOSE.md
8. docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-3-VERIFICATION.md
9. docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-2-IMPLEMENTATION.md
10. docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-1-REPORT.md
11. docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-0-PLAN.md
12. docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-5-CLOSE.md
13. docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-3-VERIFICATION.md
14. docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-2-IMPLEMENTATION.md
15. docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-1-REPORT.md
16. docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-0-PLAN.md
17. docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-5-CLOSE.md
18. docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-3-VERIFICATION.md
19. docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-2-IMPLEMENTATION.md
20. docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-1-REPORT.md
21. docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-0-PLAN.md
22. docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-5-CLOSE.md
```

The next route is not distributable skeleton yet. Follow the current order:

```txt
cycle-external: WP block styleguide human visual QA
v3.6.25:       Webdesign decision matrix ontology
v3.6.26:       TT5 docs + codebase audit
v3.6.27:       Pilot template implementation pass + Google Sites extraction
v3.6.28+:      distributable skeleton bootstrap, only with explicit user slug GO
```

## 0a) Historical Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #21 / #22 / #44 / #46 / #47 / #14
7. docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-5-CLOSE.md
8. docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-3-VERIFICATION.md
9. docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-2-DECISION.md
10. docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-1-REPORT.md
11. docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-0-PLAN.md
12. docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-5-CLOSE.md
13. docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-3-VERIFICATION.md
14. docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-2-DECISION.md
15. docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-1-REPORT.md
16. docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-0-PLAN.md
17. docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-5-CLOSE.md
18. docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-3-VERIFICATION.md
19. docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-2-DECISION.md
20. docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-1-REPORT.md
21. docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-0-PLAN.md
22. docs/ASSET-SURFACE-INDEX.md
23. docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-5-CLOSE.md
24. docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-3-VERIFICATION.md
25. docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-2-DECISION.md
26. docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-1-REPORT.md
27. docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-0-PLAN.md
28. docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-5-CLOSE.md
29. docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-3-VISUAL-QA.md
30. docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-2-DECISION.md
31. docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-1-REPORT.md
32. docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-0-PLAN.md
33. docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-5-CLOSE.md
34. docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-3-VERIFICATION.md
35. docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-2-IMPLEMENTATION.md
36. docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-1-REPORT.md
37. docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-0-PLAN.md
38. docs/v3.6.15/VS-CODE-DIAGNOSTICS-SWEEP-PHASE-5-CLOSE.md
39. docs/v3.6.15/VS-CODE-DIAGNOSTICS-SWEEP-PHASE-1-REPORT.md
40. docs/v3.6.15/VS-CODE-DIAGNOSTICS-SWEEP-PHASE-0-PLAN.md
41. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-5-CLOSE.md
42. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-3-VISUAL-QA.md
43. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-2-REPORT.md
44. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-1-REPORT.md
45. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-0-PLAN.md
46. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-5-CLOSE.md
47. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-3-VISUAL-QA.md
48. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-2-REPORT.md
49. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-1-REPORT.md
50. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-0-PLAN.md
51. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-5-CLOSE.md
52. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-3-VISUAL-QA.md
53. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-2-REPORT.md
54. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-1-REPORT.md
55. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-0-PLAN.md
56. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-5-CLOSE.md
57. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-3-VISUAL-QA.md
58. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-2-REPORT.md
59. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-1-REPORT.md
60. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-0-PLAN.md
61. docs/v3.6.10/WAVE-2B-FORM-PHASE-5-CLOSE.md
62. docs/v3.6.10/WAVE-2B-FORM-PHASE-3-VISUAL-QA.md
63. docs/v3.6.10/WAVE-2B-FORM-PHASE-2-REPORT.md
64. docs/v3.6.10/WAVE-2B-FORM-PHASE-1-REPORT.md
65. docs/v3.6.10/WAVE-2B-FORM-PHASE-0-PLAN.md
66. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-5-CLOSE.md
67. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-3-VISUAL-QA.md
68. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-2-REPORT.md
69. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-1-REPORT.md
70. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-0-PLAN.md
71. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
72. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-3-VISUAL-QA.md
73. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
74. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-1-REPORT.md
75. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-0-PLAN.md
76. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
77. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
78. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
79. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
80. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
81. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
82. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
83. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
84. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
85. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
86. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
87. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
88. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
89. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
90. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
91. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
92. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
93. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
94. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
95. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
96. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
97. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
98. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
99. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
100. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
101. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
102. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
103. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
104. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
105. bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
106. docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
```

Repo docs remain authority. Chat is relay, not source of truth.

Default relay ownership:

```txt
Codex:
  implementation files and phase plan/report docs

Opus/Claude:
  review findings only, preferably as user-relayed text or
  docs/<cycle>/*-review.md if repo-based handoff is requested
```

## 1) Current State

```txt
v3.5.18  Pre-Pilot cleanup + Carousel reroute       CLOSED
v3.6.0   Ontology Theme Pilot v0                    CLOSED
v3.6.1   Token Architecture Refactor                CLOSED
v3.6.2   WP Core Block Specimen Wall                CLOSED
v3.6.3   WP Block Bridge Expansion                  CLOSED
v3.6.4   WP Block Bridge Residual Cleanup           CLOSED
v3.6.5   WP Block Bridge Editor Token Parity        CLOSED
v3.6.6   WP Block Bridge Ripple / Editor State Parity CLOSED
v3.6.7   WP Specimen Follow-On Editor Compatibility CLOSED
v3.6.8   Wave 2A Navigation Core                    CLOSED
v3.6.9   Wave 2A-2 Menu / Popover Consumer          CLOSED
v3.6.10  Wave 2B-1 Form Controls                    CLOSED
v3.6.11  Wave 2B-2 Dialog / Sheet                   CLOSED
v3.6.12  Wave 2B-3 DateTime                         CLOSED
v3.6.13  Wave 2B-4 Actions Consumers                 CLOSED
v3.6.14  Wave 3 Closure - Inputs / Feedback Final   CLOSED
v3.6.15  VS Code Diagnostics Sweep                   CLOSED
v3.6.16  Lab A11y Diagnostics Fix Sweep              CLOSED
v3.6.17  WP Ripple Runtime Packaging Decision        CLOSED
v3.6.18  Core Block Mapping Audit                    CLOSED
v3.6.19  Asset Surface Audit + Cross-Reference Index CLOSED
v3.6.20  Pilot vs Distributable Bootstrap            CLOSED
v3.6.21  Theme Switcher Contract                     CLOSED
v3.6.22  Explicit data-theme auto root state          CLOSED
v3.6.23  Core Block Catalog 6-Category Split          CLOSED
v3.6.24  Core Block Style Guide Full Spec             CLOSED

Next route:
  Cycle-external WP block styleguide human visual QA first.
  Then start v3.6.25 plan-first as Webdesign decision matrix ontology.
  TT5 docs/codebase audit follows only after the ontology framework exists.
  Pilot template implementation and Google Sites extraction follow TT5 audit.
  Distributable skeleton remains blocked until explicit user slug GO.
```

Public repository:

```txt
https://github.com/Jiwoon-Kim/axismundi
https://jiwoon-kim.github.io/axismundi/
```

Local workspace:

```txt
C:\Users\thaum\dev\axismundi
```

## 2) v3.6.24 Close Summary

Closed by v3.6.24:

- Core Block Style Guide Full Spec.
- 15-section catalog structure with full-spec specimens and explicit gap rows.
- Heading, Cover, and Media & Text specimens.
- M3 publish-tooling route with optional cleanup of untracked generated
  artifacts.

Evidence:

- Preserved `#blocks-table`, `#blocks-search`, and `#blocks-theme`.
- Added `#blocks-heading`, `#blocks-text-gaps`, and `#blocks-design-gaps`.
- Four-state classification propagated into implemented specimens, gap rows,
  external prerequisites, and out-of-scope route notes.
- Browser smoke passed through local HTTP with 15 sections, 6 anchors, console
  errors 0, and no horizontal overflow.
- Full validation passed; Axis A-G stayed 1.000.
- Lock 5 fourteenth overall self-application held; ninth implementation-cycle
  count and third consecutive narrow implementation cycle.

Routed next:

- WP block styleguide human visual QA as a cycle-external task.
- Webdesign decision matrix ontology before TT5 / Pilot template / Google Sites
  extraction work.
- Distributable skeleton only after explicit user slug GO.

## 2a) v3.6.23 Close Summary

Closed by v3.6.23:

- Core Block Catalog 6-Category Split.
- v3.6.18 Layer 3 routed-forward catalog shell execution.
- M3 publish-tooling source/mirror route for `styleguide/blocks.html`.

Evidence:

- `style-guide-blocks.html` split into Text, Media, Design, Widgets, and Theme
  categories, with Embeds excluded.
- `#blocks-table` validator anchor was preserved.
- `#blocks-search` and `#blocks-theme` category anchors were added.
- Full validation and browser smoke passed; unrelated generated churn was
  restored.

## 2b) v3.6.22 Close Summary

Closed by v3.6.22:

- BACKLOG #22 explicit `data-theme="auto"` root-state implementation.
- JS, CSS, and Pilot front-end root-default alignment.
- v3.6.17 Pilot bridge source/copy byte-identical contract restoration after
  Phase 2 amend.

Evidence:

- Auto mode writes `data-theme="auto"` instead of removing the attribute.
- CSS now treats absent root theme and explicit auto as deliberate states.
- Pilot PHP root default is guarded out of admin/editor contexts.
- Full validation, browser/runtime checks, and bridge source/copy SHA256 checks
  passed.

## 2c) v3.6.21 Close Summary

Closed by v3.6.21:

- Theme Switcher Contract.
- `.sg-theme` / `.ax-theme-switcher` selector ownership.
- `data-theme-button` / `data-theme-set` attribute ownership.
- owner-specific storage key contract.
- BACKLOG #22 narrowing.

Evidence:

- `.sg-theme` is the lab / styleguide / module selector contract.
- `.ax-theme-switcher` is the Pilot / future product-facing selector contract.
- Current runtime already tolerates both selectors; the drift was contract
  wording, not a click-path defect.
- `data-theme-button` remains styleguide-local.
- `data-theme-set` remains production / module / Pilot runtime vocabulary.
- Storage keys remain owner-specific:
  - `ax-theme` = lab module/prototype runtime;
  - `axismundi.theme` = lab/styleguide catalog-local runtime;
  - `axismundi-pilot-theme` = Pilot front-end runtime.
- A future distributable visitor key must be chosen during the skeleton /
  product-context cycle and must not reuse `axismundi.theme`.
- BACKLOG #22 remains open and narrowed to explicit `data-theme="auto"`
  root-state implementation; BACKLOG #21 remains plugin territory for HCT,
  editor UI, Global Styles sync, and custom color regeneration.
- Full 6-suite validation passed and validator-generated report churn was
  restored.
- Lock 5 eleventh clean self-application held; sixth implementation-cycle
  count remains unchanged because v3.6.21 is a no-code contract decision.

Routed forward:

1. Theme Switcher Route B comment hygiene.
2. BACKLOG #22 explicit auto-state implementation.
3. Core Block Catalog 6-category split.
4. Distributable skeleton bootstrap (requires user slug / product GO).
5. Release-seal derivative generation.
6. Distributable build-copy pipeline.
7. Webdesign-craftsman workflow ontology.
8. Media catalog implementation.
9. Pixabay video isolation.
10. `ontology-theme-pilot/assets/` modernization or freeze.
11. BACKLOG #21 / #44 / #46 / #47.
12. Diagnostics policy follow-ons.

## 3) v3.6.20 Close Summary

Closed by v3.6.20:

- Pilot vs Distributable Bootstrap boundary decision.
- Pilot remains a probe / reference implementation.
- Future distributables live under `products/distributables/themes/<slug>/`.
- `axismundi` is the default first-distributable slug candidate pending user
  slug / product-name GO.

Evidence:

- Pilot cannot become a distributable by rename: namespace, constants, style
  header, text domain, pattern category slugs, `readme.txt`, and
  `screenshot.png` are Pilot/probe-oriented.
- `axismundi-microblog` is stale as first-distributable guidance but remains a
  possible future ActivityPub / microblog product.
- Pilot `readme.txt` and `screenshot.png` are probe artifacts, not
  WordPress.org submission or release-seal artifacts.
- Release-seal derivatives remain blocked until product context exists.
- Full 6-suite validation passed and validator-generated report churn was
  restored.
- Lock 5 tenth clean self-application held; sixth implementation-cycle count
  remained unchanged because v3.6.20 is a no-code boundary decision.

Routed forward:

- Distributable skeleton bootstrap after explicit user slug / product GO.
- Release-seal derivative generation after product context exists.
- Theme Switcher Contract, later closed by v3.6.21.
- Core Block Catalog split.
- Webdesign-craftsman workflow ontology and diagnostics policy follow-ons.

## 4) v3.6.19 Close Summary

Closed by v3.6.19:

- Asset Surface Audit + Cross-Reference Index.
- `docs/ASSET-SURFACE-INDEX.md`.
- Stale Material Symbols top-level README wording.
- Stale `Opus/Ogg` audio wording in root license / notice docs.
- Brand source-vs-release-seal wording.

Evidence:

- Added `docs/ASSET-SURFACE-INDEX.md` as a cross-cutting project document, not
  a v3.6.19 cycle artifact.
- Recorded seven asset surfaces and preserved path-as-policy instead of
  collapsing them into one directory.
- Clarified Material Symbols storage vs runtime registration: all three style
  sets are stored, current runtime registers Rounded only.
- Clarified MP3 source/reference plus Opus derivative wording.
- Clarified `assets/brand/*.svg` as complete project identity source assets
  while deployment derivatives remain unlocked.
- Full 6-suite validation passed and validator-generated report churn was
  restored.
- Lock 5 ninth clean self-application held; sixth implementation-cycle count
  advanced because v3.6.19 was a narrow docs-hygiene implementation.

Routed forward:

- Future cycles touching asset surfaces should update the matching
  `docs/ASSET-SURFACE-INDEX.md` row.
- Do not collapse asset surfaces unless a future architecture cycle explicitly
  reopens path-as-policy.

## 5) v3.6.18 Close Summary

Closed by v3.6.18:

- Core Block Mapping Audit.
- Current WordPress core-block mapping crosswalk after v3.6.2-v3.6.7 and
  v3.6.17.

Evidence:

- Recorded a five-layer no-code decision:
  - Layer 1: Tier 1 block status is closed or routed.
  - Layer 2: WordPress categories split into Text, Media, Design, Widgets,
    Theme, and Embeds.
  - Layer 3: `style-guide-blocks.html` routes to a future category-aware lab
    catalog split.
  - Layer 4: `style-guide-prose.html` remains the Markdown / Custom HTML prose
    inheritance surface.
  - Layer 5: D-layer binding files remain read-only and route to BACKLOG #21 /
    ontology / Interpreter Plugin work.
- Embeds remain excluded until source/privacy, provider whitelist, iframe
  policy, oEmbed cache, and responsive-token policy are explicit.
- Out-of-cycle asset commits `1eed48a`, `6a6d27b`, and `4bec70d` are recorded
  as brand-slot / placeholder-media lineage outside the mapping audit.
- `php -l`, `npm test`, `build_pilot_specimen_wall`,
  `validate:specimen-wall`, `validate:computed`, and `git diff --check`
  passed.
- Validator-generated report churn was restored.
- Lock 5 eighth clean self-application held; fifth implementation-cycle count
  remains unchanged because v3.6.18 is a no-code decision-only variant.

Routed forward:

- Candidate set remains plan-first: Pilot vs distributable theme bootstrap,
  brand asset migration follow-on, lab catalog split, Theme / FSE template
  work, Media catalog implementation, BACKLOG #21, #44, #46, #47, and
  diagnostics policy follow-ons.

Current matrix snapshot remains:

```txt
DONE       31
PARTIAL     0
TODO        0
RECORD      3
```

Resume checklist:

1. Confirm local `git status --short --branch`; local git status is
   authoritative for mount-staleness cases.
2. Read v3.6.21 Phase 5/3/2/1/0 docs before choosing the next route.
   If entering BACKLOG #22, also read v3.6.21 Phase 2 narrowing and the
   theme-switcher memories.
3. Start the next cycle plan-first; do not enter Phase 2 without a review
   trigger.
4. Choose the next primary route from the candidate set above.

## 3) v3.6.17 Close Summary

Closed by v3.6.17:

- WP Ripple Runtime Packaging Decision.
- BACKLOG #41.
- The remaining shared WordPress ripple runtime packaging decision narrowed by
  v3.6.6.

Evidence:

- Recorded a no-code layered route:
  - Route D: split CSS state-layer parity from animated JS ripple.
  - Route C: shared animated WordPress ripple runtime belongs to future
    plugin/custom-binding or dedicated WordPress runtime package territory if
    pursued.
  - Route A: v3.6.17 execution shape was a no-code decision report.
- Preserved lab Ripple v2 forbidden ancestors for `.prose`,
  `.wp-block-post-content`, `.entry-content`, and `[contenteditable]`.
- Kept current Pilot front-end button ripple Pilot-only, not shared runtime
  authority.
- Kept editor parity as CSS state-layer parity where WordPress exposes a state;
  no animated runtime enters editor-owned content in this cycle.
- Phase 3 front-end smoke confirmed `pilot-block-bridge.js/css` still load,
  `window.axRipple` remains undefined, 5 / 5 post-content button links retain
  Pilot-only markers, and console/page errors are 0.
- `php -l`, `npm test`, `build_pilot_specimen_wall`,
  `validate:specimen-wall`, `validate:computed`, and `git diff --check`
  passed.
- Validator-generated report churn was restored.
- Lock 5 seventh clean self-application held; fifth implementation-cycle count
  remains unchanged because v3.6.17 is a no-code packaging-decision variant.

Routed forward:

- Candidate set remains plan-first: BACKLOG #21, #44, #46, #47, Pilot
  revision, Sheet drag-to-dismiss, styleguide integration, or diagnostics
  policy follow-ons.
- Future shared animated WordPress ripple runtime, if pursued, should be opened
  as a new plugin/custom-binding or dedicated WordPress runtime packaging item,
  not as a theme-side reopening of #41.

Current matrix snapshot remains:

```txt
DONE       31
PARTIAL     0
TODO        0
RECORD      3
```

Resume checklist:

1. Confirm local `git status --short --branch`; local git status is
   authoritative for mount-staleness cases.
2. Read v3.6.17 Phase 5/3/2/1/0 docs before choosing the next route.
3. Start the next cycle plan-first; do not enter Phase 2 without a review
   trigger.
4. Choose the next primary route from BACKLOG #21 / #44 / #46 / #47,
   Pilot revision, or diagnostics policy follow-ons.

## 3) v3.6.16 Close Summary

Closed by v3.6.16:

- Lab A11y Diagnostics Fix Sweep.
- BACKLOG #48.
- Four user-captured VS Code Problems panel target diagnostics.

Evidence:

- DateTime CSS: nested `/* EXTRACTED */` marker prose changed to `[EXTRACTED]`.
- Menu: checkable "Autosave on" item now uses `role="menuitemcheckbox"` and
  `aria-checked="true"`.
- Nav bar: active destination specimen now uses `aria-current="page"`,
  matching the first nav-bar specimen.
- Ripple: menuitem TARGET specimen is inside a local `role="menu"` host while
  `data-ax-ripple` remains on the menuitem.
- User-side VS Code Problems panel re-sweep showed 0 errors on the four target
  files and no BACKLOG #48 target diagnostics.
- `npm test` passed with Axis A/B/C/D/E/F/G all 1.000.
- `build_pilot_specimen_wall`, `validate:specimen-wall`, and
  `validate:computed` passed.
- Lock 5 sixth clean self-application held (fifth implementation-cycle
  application after v3.6.15's diagnostic-only variant).

Routed forward:

- Candidate set remains plan-first: BACKLOG #21, #41, #44, #46, #47, Pilot
  revision, or diagnostics policy follow-ons.
- Policy / diagnostics follow-ons: VS Code workspace diagnostics config,
  Microsoft Edge Tools / webhint normative policy, no-inline-styles policy,
  broad compat-api/css handling, and the button-group `inline-size:
  fit-content` compatibility warning.
- Mount staleness was observed again during Phase 2 review with roughly 22-67h
  stale source snapshots; local git status and user-side byte verification
  remain authoritative.

Current matrix snapshot remains:

```txt
DONE       31
PARTIAL     0
TODO        0
RECORD      3
```

Resume checklist:

1. Confirm local `git status --short --branch`; local git status is
   authoritative for mount-staleness cases.
2. Read v3.6.16 Phase 5/3/2/1/0 docs before choosing the next route.
3. Start the next cycle plan-first; do not enter Phase 2 without a review
   trigger.
4. Choose the next primary route from BACKLOG #21 / #41 / #44 / #46 / #47,
   Pilot revision, or diagnostics policy follow-ons.

## 3) v3.6.15 Close Summary

Closed by v3.6.15:

- VS Code Diagnostics Sweep.
- Scope correction from repo-level parser sweep to VS Code Problems panel
  diagnostics.
- v3.6.14 Docker-dependent validation debt.

Evidence:

- Phase 0 / Phase 1 docs were amended in-place after user correction.
- VS Code Problems panel diagnostics became primary evidence.
- Parser / validator sweep became supporting evidence.
- Wave 3 priority slice (`slider/loading/progress`) had 0 source errors and 9
  no-inline-styles warnings from shared pattern-page critical styles.
- JavaScript 25/25, PHP 8/8, Python compile, JSON 50/50, `npm test`, and
  `publish:styleguide` passed.
- `build_pilot_specimen_wall`, `validate:specimen-wall`, and
  `validate:computed` passed after Docker Desktop / wp-env became available.
- No source implementation files changed; generated artifacts were restored.
- Lock 5 fifth clean self-application held as a diagnostic-only variant.

Routed forward:

- v3.6.16 primary candidate: Lab Module A11y Diagnostics Fix Sweep.
- BACKLOG #48 owns the four P2 diagnostics:
  - `date-time/lab-date-time.css` nested comment marker cleanup.
  - `menu/lab-menu-pattern.html` invalid `aria-selected` on `role=menuitem`.
  - `nav-bar/lab-nav-bar-pattern.html` invalid `aria-selected` on a plain
    button.
  - `ripple/lab-ripple-pattern.html` standalone `role=menuitem` without
    required parent.
- Low-priority policy routing: VS Code workspace diagnostics config,
  Microsoft Edge Tools / webhint normative status, no-inline-styles policy,
  and compat-api/css broad warnings.

Current matrix snapshot remains:

```txt
DONE       31
PARTIAL     0
TODO        0
RECORD      3
```

Resume checklist:

1. Confirm local `git status --short --branch`; local git status is
   authoritative for mount-staleness cases.
2. Start the next cycle plan-first; do not enter Phase 2 without a review
   trigger.
3. If choosing v3.6.16 primary, begin with BACKLOG #48 and
   `docs/v3.6.15/VS-CODE-DIAGNOSTICS-SWEEP-PHASE-5-CLOSE.md`.

## 4) v3.6.14 Close Summary

Closed by v3.6.14:

- Wave 3 Closure - Inputs / Feedback Final.
- Slider #21, Loading #30, and Progress #31.
- All remaining TODO component rows.

Evidence:

- Added `modules/slider/`, `modules/loading/`, and `modules/progress/`.
- Slider uses lab-scoped CSS, `lab-slider.js`, pattern HTML, and SPEC /
  MEASUREMENT / RUNTIME / WP docs.
- Loading and Progress use lab-scoped CSS, pattern HTML, and SPEC /
  MEASUREMENT / WP docs.
- 12 Phase 3 visual cells passed with console 0, 4xx 0, overflow 0, and
  `theme.js` no-load.
- Slider keyboard/value sync, Loading `role=status`, and Progress
  `role=progressbar` evidence passed.
- Loading and Progress reduced-motion fallback passed via CDP emulation.
- Slider reduced-motion is N/A because it has no animation surface.
- Lock 5 fourth clean post-promotion self-application held.

Current matrix snapshot:

```txt
DONE       31
PARTIAL     0
TODO        0
RECORD      3
```

Validation status:

- PASS: `node --check` for `lab-slider.js`.
- PASS: `php -l products/reference-implementations/axismundi-pilot/functions.php`.
- PASS: `npm test`; Axis A/B/C/D/E/F/G all 1.000.
- PASS: `npm run publish:styleguide`, with generated mirror restored.
- PASS: `git diff --check`.
- BLOCKED: `build_pilot_specimen_wall`, `validate:specimen-wall`, and
  `validate:computed` because Docker Desktop / `wp-env` was unavailable.

Resume checklist:

1. If Docker Desktop is available, rerun:
   `python tools/generators/build_pilot_specimen_wall.py`,
   `npm run validate:specimen-wall`, and `npm run validate:computed`.
2. Confirm local `git status --short --branch`; local git status is
   authoritative for mount-staleness cases.
3. Start the next cycle plan-first; do not enter Phase 2 without a review
   trigger.

Routed forward:

- VS Code diagnostics sweep as primary next candidate.
- Optional styleguide integration for
  `lab/modules/{slider,loading,progress}/`.
- Loading inline-in-button "Saving" contrast and Progress linear determinate
  dark-mode contrast as low-priority visual observations.
- BACKLOG #21 / #41 / #44 / #46 / #47 and Pilot theme revision remain
  available candidates after diagnostic sweep.

## 3) v3.6.13 Close Summary

Closed by v3.6.13:

- Wave 2B-4 Actions Consumers.
- FAB menu #5, Split button #7, and Toolbar #8.
- Wave 2B as a whole.

Evidence:

- 22 Phase 2 files added for 3 modules x lab CSS / JS / pattern / 4 audit docs
  plus the Phase 2 report.
- 12 Phase 3 visual cells passed with console 0, 4xx 0, and overflow 0.
- FAB menu verified intentional outside-click absence plus Escape close.
- Split button verified primary action distinct from trailing chevron popover
  trigger.
- Toolbar verified local `aria-pressed` state sync without loading
  `scripts/theme.js`.
- Toolbar ripple count clarified: 7 icon buttons total, 6 enabled unbounded
  ripple hosts, and 1 disabled no-ripple host.
- Lock 5 third clean post-promotion self-application held.

Current matrix snapshot:

```txt
DONE       28
PARTIAL     0
TODO        3
RECORD      3
```

Routed forward:

- Remaining TODO component rows.
- BACKLOG #21 Interpreter Plugin strategy.
- BACKLOG #41 / #44 / #46 / #47 unchanged.
- VS Code diagnostics sweep after component modularization.

## 3) v3.6.12 Close Summary

Closed by v3.6.12:

```txt
Phase 1 inventory:
  Existing date-time/ module preserved and mapped as PARTIAL -> DONE candidate
  Chunk H3/H4 Date+Time baseline selectors mapped
  popover/ relationship resolved as aspirational/stale, not factual consumer
  BACKLOG #19 itemized into drift-closed, Phase 2 closure, and decision rows
  Route A selected: self-contained DateTime completion

Phase 2 implementation:
  Existing lab-date-time.js / CSS / pattern updated in place
  Date grid gained role=row wrappers, aria-current, aria-labelledby,
    aria-multiselectable, live announcements, Home/End, PageUp/PageDown,
    Shift+PageUp/PageDown, and Enter/Space activation
  Time picker listbox/option contract preserved
  Four modern DateTime audit docs added
  Legacy DATE-TIME-AUDIT.md preserved with a v3.6.12 addendum
  No popover migration, provider edit, baseline edit, plugin/WP edit, or Lock 5 shortcut

Phase 3 visual QA:
  DateTime x desktop/mobile x light/dark: console 0 / overflow 0
  CDP Accessibility.getFullAXTree verified grid: 1, row: 6, gridcell: 42
  Date keyboard matrix PASS: Arrow, Home/End, PageUp/PageDown,
    Shift+PageUp/PageDown, Enter, Space, roving tabindex, live text
  Time picker non-regression PASS: listbox/options, 12h/24h, typed input,
    Escape close, OK commit
  Forbidden-ancestor bail-out PASS
```

Validation at close:

```txt
node --check products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js PASS
wp-env run cli wp core version                      7.0
python tools/generators/build_pilot_specimen_wall.py PASS
npm run validate:specimen-wall                       PASS
php -l products/reference-implementations/axismundi-pilot/functions.php PASS
npm test                                             PASS (Axis A-G all 1.000)
npm run validate:computed                            PASS
npm run publish:styleguide                           PASS, generated mirror restored
git diff --check                                     PASS
```

Routed forward:

```txt
Wave 2B-4:
  Actions consumers #5 / #7 / #8 closed by v3.6.13

BACKLOG #41 / #44 / #46 / #47:
  unchanged

BACKLOG #19:
  closed by v3.6.12

Sheet drag-to-dismiss:
  Wave 2B-2 follow-on note in ROADMAP / NEXT-SESSION, no BACKLOG item

Native Dialog backdrop:
  future .dialog::backdrop visual styling must revisit external .modal-scrim layering

DateTime provider-matrix wording:
  current DateTime module is self-contained; stale/aspirational popover/ wording
  routes as light documentation cleanup, not a BACKLOG item

Lock 5:
  second post-promotion self-application held; no safe-shortcut exception used
```

Phase 3 test target convention:

```txt
For module pattern pages, prefer a repository-root localhost server:
  http://127.0.0.1:<port>/products/reference-implementations/axismundi-lab/modules/<module>/<pattern>.html

This avoids file:// automation policy blocks and preserves repository-root
self-hosted font / Material Symbols paths.

For a11y-heavy modules, CDP Accessibility.getFullAXTree is a primary evidence
path for role hierarchy checks. Manual NVDA / VoiceOver audio testing remains
supplementary unless a reviewer explicitly requires it.
```

## 4) Lesson Locks

These are now close-time rules, not suggestions:

```txt
Lock 1 - wp-custom downstream-only

Every settings.custom.axismundi.* entry MUST be defined as:
  var(--comp-*) or var(--md-sys-*) or var(--md-ref-*)

Literal hex / rgb / px / number values are forbidden in this namespace.
Rationale: wp-custom is a downstream projection of M3, never a source.
Validator: tools/validators/validate_theme_pilot.py Axis G.
```

```txt
Lock 2 - md-sys color maps to md-ref

Every --md-sys-color-* entry MUST be defined as:
  var(--md-ref-palette-*)

Literal hex / rgb / hsl values are forbidden in the md-sys color layer.
Rationale: md-sys is the runtime semantic layer; md-ref is the primitive source.
Dark mode swaps sys -> ref mappings only.
Validator: tools/validators/validate_theme_pilot.py Axis E.
```

```txt
Lock 3 - core/button semantic route before visual cleanup

Before accepting visual cleanup for core/button link affordances, name the
semantic route. A core/button anchor with href is navigation and may receive an
M3 button visual bridge. A real action, form behavior, AJAX flow, federation
action, or durable custom schema must be routed to plugin/custom-block
territory, not implemented in the theme bridge.
```

```txt
Lock 4 - semantic mismatch handling rule

When a WordPress core block visually maps to M3 but carries divergent markup,
interaction, or accessibility semantics, route the mismatch as either
theme-owned semantic-decision or plugin/custom-block territory before
accepting a visual fix. Do not silently ignore the mismatch and do not collapse
distinct core block structures into one generic CSS patch.
```

```txt
Lock 5 - diagnostic-first before implementation

For plan-first cycles where the route, failure mode, or boundary risk is not
already known, Phase 1 diagnostic inventory is mandatory before Phase 2
implementation. The diagnostic names source inputs, baseline / provider /
semantic boundaries, route buckets, selected and rejected routes, write scope,
fences, and validation plan.

Do not patch first and backfill the route later. Tiny mechanical edits with
explicit scope and no boundary risk may skip the full report only when the
shortcut is recorded as safe.
```

## 5) Resume Checklist

Start by running:

```powershell
cd C:\Users\thaum\dev\axismundi
git status --short
wp-env start
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
```

Then open/check relevant Pilot/styleguide surfaces for the next cycle. For
Pilot feedback work, include:

```txt
http://localhost:8888/
http://localhost:8888/?page_id=10
http://localhost:8888/?p=1
http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
file:///C:/Users/thaum/dev/axismundi/styleguide/blocks.html
```

## 6) Next Action

Choose the next cycle. Do not auto-start implementation without a Phase 0 plan.

Recommended primary routes:

```txt
Pilot vs distributable theme bootstrap:
  decide whether the next theme work revises Pilot as a probe or starts the
  distributable theme skeleton

Brand asset migration follow-on:
  partially pre-completed by 1eed48a / 6a6d27b / 4bec70d; remaining decisions
  are Pilot vs distributable placement, Pixabay isolation, MP3 source retention
  vs Opus-only distributable policy, final brand seal, and asset reference
  policy

Lab catalog split:
  restructure style-guide-blocks.html into Text / Media / Design / Widgets /
  Theme sections, with Embeds excluded until source/privacy policy exists

Media catalog implementation:
  use the new placeholder media only after asset ownership and distributable
  placement are decided

BACKLOG #21 Interpreter Plugin strategy:
  plugin-tier strategy, with Lock 3/4 routing kept explicit

BACKLOG #44 remaining specimen coverage / validator polish:
  mark/highlight, long-line code, deep pullquote, Material Symbols follow-on
  coverage, and validator hardening

BACKLOG #46 disabled ripple host authoring hygiene:
  decide remove data-ax-ripple from disabled hosts vs document provider
  tolerance

BACKLOG #47 popover provider menu-item-class logic extraction hygiene:
  decide provider contract remains as anchored Menu provider vs extract
  menu-owned helper
```

Alternative routes:

```txt
Pilot theme revision
Sheet drag-to-dismiss follow-on
Styleguide integration for Slider / Loading / Progress module pages
VS Code workspace diagnostics config policy
Microsoft Edge Tools / webhint normative policy for lab module pages
no-inline-styles policy for pattern critical styles
broad compat-api/css handling policy
button-group inline-size: fit-content compatibility warning
```

Phase cadence:

```txt
v3.6.x uses Phase 0 / 1 / 2 / 3 / 5.
Phase 4 is intentionally unused in this cadence.
```
