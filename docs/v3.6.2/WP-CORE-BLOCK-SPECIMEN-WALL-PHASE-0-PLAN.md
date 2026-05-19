# v3.6.2 — WP Core Block Specimen Wall — Phase 0 Plan

Date: 2026-05-20

## Verdict

v3.6.2 should begin as an evidence-collection and classification cycle, not a
bridge implementation cycle.

The cycle target is BACKLOG #43. BACKLOG #41 consumes this cycle's evidence,
but #41 implementation decisions remain out of scope until the specimen wall
has produced coverage and classification data.

## User Request Log

Preserve these requirements as acceptance criteria. Do not abstract them into a
generic "block QA" lane.

```txt
v3.6.2 — WP Core Block Specimen Wall
```

```txt
"wall"이 단어 자체로 "다 펴놓고 한 번에 본다"는 scope discipline을 함의합니다.
```

```txt
"evidence collection / classification 사이클로 작게 자르고, 구현 확장은 다음 cycle"
```

```txt
#43은 enumerate + classify + route만 하고 변경 hunk는 최소화해야 #41 진입이 깨끗합니다.
```

```txt
Tier 1 (must): Pilot이 이미 mapping하는 block + 누출 발견된 block
  버튼, 검색, 표(tfoot 포함), 코드, 인용, 분리선, 단락, 제목, 목록 등

Tier 2 (should): Tier 1 인접 block, semantic 비중 있는 block
  미디어, 갤러리, 컬럼, 그룹, 커버 등

Tier 3 (nice-to-have): 나머지 — utility, embed, 위젯 등
```

```txt
하이브리드 권장. WP CLI 또는 content import로 WP page를 programmatically 생성
```

```txt
v0 (Phase 1 진입 직전): specimen page rendered 확인 (HTTP 200, no console errors)
v1 (Phase 1 종료):       per-block computed style snapshot 캡처 → finding raw data
v2 (#41로 routing):       per-block expected M3 mapping 검증
```

```txt
5번째 "no action" 추가 권장:

no-action: WP default가 M3 surface와 충돌 없이 자연 동작 — reset/bridge 불필요
```

```txt
#43에서는 observation + classification만, decision은 #41로.
```

```txt
v3.6.2 Phase 1 done =
  Tier 1 core blocks N개 모두 specimen에 포함됨
  각 block × variation 별 finding bucket이 분류됨
  finding 총 M개가 5-bucket으로 routing됨
```

## Cycle Framing

```txt
Cycle name:
  v3.6.2 — WP Core Block Specimen Wall

Primary backlog:
  BACKLOG #43 — WP core block specimen wall / full variation audit

Feeds:
  BACKLOG #41 — full block bridge expansion

Mode:
  Evidence collection / classification

Not mode:
  Bridge implementation
  CSS reset expansion
  Button semantic decision RFC
```

This cycle's core insight is that WordPress integration defects are not always
visible from the source CSS or from a small set of hand-picked pages. The wall
exists to make WordPress core block output, style variations, and native
defaults inspectable in one deterministic surface.

## Scope

### Tier 1 — In Scope

Tier 1 includes blocks already touched by the Pilot, blocks already mapped by
the bridge, and blocks that surfaced visual QA findings.

```txt
T1.01 core/paragraph
T1.02 core/heading
T1.03 core/list
T1.04 core/quote
T1.05 core/code
T1.06 core/table
      default
      stripes
      header
      footer / tfoot
T1.07 core/buttons + core/button
      fill
      outline
      tonal
      elevated
      text
      link-markup semantic observation
T1.08 core/search
      default
      filled-search style
T1.09 core/separator
      default
      inset
      middle inset
T1.10 core/group
      card-filled
      card-elevated
      card-outlined
T1.11 core/columns + core/column
      layout wrapper around existing card/list specimens
```

Tier 1 success requires every listed block family to appear in the WP-rendered
specimen page with a stable `data-ax-specimen-id` or equivalent selector anchor.

### Tier 2 — Enumerate And Defer

Tier 2 should be listed in the Phase 1 report as follow-on candidates, but it is
not required for v3.6.2 close.

```txt
core/image
core/gallery
core/media-text
core/cover
core/file
core/audio
core/video
core/pullquote
core/details
```

### Tier 3 — Enumerate And Defer

```txt
core/embed family
core/query / post-template / query-pagination
core/navigation
core/comments family
core/site-* template/chrome blocks
legacy widget-like blocks
utility/dynamic blocks whose output depends on site data or external providers
```

Tier 2 and Tier 3 are intentionally not "forgotten"; they are excluded to keep
v3.6.2 from becoming full block bridge expansion by accident.

## Fixture Strategy

Use a hybrid, reproducible, WP-rendered fixture.

```txt
Version-controlled source:
  a deterministic Tier 1 block-content fixture, stored in the repo

Import/create step:
  a script or WP-CLI command creates/updates a Pilot page from that source

Rendered surface:
  WordPress front end renders the page in wp-env

Audit:
  Playwright captures per-block computed snapshots from the rendered page
```

Preferred implementation shape for Phase 1:

```txt
products/reference-implementations/axismundi-pilot/fixtures/
  core-block-specimen-wall.html

tools/generators/
  build_pilot_specimen_wall.py or equivalent importer helper

tools/validators/
  validate_pilot_specimen_wall.js
```

The exact importer mechanism may be adjusted during Phase 1 based on wp-env
reality. The invariant is that the wall must be reproducible from committed
source and rendered by WordPress, not maintained as a manually edited page.

Static HTML alone is not acceptable because it can miss WordPress server-side
block rendering quirks. Manual WP editor content alone is not acceptable
because it drifts.

## Finding Buckets

Every Tier 1 block/variation must be assigned exactly one bucket:

```txt
no-action:
  WP output/defaults do not conflict with the current M3 surface.
  No reset, bridge, or backlog item is needed.

reset:
  Native WP default leaks through and should be normalized before M3 mapping.

bridge:
  A block needs token or style bridge mapping, but no architectural decision is
  required.

semantic-decision:
  The visual mapping depends on markup, interaction, a11y, block style vs
  custom block, or similar architecture. Example: core/button link markup vs
  M3 button semantics.

backlog:
  Valid issue, but not Tier 1 / #41-ready, or too broad for the current Pilot
  feedback lane.
```

The `no-action` bucket is required. Without it, the specimen wall becomes a
todo generator instead of an evidence map.

## Validator Strategy

Do not force-fit this evidence cycle into the v3.6.1 "1.000 PASS means mapping
complete" model.

```txt
v0 — Render gate
  - specimen page HTTP 200
  - no console/page errors
  - no horizontal overflow at mobile viewport
  - Tier 1 anchors all present

v1 — Snapshot gate
  - per-block computed style snapshots captured
  - raw report written to tmp/
  - classification table in phase report covers every Tier 1 block/variation

v2 — Expected M3 mapping gate
  - out of scope for v3.6.2
  - belongs to BACKLOG #41 bridge expansion
```

Recommended command shape:

```txt
npm run validate:computed
npm run validate:specimen-wall
```

If a new npm script is added, it should be additive and must not weaken the
existing validators.

## Phase Plan

### Phase 0 — Plan And Review

Deliverable:

```txt
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-0-PLAN.md
```

Exit criteria:

```txt
- User Request Log preserves the explicit evidence-cycle framing.
- Tier 1 / Tier 2 / Tier 3 boundary is explicit.
- Fixture strategy is WP-rendered and reproducible.
- Finding buckets include no-action.
- Success metric is coverage + classification completeness, not bridge PASS.
- Reviewer returns GO or no P1 findings remain.
```

### Phase 1 — Specimen Wall Fixture + Render Gate

Expected work:

```txt
- Add committed Tier 1 block-content fixture.
- Add deterministic import/create helper for the Pilot specimen page.
- Ensure page renders in wp-env.
- Add v0 render gate:
  HTTP 200
  no console/page errors
  no horizontal overflow
  Tier 1 anchors present
- Phase 1 exit requires:
  Tier 1 block families represented: 11 / 11
  Every Tier 1 family has a stable data-ax-specimen-id or equivalent selector
  Every Tier 1 family is individually targetable by Playwright
```

Non-goals:

```txt
- Do not patch block bridge CSS.
- Do not resolve button semantics.
- Do not assert expected M3 values yet.
```

### Phase 2 — Computed Snapshot + Classification

Expected work:

```txt
- Extend or add Playwright audit for per-block snapshots.
- Produce raw JSON snapshot under tmp/.
- Create Phase 2 report with a Tier 1 block x variation table.
- Assign every entry to exactly one bucket:
  no-action / reset / bridge / semantic-decision / backlog.
```

Exit metric:

```txt
Tier 1 block families covered: 11 / 11
Tier 1 entries classified:     N / N
Unclassified entries:          0
```

The final value of N should be computed from the actual fixture table once
Phase 1 defines the exact block/variation count.

### Phase 3 — Visual QA

Expected work:

```txt
- User/Codex visual review of the specimen wall in light and dark.
- Confirm screenshots or browser session cover the full wall.
- Route any new finding into the 5-bucket model.
```

### Phase 5 — Close

Expected work:

```txt
- Close report with coverage/classification summary.
- BACKLOG #43 updated with v3.6.2 evidence.
- BACKLOG #41 updated with reset/bridge/semantic-decision inputs.
- CHANGELOG / ROADMAP / CURRENT-STATE / NEXT-SESSION updated.
```

## Files To Read In Phase 1

```txt
products/reference-implementations/axismundi-pilot/functions.php
  Existing block style registration and enqueue order.

products/reference-implementations/axismundi-pilot/patterns/*.php
  Current Pilot block markup and block style usage.

products/reference-implementations/axismundi-pilot/templates/*.html
  How Pilot pages render content.

tools/validators/validate_pilot_computed_styles.js
  Reusable Playwright helpers and snapshot conventions.

BACKLOG.md #41 / #43
  Routing targets.

docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-3-VISUAL-QA.md
  Seed findings: table footer and core/button semantic boundary.
```

## Files Expected To Change In Phase 1

Exact filenames may change after implementation discovery, but the write scope
should stay close to this set:

```txt
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
  Deterministic block-content fixture.

tools/generators/build_pilot_specimen_wall.py
  Import/create helper, if WP-CLI/import scripting is feasible.

tools/validators/validate_pilot_specimen_wall.js
  v0 render gate and v1 snapshot capture.

package.json
  Add validate:specimen-wall only if the validator becomes a committed script.

docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-1-REPORT.md
  Phase 1 result / render gate report.
```

Avoid editing:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
```

Those files belong to #41 or later unless a direct Phase 1 blocker appears and
the user explicitly approves a scope change.

## Dependency Assumptions

```txt
wp-env:
  Required for actual WordPress rendering.

WordPress version:
  .wp-env.json currently pins WordPress/WordPress#6.9.4.
  The wall audits that environment; do not claim broader WP-version coverage.

Pilot theme:
  axismundi-pilot remains a theme-only proof.
  No custom block registration in v3.6.2.

Existing validators:
  Axis E/F/G and validate:computed remain release guards.
  Specimen-wall validator is additive.
```

## Applicable Gates

From `docs/v3.5.0/PROMOTION-CRITERIA.md`, applied by analogy to the Pilot
evidence surface:

```txt
G1 ontology fit:
  v3.6.2 is a WordPress binding QA cycle, not a component completion cycle.

G4 artifacts:
  Fixture, validator, and phase report must exist if Phase 1 proceeds.

G6 visual QA:
  Light/dark specimen wall review required in Phase 3.

G10 audit pattern:
  Findings must be recorded with traceable evidence, not chat memory.

G15 WordPress mapping:
  Core block mapping claims require actual WP-rendered evidence.

G20 regression safety:
  Existing v3.6.1 validators must remain PASS.
```

## Validation Commands

Phase 0:

```powershell
git diff --check
```

Phase 1+:

```powershell
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
npm run validate:specimen-wall
git diff --check
```

If `validate:specimen-wall` is not yet added during an intermediate step, the
Phase 1 report must state the exact one-off command used instead.

## Non-Goals

```txt
- Do not implement BACKLOG #41 bridge/reset expansion.
- Do not decide core/button vs custom block architecture.
- Do not add custom blocks.
- Do not claim full WordPress core block coverage.
- Do not cover Tier 2/3 as close blockers.
- Do not alter v3.6.1 token architecture locks.
- Do not weaken Axis E/F/G or validate:computed.
- Do not patch visual findings discovered by the wall inside v3.6.2 unless the
  user explicitly promotes one to blocker.
```

## Risks

```txt
R1 — Fixture drift:
  Manual WP editor pages can drift. Mitigation: committed source + deterministic
  import/create step.

R2 — Scope creep into #41:
  Findings may tempt immediate CSS fixes. Mitigation: five-bucket routing and
  explicit no bridge implementation non-goal.

R3 — Dynamic/server-rendered block instability:
  Some core blocks depend on site state or external providers. Mitigation: Tier
  1 avoids dynamic/template/embed-heavy blocks.

R4 — Button semantic rabbit hole:
  core/button can expose a/button semantic mismatch. Mitigation: #43 catalogs
  markup and affordance conflicts only; #41 owns decisions.

R5 — Validator over-assertion:
  Expected M3 mapping assertions would turn the wall into #41. Mitigation:
  v3.6.2 validator captures render/snapshot/classification, not final mapping.
```

## Phase 0 Exit Criteria

```txt
- This plan is committed or ready for review.
- Opus/Claude review returns GO or all P1 findings are resolved.
- No implementation files are changed before GO.
```
