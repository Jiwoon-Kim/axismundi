# v2.0 Gap Report

3-way validation에서 발견된 handbook ↔ docs ↔ schema/code 간 불일치 기록.

**Baseline**: WordPress 6.9.4 (97b7f62a, 2026-03-11) + Gutenberg v23.1.1 (12c6c76e, 2026-05-08)
**Corpus**: dev_handbook_clean v1.1a-frozen (synced 2026-05-12)

---

## #1 — `supports.md` (pilot, 2026-05-12)

**Anchor**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md`
**Sources**:
- corpus: `dev_handbook_clean/.../supports.md`
- docs: `gutenberg/docs/reference-guides/block-api/block-supports.md`
- schema: `gutenberg/schemas/json/block.json#/properties/supports`

### Summary

| Agreement level | Count | Notes |
|---|---|---|
| in all 3 sources | 27 | strong consensus → BlockSupport ontology instances |
| in 2 sources | 1 | `autoRegister` (corpus + docs, schema 누락) |
| schema only | 1 | `customCSS` (upcoming feature, docs/corpus 미작성) |
| **total distinct** | **29** | |

### Gap details

#### G1.1 — Naming convention divergence: `autoRegister` vs `auto_register`

- **corpus**: `## autoRegister` (camelCase)
- **docs**: `## auto_register` (snake_case)
- **schema**: not defined in `properties.supports`

**원인 추정**: developer.wordpress.org HTML builder가 anchor를 camelCase로 normalize. Gutenberg docs는 PHP-style snake_case 원본. Schema는 v23.1.1 시점 아직 미반영 (handbook이 schema보다 먼저 문서화).

**Ontology slot impact**: BlockSupport.autoRegister 인스턴스를 1개로 통합. canonical name = `autoRegister` (handbook 표기) + alias `auto_register` (schema/PHP 표기).

**Action**: ontology binding rule에 `naming_alias`로 등록.

---

#### G1.2 — `customCSS` schema-only

- **schema**: `properties.supports.properties.customCSS` (type: boolean) 정의됨
- **docs**: H2 부재
- **corpus**: H2 부재

**원인 추정**: Gutenberg v23.1.1 schema에 추가됐지만 문서화 PR이 아직 미머지. Block CSS API의 일부로 추정 (Gutenberg 23.x 사이클 신규 feature).

**Ontology slot impact**: BlockSupport.customCSS 인스턴스로 등록. provenance="schema_only", evidence_tier=2.

**Action**: gap_report에 명시. 다음 Gutenberg release (v23.2+)에서 docs 추가 여부 모니터링.

---

#### G1.3 — Corpus vs docs perfect H2 match (28/28)

corpus의 H2 28개와 v23.1.1 docs의 H2 28개가 **완전 일치** (autoRegister/auto_register naming만 제외).

**의미**:
- developer.wordpress.org docs build가 Gutenberg v23.1.1 plugin docs를 거의 그대로 사용함을 확인.
- corpus sync date (2026-05-12) ↔ Gutenberg v23.1.1 (2026-05-08) gap 4일은 매우 작아 anchor 누락/추가 risk 거의 없음.
- v2.0 baseline pin이 적절했음을 supports.md가 검증함.

---

#### G1.4 — WP core (6.9.4) ships older Gutenberg (v21.9.0) than handbook reflects (v23.1.x)

WordPress 6.9.4의 wp-includes는 Gutenberg v21.9.0 subset만 포함. 하지만 handbook은 plugin 최신 (v23.x) 기준.

| feature | in v21.9.0 docs | in v23.1.1 docs | in WP 6.9.4 core |
|---|---|---|---|
| `autoRegister` (snake `auto_register`) | ✓ | ✓ | ✓ (PHP register_block_type) |
| `contentRole` | ✗ | ✓ | ✗ (Gutenberg-only) |
| `listView` | ✗ | ✓ | ✗ (Gutenberg-only) |
| `visibility` | ✗ | ✓ | ✗ (Gutenberg-only) |
| `customCSS` | n/a | schema only | ✗ |

**Ontology slot impact**: 각 BlockSupport에 `availability` 속성 부여:
- `core_6_9_4`: WP core가 지원하는 supports
- `gutenberg_plugin_23_1_1`: 추가로 plugin이 제공하는 supports

**Action**: ontology core에 dual-track availability 도입. Axismundi 같은 production theme는 core 지원만 사용 가능. Gutenberg plugin 활성 환경은 plugin features 사용 가능.

---

### Ontology slot candidates (extracted from supports.md)

| Slot | Instance count | Evidence tier | Notes |
|---|---|---|---|
| **BlockSupport** | 28 | 2 (schema-grounded) | 27 in-all-3 + 1 schema_only |
| **SupportProperty** | 24 | 2 | nested sub-properties (background.backgroundImage 등) |
| **DefaultValue** | 20 | 2 | schema-declared defaults |
| AttributeInjection | 0 | (prose only — pilot deferred) |
| StylePath | 0 | (prose only — pilot deferred) |
| UIControlExposure | 0 | (prose only — pilot deferred) |
| SchemaConstraint | 0 | (schema JSON-schema constraint — pilot deferred) |

**다음 iteration**: prose-derived slot 4개 (AttributeInjection 등)는 supports.md 본문에서 정규식 + 패턴 추출 필요. 본 pilot은 schema-grounded slot 3개로 충분.

---

## Next pilots (예정)

- `attributes.md` — Attribute ontology slot (type/source/selector/default)
- `registration.md` — BlockType ontology slot
- `theme.json settings/styles` — Token ontology slot (Material binding 후보)

---

## #2 — `attributes.md` (P2 4-way pilot, 2026-05-12)

**Anchor**: `block-editor-handbook/03-reference-guides/01-block-api-reference/attributes.md`
**Sources** (4-way):
- corpus: `dev_handbook_clean/.../attributes.md`
- docs: `gutenberg/docs/reference-guides/block-api/block-attributes.md`
- schema: `gutenberg/schemas/json/block.json#/properties/attributes`
- instances: `gutenberg/packages/block-library/src/*/block.json` (121 core blocks)

### Summary

- **H2 alignment**: corpus 5 H2 ↔ docs 5 H2 — **perfect match**
- **Schema BlockAttribute**: 9 keys (`type`, `source`, `selector`, `attribute`, `meta`, `query`, `enum`, `default`, `role`)
- **Instance corpus**: 121 blocks, 102 with attributes, **473 attribute defs total**
- **4-way agreement**: 8 keys [SDCI], 1 key [SDC-] (`meta` — not used in instances)

### Key usage in 121 core block instances (sorted)

| key | count | % of 473 attrs |
|---|---|---|
| `type` | 473 | 100% |
| `default` | 224 | 47% |
| `role` | 96 | 20% |
| `source` | 70 | 15% |
| `selector` | 66 | 14% |
| `attribute` | 39 | 8% |
| `enum` | 16 | 3% |
| `query` | 5 | 1% |
| `meta` | 0 | **0%** ← deprecated signal |

### `source` enum distribution

| source | instance usage | schema status |
|---|---|---|
| `attribute` | 37 | active |
| `rich-text` | 21 | active |
| `query` | 5 | active (recursive) |
| `raw` | 4 | active |
| `html` | 3 | active |
| `text` | **0** | schema-only ⚠ |
| `meta` | **0** | schema-only ⚠ deprecated |

### Gap details

#### G2.1 — `rich-text` overloaded: type enum AND source enum

- **schema**: `type` enum has `"rich-text"`, `source` enum also has `"rich-text"`
- **semantic distinction**:
  - `type: "rich-text"` = data-shape primitive (alongside string/number/object/...)
  - `source: "rich-text"` = extraction strategy (rich-text serializer reads DOM)
- **ontology impact**: must split into two distinct slots. `AttributeType.rich_text` (primitive) and `AttributeSource.rich_text` (extractor) are NOT the same instance.
- **Action**: ontology binding rule — `rich-text` token resolves by context (type-context → primitive; source-context → extractor).

---

#### G2.2 — `role` field: schema vs instance enum mismatch

- **schema**: `role` typed as plain `string` (no enum)
- **instances**: only two values observed across 96 uses
  - `content` (majority — primary text/HTML content)
  - `local` (minority — block-local state, not serialized)
- **ontology impact**: `Role` slot has **closed enum at the instance level** even though schema leaves it open. v2.0 ontology should constrain enum to these two values as authoritative, mark schema as under-specified.
- **Action**: gap report on schema (PR upstream consideration?). Ontology `Role` enum = `{content, local}`.

---

#### G2.3 — Source enum members `text`, `meta` unused in core instances

- **schema**: 7 source enum members
- **core block usage**: 5 actually used (attribute/rich-text/query/raw/html)
- **unused**: `text`, `meta`
  - `text`: likely deprecated in favor of `rich-text`
  - `meta`: postmeta bridge, not used by core blocks (plugin territory)
- **ontology impact**:
  - `AttributeSource.text` → mark as deprecated
  - `AttributeSource.meta` → mark as plugin-only / external-storage boundary; this is the `MetaBinding` Tier 3 slot's evidence base
- **Action**: ontology core gives 5-element active source enum + 2-element deprecated/external annotation.

---

### Ontology slots — instance-grounded counts

| Slot | Tier | Instances | Source |
|---|---|---|---|
| **BlockAttribute** | 2 | 473 across 102 blocks | schema + instances |
| **AttributeType** | 1 | 8 primitives (null/bool/obj/arr/string/rich-text/integer/number) | schema enum |
| **AttributeSource** | 1 | 5 active + 2 dormant | schema enum × instance scan |
| **DefaultValue** | 2 | 224 (47% of attrs have default) | instances |
| **EnumConstraint** | 2 | 16 (3% of attrs have enum) | instances |
| **Selector** | 2 | 66 (14% of attrs) | instances |
| **QueryShape** | 1 | 5 (recursive sub-attribute) | schema (self-referential) + instances |
| **Role** | 1 | 96 × 2 values = `{content, local}` | instances (schema under-specified) |
| **SerializationBoundary** | 3 | derived | function(AttributeSource) |
| **DOMBinding** | 3 | derived | function(Selector, Source) |
| **MetaBinding** | 3 | 0 (schema-only) | plugin territory |

### Binding implications (v2.1 prep)

`attributes.md`가 ontology core의 backbone이 됨을 4-way로 확인. Material binding 후보 매핑:

| WordPress slot | Material binding 후보 | 근거 |
|---|---|---|
| `BlockAttribute.role: content` | `Component.contentSlot` | content vs chrome separation |
| `BlockAttribute.role: local` | `Component.localState` | non-serialized UI state |
| `AttributeType.rich-text` | `Component.RichTextField` | typed slot |
| `AttributeSource.attribute` | `Component.propBinding` | attribute → component prop |
| `EnumConstraint` | `Component.variantEnum` | Material variants (filled/outlined/text/elevated/tonal) |
| `DefaultValue` | `Component.defaultProps` | reasonable default policy |

이게 GPT가 제시한 "Button.variant ↔ attributes.style", "Card.elevation ↔ supports.shadow" 매핑의 ontology backbone.

---

## #3 — `registration.md` (P3 4-way pilot, 2026-05-12)

**Anchor**: `block-editor-handbook/03-reference-guides/01-block-api-reference/registration.md`
**Sources** (4-way):
- corpus: `dev_handbook_clean/.../registration.md`
- docs: `gutenberg/docs/reference-guides/block-api/block-registration.md`
- schema: `gutenberg/schemas/json/block.json` (top-level properties)
- instances: `gutenberg/packages/block-library/src/*/block.json` (121 core blocks)

### Summary

- **H2 alignment**: 3/3 corpus ↔ docs perfect match (`registerBlockType` / `Block collections` / `registerBlockCollection`)
- **Schema BlockType**: 31 top-level properties, 3 required (`apiVersion`, `name`, `title`)
- **Instance corpus**: 121 core blocks (all `core/` namespace, all `apiVersion=3`)
- **Important**: registration.md는 register flow만 다루고 individual key 설명은 metadata.md 등에 분산 — ontology grounding은 schema + instance가 주역

### Ontology grouping of 31 BlockType properties

| Group | Keys | Notes |
|---|---|---|
| **Identity** | name, title, description, icon, apiVersion, version, textdomain, $schema, __experimental | 9 keys, 3 required |
| **Taxonomy** | category, keywords | enum derived from instances |
| **Containment** | parent, ancestor, allowedBlocks | direct vs recursive 구분 |
| **Context** | providesContext, usesContext | context flow |
| **DataModel** | attributes, supports, selectors | P1/P2와 연결되는 슬롯들 |
| **Composability** | blockHooks, variations, styles, example | composition surfaces |
| **Runtime** | render, editorScript, editorStyle, script, style, viewScript, viewScriptModule, viewStyle | asset loading |

### Instance reality check

| key | required | 121 instance use | corpus mention | docs mention |
|---|---|---|---|---|
| `name` | ✓ | 121 | 1 | 1 |
| `title` | ✓ | 121 | 1 | 1 |
| `apiVersion` | ✓ | 121 (all=3) | 0 | 0 |
| `category` | | 121 | 0 | 0 |
| `description` | | 121 | 0 | 0 |
| `$schema` | | 121 | 0 | 0 |
| `textdomain` | | 121 | 0 | 0 |
| `supports` | | 119 (98%) | 1 | 1 |
| `attributes` | | 104 (86%) | 0 | 0 |
| `style` | | 85 (70%) | 0 | 0 |
| `usesContext` | | 63 (52%) | 0 | 0 |
| `editorStyle` | | 62 (51%) | 0 | 0 |
| `keywords` | | 46 (38%) | 0 | 0 |
| `parent` | | 25 (21%) | 3 | 3 |
| `allowedBlocks` | | 20 (17%) | 1 | 1 |
| `example` | | 18 (15%) | — | — |
| `ancestor` | | 15 (12%) | — | — |
| `styles` | | 8 (7%) | — | — |
| `selectors` | | — | — | — |
| `providesContext` | | — | — | — |
| 7 keys unused | | **0** | 0 | 0 |

**Schema keys unused in core instances** (7): `blockHooks` / `render` / `script` / `variations` / `version` / `viewScript` / `viewStyle`

### Gap details

#### G3.1 — Name namespace homogeneity (core-only)

- **Instance evidence**: 121/121 blocks use `core/` namespace, zero name collisions
- **ontology impact**: `BlockName` slot has pattern `^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$`. core namespace is reserved; plugin/theme blocks use their own. Ontology marks `core` as **canonical-source namespace**.
- **action**: BlockName slot identity rule = `namespace + "/" + slug`, with namespace ∈ {core, plugin-specific}.

---

#### G3.2 — Category enum: schema unconstrained, instance-derived

- **schema**: `category` is `type=?` with no enum (open string)
- **instance evidence (121 blocks)**:
  ```
  theme    51 (FSE template blocks: site-title, navigation, query-loop, ...)
  design   25 (layout/structural: columns, group, cover, ...)
  widgets  18 (search, calendar, archives, ...)
  text     15 (paragraph, heading, list, quote, ...)
  media    10 (image, audio, video, gallery, ...)
  reusable  1 (block)
  embed     1 (embed)
  ```
- **ontology impact**: 7-value closed enum derived from instances. schema PR 후보 (upstream Gutenberg).
- **action**: ontology `Category` slot enum = these 7 + flag for plugin extensibility.

---

#### G3.3 — parent vs ancestor: semantic distinction

- **parent (25 blocks)**: direct child constraint
  - Examples: `button → buttons`, `column → columns`, `accordion-heading → accordion-item`
  - Tight UI composition (Material composite components)
- **ancestor (15 blocks)**: recursive nesting constraint
  - Examples: `comment-author → comment-template (anywhere within)`, `post-template → query (anywhere within)`
  - Loop/template patterns (Material slot templates)
- **Asymmetric adoption**: parent more frequent. ancestor is for loop scenarios.
- **ontology impact**: Two distinct slots
  - `ParentConstraint` = direct containment (`directChildOf`)
  - `AncestorConstraint` = recursive containment (`descendantOf`)
- **Binding implications (v2.1)**:
  - parent ↔ Material composite components (Button-in-ButtonGroup)
  - ancestor ↔ Material slot template patterns (Card.Content anywhere in Card hierarchy)
  - ActivityPub: ancestor ≈ Object-in-Collection containment

---

#### G3.4 — apiVersion homogeneity (no evolution mismatch)

- **schema**: `apiVersion` integer, required
- **instance evidence**: all 121 blocks use `apiVersion: 3`
- **prediction was**: mixed versions (1/2/3) — **disproved**. core block migration to v3 complete.
- **ontology impact**: `BlockType.apiVersion` is a discrete enum {1, 2, 3} for plugin-block support; core is exclusively v3. Document migration history in ontology for plugin/theme compatibility analysis (Axismundi targets v3 only).

---

#### G3.5 — `example` schema underuse

- **schema**: `example` defines preview data for inserter UI
- **instance evidence**: 18/121 = 15% blocks provide one
- **interpretation**: most blocks rely on dynamic preview rendering; static `example` is opt-in for blocks where static preview is meaningful (image, columns, heading, ...)
- **ontology impact**: `Example` slot is **optional with low coverage**. Mark as `optional`, not as `widespread`.

---

#### G3.6 — `variations` absent from block.json instances (JS API pattern)

- **schema**: `variations` defined
- **instance evidence**: **0 blocks** in core block-library use `block.json` `variations` field
- **reason**: variations are registered via `registerBlockVariation()` JS API (runtime registration), not via block.json static metadata
- **ontology impact**: Variation ontology slot exists but its grounding is in JS runtime, not block.json. Separate scanning pass needed (P3 supplement or P5-data).
- **action**: Note for v2.0 — ontology `Variation` slot has provenance="js_runtime_registration", not provenance="block_json".

---

#### G3.7 — `__experimental` lifecycle marker (14 blocks)

- **schema**: `__experimental` accepts `true` or string-tag
- **instance evidence**: 14/121 blocks (11.6%):
  ```
  __experimental: true  (12 blocks: core/form*, core/playlist*, core/tab*, core/table-of-contents)
  __experimental: "fse" (2 blocks: core/comment-author-avatar, core/post-comment)
  ```
- **ontology impact**: `BlockType.experimentalMarker` slot with enum `{true, "fse"}` — two semantically distinct experimental statuses:
  - `true`: API may change, opt-in only
  - `"fse"`: limited to FSE context, may stabilize in FSE context first
- **availability impact**: Axismundi production theme must avoid `__experimental: true` blocks. Ontology supports filtering by `availability ∈ {stable, experimental, fse-only}`.

---

#### G3.8 — 7 schema keys with zero instance usage

`blockHooks, render, script, variations, version, viewScript, viewStyle` — all defined in schema, but **0 instance usage** in core block-library.

- **blockHooks**: new API (WP 6.4+), no core blocks yet using it
- **render**: dynamic block PHP render template (core blocks use PHP `register_block_type()` rather than block.json render)
- **script**: legacy combined script (deprecated in favor of view/editor split)
- **variations**: JS runtime API (see G3.6)
- **version**: rarely set
- **viewScript / viewStyle**: only when block needs frontend-specific assets distinct from editor

**ontology impact**: Each of these is a slot, but with **provenance=schema_only**. Plugin / theme blocks may use them (e.g. Axismundi theme blocks). Ontology supports them but flags low core adoption.

---

### Ontology slot extraction (Tier 1 + Tier 2)

**Tier 1 — schema-direct slots**:

| Slot | Required | Schema field | Instance grounding |
|---|---|---|---|
| **BlockType** | (root) | (object root) | 121 instances |
| **BlockName** | ✓ | `name` | namespace=`core` always |
| **Category** | | `category` | 7-value enum derived |
| **ParentConstraint** | | `parent` (array) | 25 blocks (21%) |
| **AncestorConstraint** | | `ancestor` (array) | 15 blocks (12%) |
| **Keyword** | | `keywords` (array) | 46 blocks (38%); ~150 unique terms |
| **StyleVariation** | | `styles` (array) | 8 blocks |
| **Variation** | | `variations` (array) | 0 in block.json; JS API only |
| **Example** | | `example` (object) | 18 blocks |
| **BlockTitle** | ✓ | `title` | 121 |
| **ApiVersion** | ✓ | `apiVersion` | all=3 in core |
| **Description** | | `description` | 121 |
| **Icon** | | `icon` (string\|object) | 121 |
| **TextDomain** | | `textdomain` | 121 |

**Tier 2 — binding-relevant derived slots**:

| Slot | Derivation | Evidence |
|---|---|---|
| **Discoverability** | category + keywords + parent + ancestor + allowedBlocks | inserter UI surface |
| **InserterSurface** | parent + ancestor + allowedBlocks (of would-be host) | composability rules |
| **ComposableConstraint** | full composability rule set | parent + ancestor + allowedBlocks + supports |
| **ContextProvider** | providesContext | (data not collected — TBD) |
| **ContextConsumer** | usesContext | 63 blocks (52%) |
| **PatternAffinity** | blockHooks | 0 in core; plugin territory |
| **ExperimentalMarker** | __experimental enum | 14 blocks, 2 values |

### BlockType ontology skeleton (P1+P2+P3 통합 시점)

```
BlockType
├─ name              (BlockName, identity)
├─ title             (display)
├─ apiVersion        (enum {1,2,3})
├─ category          (Category enum, 7 values)
├─ keywords[]        (Keyword)
├─ icon              (Icon)
├─ description       (text)
├─ parent[]          → ParentConstraint     [direct]
├─ ancestor[]        → AncestorConstraint   [recursive]
├─ allowedBlocks[]   → InserterSurface
├─ supports          → BlockSupport*        (P1: 28 instances)
│                     ├─ background
│                     ├─ color
│                     ├─ spacing
│                     └─ ... (24 more)
├─ attributes        → BlockAttribute*      (P2: 9 schema keys, avg 4 per block)
│                     ├─ type, source, selector, default
│                     ├─ enum, attribute, query, meta
│                     └─ role: {content, local}
├─ styles[]          → StyleVariation
├─ variations[]      → Variation            [provenance: js_runtime]
├─ example           → Example              (optional preview)
├─ providesContext   → ContextProvider
├─ usesContext[]     → ContextConsumer
├─ blockHooks        → PatternAffinity      [new API]
├─ selectors         → (P1 selectors API connection)
└─ __experimental    → ExperimentalMarker   {true, "fse"}
```

**이 시점에서 WordPress ontology skeleton이 처음으로 "형태"를 갖춤**. P4(theme.json) + P5(data)는 이 root에 매달리는 sibling 영역.

### Binding implications (v2.1 prep)

| WordPress slot | Material binding | ActivityPub binding |
|---|---|---|
| `BlockType` | `Component` | `Object` |
| `BlockName` (namespace/slug) | `componentName` | `type` |
| `Category` enum | `componentCategory` | (n/a) |
| `ParentConstraint` (direct) | composite child | `partOf` (single Collection) |
| `AncestorConstraint` (recursive) | slot template | `partOf` (transitive) |
| `usesContext` | prop drilling | `context` field |
| `providesContext` | provider | (n/a) |
| `Variation` | variant preset | (n/a) |
| `StyleVariation` | theme variant | (n/a) |
| `__experimental` | experimental API marker | (n/a) |


---

## #4 — `theme.json settings + styles` (P4 5-way pilot, 2026-05-12)

**Anchor**: theme-handbook/03-theme-json/02-settings/ + 03-styles/
**Sources** (5-way):
- corpus: `dev_handbook_clean/theme-handbook/03-theme-json/02-settings/*.md` (13 files)
- docs: `gutenberg/docs/reference-guides/theme-json-reference/theme-json-living.md`
- schema: `gutenberg/schemas/json/theme.json` (13 settings definitions)
- wp-runtime: `WP-6.9.4/wp-includes/theme.json` (9 settings keys with defaults)
- atlas: `knowledge/wordpress/theme-config/settings/*.md` (6 rules) + `styles/*.md` (6 rules)

### Summary

| n_sources | count | categories |
|---|---|---|
| 5/5 perfect | **4** | **appearanceTools, color, spacing, typography** (Material binding 핵심 4축) |
| 4/5 | 4 | border, dimensions, layout, shadow |
| 3/5 | 5 | blocks, custom, lightbox, position, use-root-padding-aware-alignments |
| 2/5 | 1 | background |
| 1/5 | 1 | residual-governance (atlas-only meta rule) |

**styles (separate)**:
| n_sources | count | axes |
|---|---|---|
| 4/5 | 1 | spacing |
| 3/5 | 3 | color, css, filter, typography |
| 2/5 | 4 | background, border, dimensions, outline, shadow |
| 1/5 | 2 | blocks, elements (runtime-only) |

### Gap details

#### G4.1 — schema vs runtime divergence (defaults absent)

theme.json schema defines 13 settings categories; WP runtime theme.json (6.9.4) provides default values for only **9** of them. Missing runtime defaults for: `background`, `custom`, `layout`, `lightbox`, `position`.

**Interpretation**: schema is forward-compatible (covers all features); runtime defaults are conservative (only set what core themes rely on).

**Ontology impact**: Each ThemeToken slot must indicate `has_runtime_default: bool`. Plugin/theme authors must explicitly set values for non-default categories.

---

#### G4.2 — Atlas coverage of theme settings is 6/13 (46%)

Atlas covers `appearanceTools`, `color`, `spacing`, `typography`, `layout`, `residual-governance` — strategically the most-used + 1 governance meta rule.

**Missing in atlas (settings)**: `background`, `border`, `custom`, `dimensions`, `lightbox`, `position`, `shadow`, `blocks`, `use-root-padding-aware-alignments`

**Interpretation**: Atlas chose to deeply document the core 4 (color/typography/spacing/appearanceTools) + 1 strategic (layout) + 1 governance pattern. The rest are derivable from these patterns.

**Ontology impact**: Tier 1 ThemeToken instances for missing categories use schema+runtime grounding without atlas backing. v2.0 ontology still works; v2.1+ atlas can expand if needed.

---

#### G4.3 — appearanceTools is the most important Block↔Theme bridge

`appearanceTools=true` in theme.json bulk-enables 12+ block support sub-properties:

```
appearanceTools=true →
  border.color, border.radius, border.style, border.width
  color.link
  dimensions.aspectRatio, dimensions.minHeight
  position.sticky
  spacing.blockGap, spacing.margin, spacing.padding
  typography.lineHeight
```

**Ontology impact**: `wp:Bridge[appearanceTools]` is a meta-bridge slot that connects single AppearanceTool to 12+ BlockSupport sub-properties. This is the cleanest single proof that Block and Theme axes connect.

**Binding implications**:
- Material binding: appearanceTools maps to a Component design-tool meta-flag (bulk-enable for design-token-aware features)
- ActivityPub: not directly applicable

---

#### G4.4 — Slug naming divergence (kebab-case vs camelCase)

- corpus: `appearance-tools.md`, `use-root-padding-aware-alignments.md` (kebab)
- atlas: `appearanceTools.md`, no `use-root-padding-aware-alignments`
- schema: `appearanceTools`, `useRootPaddingAwareAlignments` (camel)
- runtime: `appearanceTools`, `useRootPaddingAwareAlignments` (camel)

**Ontology canonical form**: camelCase (matches schema + runtime + atlas). corpus kebab-case is presentation-layer artifact of developer.wordpress.org URL normalization.

---

#### G4.5 — corpus styles axes are not per-page

corpus has only `applying-styles.md` + `styles-reference.md` + `using-presets.md` + `index.md` (4 files), no per-axis pages. By contrast schema defines 10 styles categories and atlas has 6 per-axis rules.

**Interpretation**: WP handbook authors decided styles is "one big reference" rather than separate articles. atlas explicitly split per-axis because rule-grain documentation is more useful for ontology.

**Ontology impact**: Styles ontology slot grounding relies on schema + atlas; corpus is supplementary reference only for styles.

---

### Ontology slots — Theme axis (22 entities)

**Tier 1** (5):
- `wp:ThemeToken` (root class)
- `wp:ThemeToken.appearanceTools` (Bridge — 5/5 agreement, links to 12 BlockSupport sub-properties)
- `wp:ThemeToken.color` (5/5)
- `wp:ThemeToken.spacing` (5/5)
- `wp:ThemeToken.typography` (5/5)
- Plus 6 GlobalSetting variants (border/dimensions/layout/lightbox/position/shadow/blocks/custom/use-root-padding-aware-alignments)

**Tier 2 token subclasses** (5):
- `wp:ColorPalette` → Material.ColorScheme
- `wp:TypographyScale` → Material.TypeScale
- `wp:SpacingScale` → Material.SpacingTokens
- `wp:ShadowPreset` → Material.ElevationTokens
- `wp:LayoutConstraint` → Material.LayoutGrid

**Tier 3 derived** (2):
- `wp:PresetOrigin` (core/theme/user cascade)
- `wp:InheritanceBoundary` (block-level overrides theme-level)

### Block↔Theme Bridges (8)

8 cross-axis bridges connecting BlockSupport to ThemeToken:
- 7 simple bridges (spacing/typography/color/dimensions/shadow/layout/border)
- 1 meta bridge (appearanceTools → 12 BlockSupport sub-properties)

---

### Atlas coverage transformation

| Stage | Atlas references | Coverage |
|---|---|---|
| v0.1 (Block only) | 23/113 | 20.4% |
| **v0.2 (Block + Theme)** | **39/113** | **34.5%** |
| block axis cover | 23/38 | 60.5% |
| **theme-config cover** | **16/16** | **100%** |

GPT's prediction confirmed: **P4 brings atlas coverage from 20% to 34.5%** because theme-config atlas was already mature and waiting for ontology integration.

