# Axismundi Constitution

> Layer principles. These are not negotiable. Violating them collapses the architecture.

**Author**: KIM Ji-woon. See [AUTHORSHIP.md](AUTHORSHIP.md) for decision territory and the relationship between this Constitution and tools (GPT, Claude) used during its drafting. The articles below are the author's design judgments codified into governing structure; LLMs assisted drafting and challenge-testing but did not author them.

**Constitution version**: v3.3.0 (12 articles; Article 1 amended from 4-layer to 6-layer canonical mapping)

---

## Article 1 — Six layers, distinct authorities

The system has six conceptual layers. Each layer has its own authority and its own form of correctness:

| Layer | Folder | Authority | Form | Tests | Role |
|---|---|---|---|---|---|
| **A. Corpus** | `corpus/` | source-of-truth (upstream docs) | raw + refined text | "is it preserved faithfully?" | 근거층 / source mirror |
| **B. Atlas** | `atlas/` | rule-based knowledge | DDD-partitioned markdown with WHEN/THEN | "is the rule discoverable and consistent?" | 판단층 / rule knowledge |
| **C. Core** | `core/` | formal ontology | typed JSON-LD entities | "does the type system agree with multiple sources?" | 구조층 / formal type system |
| **D. Bindings** | `bindings/` | typed translation | confidence-scored mapping | "does the binding pattern operationalize in code?" | 번역층 / runtime translation |
| **E. Products** | `products/` | reference implementations + distributables | working artifacts (themes, plugins, prototypes) | "does it actually run / render / validate?" | 산출층 / reference + ship |
| **F. Tools** | `tools/` | builders, validators, generators | scripts (Python, Node) | "does it reproduce the artifact from layer inputs?" | 자동화층 / build + validate |

The layers form a directed pipeline (with feedback loops):

```
A Corpus       (원문 근거)
   ↓
B Atlas        (규칙 지도)
   ↓
C Core         (형식 온톨로지)
   ↓
D Bindings     (WP-M3 매핑)
   ↓
E Products     (프로토타입/테마/플러그인)
   ↑
F Tools        (생성기/검증기)
   ↑—— validates / builds / publishes ←—— A B C D E
```

These layers are **not collapsible**. The atlas is not "less formal core" — it's a different kind of knowledge (rule-based vs. type-based). E is not "executable C" — it's a different kind of correctness (does-it-ship vs. is-it-formally-true). F is not "scripts in E" — F's authority is *reproducibility from layer inputs*, not the products themselves.

When in doubt, ask: *what fails if this layer is removed?* If only "we lose ability to do X" is the answer, X belongs to that layer.

### Historical note

The 6-layer architecture was first articulated by external analysis during Phase 8 doctrinal era. Originally documented as the ABCDEF mapping:

```
A = Corpus / Source Mirror
B = Atlas / Rule Knowledge Layer
C = Ontology Core / Formal Type System
D = Bindings / Runtime Translation Layer
E = Products / Reference Implementations / Distributables
F = Tools / Builders / Validators / Generators
```

This is the structural backbone the monorepo (v3.0.0+) materializes. Each top-level folder corresponds to exactly one layer; cross-layer reference is structured rather than arbitrary.

---

## Article 2 — Platform ≠ Design system ≠ Federation

The three orthogonal authority axes of Axismundi:

- **Platform ontology**: `core/wordpress/` — content authority, block model, theme.json schema
- **Design ontology**: `core/design-systems/<name>/` — design tokens, components, semantic patterns
- **Federation ontology**: `core/federation/<protocol>/` — interop, identity, social graph

A binding connects exactly two axes:
- `wordpress × material3` → `bindings/wordpress-material3/`
- `wordpress × activitypub` → `bindings/wordpress-activitypub/` (future)
- `material3 × activitypub` → `bindings/material3-activitypub/` (unlikely but allowed)

**Do not** put Material entities under `core/wordpress/`. **Do not** put ActivityPub entities under `core/wordpress/`. They are separate axes.

---

## Article 3 — Design systems are replaceable; bindings are derivative

Replacing the design system is a `bindings/` change. The platform ontology never changes when the design system swaps.

Adding `core/design-systems/fluent/` requires:
1. Building Fluent's token + component ontologies in `core/design-systems/fluent/`
2. Creating `bindings/wordpress-fluent/` with confidence-scored translation
3. Adding products that consume the new binding (e.g., `products/theme-pilot-fluent/`)

WordPress core ontology, atlas, corpus — **untouched**.

This is the single biggest reason for the v3 structure. Without it, design system choice becomes baked into the platform model.

---

## Article 4 — Tools operate on layers; tools are not a layer

`tools/refine/` operates on `corpus/`. It is not part of corpus.
`tools/builders/` operates on atlas and corpus, emits core. It is not part of core.
`tools/validators/` reads from core, bindings, products. It is not part of any.

When a script could fit either as a tool or a layer entry — it's a tool. Layers are about knowledge representation; tools are about transformation between them.

---

## Article 5 — Provenance is mandatory

Every core ontology entity declares its provenance from a fixed taxonomy:

```
schema+instance              ← schema-defined AND observed in real artifacts
schema+php_runtime           ← schema-defined AND set in WP PHP runtime
schema+js_runtime            ← schema-defined AND set in JS API
schema_only                  ← in schema only (no observed use)
schema+instance_partial      ← schema schemas it; instances partial
instance_only                ← observed but not in schema
instance_derived_enum        ← schema is open; instances reveal closed set
```

Every binding entity declares its confidence (0.0–1.0) and its binding_pattern (e.g., `role_to_slug`, `meta_flag_to_capability_bundle`, `level_to_preset`).

No entity has anonymous origin. If you can't say where it came from, it doesn't belong in core or bindings yet.

---

## Article 6 — Three-source minimum for high-confidence bindings

A binding earns confidence ≥0.85 only when **three independent sources** agree:

- corpus + docs + schema (no runtime/atlas evidence)
- corpus + schema + atlas (no runtime evidence — still 0.85)
- 5/5 agreement (corpus + docs + schema + runtime + atlas) → 0.90+

This makes binding strength *cheap to audit and hard to fake*. The v2.1a P0 strong bindings (color 0.95, appearanceTools 0.90, typography 0.85) all earned this through P4's 5-way validation.

---

## Article 7 — Products consume the layers; products don't define them

`products/theme-pilot/` reads from:
- `core/design-systems/material3/runtime/` (CSS assets)
- `bindings/wordpress-material3/` (which block styles to register)
- `core/wordpress/` (which WP slugs to use)

It does **not** define new bindings or ontology entities. If a product needs a new binding, the binding goes in `bindings/`, not in the product.

This means the product is a thin consumer — and any product implementation that satisfies the same binding is interchangeable.

---

## Article 8 — Naming reflects function, not history

- `refine` is a process; the layer it produces is `corpus/refined/`. The tools are `tools/refine/`.
- `knowledge` is too vague; the layer is `atlas/` (a topological knowledge structure).
- `ontology_core` is too specific to one implementation; the layer is `core/`, and the WordPress part is `core/wordpress/`.

When uncertain about naming, ask: *what would this be called by someone who hadn't seen the history?* That's the right name.

---

## Article 9 — Backward compatibility is not a layer concern

If a v0.1 ontology entity is wrong, it gets corrected — not preserved for history. Provenance metadata records what changed and when. Old jsonld files may live in `core/wordpress/pilots/` for archaeological reference, but the canonical `ontology.jsonld` reflects current truth.

This is why version pinning (WP 6.9.4, GB 23.1.1) happens at the corpus layer, not the core layer. Core ontology is *about* a pinned corpus, but core entities can be revised without rewinding the corpus.

---

## Article 10 — Failure modes to watch

The architecture is robust *if* these aren't allowed:

1. **Material entities under `core/wordpress/`** — collapses platform/design separation
2. **Atlas rules emitted from core** — collapses B/C distinction (rule-based vs. type-based)
3. **Bindings without confidence** — produces "equivalences" instead of translations
4. **Products defining their own ontology entities** — makes products non-interchangeable
5. **Tools persisting state in layer directories** — pollutes layers with tool artifacts

If any of these happen, fix immediately. The architecture doesn't survive normalization debt.

---

## Article 11 — Design Doctrine is delegated, not duplicated

Design-system-specific architectural rationale lives in each design system's own `DESIGN-DOCTRINE.md`, not in this constitution. This constitution governs the **layer architecture**; the doctrine governs **why a specific design system was implemented a particular way**.

For Material Design 3, see: `core/design-systems/material3/DESIGN-DOCTRINE.md`

Key locked decisions for M3 (canonical reference, do not duplicate here):

- `tokens.css` is the design Source of Truth
- `theme.json` is an ingestion layer, not the authority
- Modern CSS (color-mix, light-dark, oklch) must be preserved
- WordPress inspector GUI limitations are accepted; plugin layer replaces them
- `var()` chains, not static hex

When other design systems (Fluent, Carbon, Cupertino) are added, each gets its own `DESIGN-DOCTRINE.md` capturing decisions specific to that system. The constitution does not gain a copy of those decisions.

---

## Article 12 — Publishing surfaces are mirrors, not authorities

Some files in the repository exist to be **published** (rendered by GitHub Pages, viewed in a browser, served to external readers). They are not source authorities — they are *projections* of source authorities into a publish-ready form.

Current publishing surfaces:

- **`/index.html`** — project landing (mirrors README structure)
- **`/styleguide/`** — visual style guide (mirrors `products/reference-implementations/axismundi-lab/style-guide*.html`, as of v3.3.0; previously mirrored `axismundi-prototype/`, now archived)

Future publishing surfaces:

- **`/docs/`** or similar — auto-generated reference documentation (mirror of `core/*` ontologies)
- **`/binding-explorer/`** — interactive visualization of `bindings/` (mirror of `bindings/wordpress-material3/binding_map.json`)

**Rules**:

1. **Source authority comes first.** A publishing surface only mirrors something that already exists as a source authority elsewhere in the layered architecture.
2. **Generators are explicit.** Every publishing surface is regenerated by a script in `tools/generators/`. The script's name describes what is being published.
3. **Do not edit files in a publishing surface.** Edit the source, then re-run the generator.
4. **Publishing surfaces are not layers.** Removing `/styleguide/` doesn't lose information — the lab still has the canonical style guide. Removing the lab **does** lose information.
5. **Source authority migrates as the project evolves.** v3.1.0 set prototype as authority; v3.3.0 moved authority to lab after prototype was demoted to legacy. The publish script reflects the current authority; the Constitution records each migration.

This rule prevents the worst failure mode of a monorepo: edits made directly to derived artifacts that then drift from their source, creating two contradictory truths and no way to know which is canonical.
