---
rule_id: block.markup-representation
domain: block-authoring
topic: markup-representation
field_cluster: ir
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: html
sources:
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/markup-representation-block/
    section: "Markup representation of a block — delimiter syntax + dynamic vs static distinction"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/explanations/architecture/key-concepts/#data-and-attributes
    section: "Key Concepts — Blocks, Composability, Data and attributes"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/explanations/architecture/data-flow/
    section: "Data Flow and Data Format — block grammar, parsing, serialization, lifecycle"
    captured: 2026-05-09
related:
  - block.json-attributes-core           # delimiter JSON encodes (sourceless) attribute values
  - block.edit-save-components           # save() produces the HTML half of this representation
  - block.json-context                   # context is NOT serialized into delimiter (runtime only)
  - block.deprecation                    # the parser uses deprecations to recover invalid markup
  - block.dynamic-rendering              # dynamic blocks use the self-closing delimiter form
  - block.inner-blocks                   # nested delimiters represent the tree's child blocks
  - block-authoring.block-json.bindings        # bindings metadata lives in delimiter — same comment carries authority hosting
  - interactivity.directive-protocol           # directive attributes live in HTML — same markup carries reactive grammar
  - interactivity.hydration                    # serialized HTML is the FIRST reactive frame; hydration attaches to it
  - data-layer.entity-resolution               # bound attributes resolve through entity layer; markup carries the binding metadata
  - style-engine.generated-selectors           # runtime-generated classes attach to wrapper elements in this markup
---

# RULE — Block markup representation — delimiters, IR, layered truth

## WHEN

You need to understand or work with how blocks are stored, serialized,
parsed, or validated. This is the IR (intermediate representation)
substrate that all other block-authoring rules sit on top of:

- the storage format (`post_content`)
- the in-memory editor model (block tree)
- the parser/serializer pair that mediates between the two
- the front-end render output (which may match `save()` markup OR be
  generated server-side via `render_callback`)

This chunk describes the **canonical truth model** and the **block
grammar** that defines the IR. Specific consumers (attributes, edit/save,
deprecation) are described in their own chunks.

## SHAPE

### The delimiter grammar

Two valid forms:

```html
<!-- wp:NAMESPACE/SLUG {"attr":"value"} -->
INNER HTML CONTENT
<!-- /wp:NAMESPACE/SLUG -->
```

```html
<!-- wp:NAMESPACE/SLUG {"attr":"value"} /-->
```

Rules:

- All blocks begin with `wp:` prefix.
- **Core blocks** omit the `core/` namespace: `<!-- wp:paragraph -->`,
  not `<!-- wp:core/paragraph -->`.
- Custom blocks include namespace: `<!-- wp:my-plugin/my-block -->`.
- Attributes are encoded as a JSON object directly inside the opening
  comment (after the block name).
- Two structural forms:
  - **Paired** opening + content + closing — used when the block
    contains markup (`save()` returns non-null).
  - **Self-closing** (single comment with `/-->`) — used when the
    block has no markup body, typical for dynamic blocks where
    `save: () => null`.

### Examples

Static block (paired):

```html
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
  <img src="source.jpg" alt="" />
</figure>
<!-- /wp:image -->
```

Dynamic block (self-closing — only attributes, no body):

```html
<!-- wp:latest-posts {"postsToShow":4,"displayPostDate":true} /-->
```

### The in-memory block object

```js
const block = {
  clientId,    // unique editor-session identifier (NOT persisted)
  type,        // namespaced block name, e.g., "core/paragraph"
  attributes,  // current attribute values (parsed or edited)
  innerBlocks, // array of child block objects (recursive)
};
```

During the editing lifecycle the object may also carry:

- `isValid` — boolean, set after the validation cycle
- `originalContent` — the original HTML serialization as parsed

### The block-tree value

```js
const value = [ block1, block2, block3, ... ];
```

The in-memory representation is a tree (root is an array; each block
may have `innerBlocks` arrays recursively).

## REQUIRES

- Comment markers MUST be exact: `<!--`, `-->`, `wp:`, `/wp:`. The
  parser depends on this strictness.
- Attribute JSON, when present, MUST be valid JSON inside the
  comment. Implementations escape `--` sequences inside the JSON to
  avoid breaking the comment.
- Custom block names MUST follow `vendor/slug` format (matching the
  block.json `name` rule).
- The pairing closing comment (`<!-- /wp:NAMESPACE/SLUG -->`) MUST
  match the opening name exactly.
- For nested blocks, child delimiters MUST appear lexically between
  their parent's opening and closing delimiters.

## INVARIANTS

- **Layered truth model** (CRITICAL). There are three distinct
  representations of the same block content:
  1. **In-memory block tree** — array of block objects with
     `clientId` / `type` / `attributes` / `innerBlocks`. *This is the
     editor's source of truth.* Source: *"A block editor post is the
     proper block-aware representation of a post: a collection of
     semantically consistent descriptions of what each block is and
     what its essential data is. This representation only ever exists
     in memory."*
  2. **Serialized `post_content`** — the HTML-with-delimiters string
     stored in the database. The artifact, not the source. Source:
     *"A block editor post is not the artifact it produces, namely the
     `post_content`. The latter is the printed page, optimized for
     the reader but retaining its invisible markings for later
     editing."*
  3. **Front-end render output** — what visitors see. For static
     blocks, equals the HTML body of the delimiter pair. For dynamic
     blocks, generated fresh by `render_callback` / `render.php` on
     every request — may differ entirely from the stored markup.
- **Identity is the defining property of a block, not its data
  location.** Source: *"The defining aspects of blocks are their
  semantics and the isolation mechanism they provide: in other words,
  their identity."* The `vendor/slug` block name is the identity;
  attribute storage location (delimiter JSON vs HTML attributes vs
  external entity) is a secondary concern.
- The delimiter is **not just syntax** — it is simultaneously:
  - **Parser anchor** — comment boundaries let the parser extract
    top-level blocks without a full HTML parse.
  - **Block identity marker** — `wp:vendor/slug` declares which
    registered block type owns this content region.
  - **Attribute boundary** — the JSON inside the comment is the
    attribute payload; outside the comment is rendered HTML.
  - **Migration target** — deprecation entries are matched against
    this exact stored representation.
- **Parsing pipeline:** `post_content` → tokenize on delimiters →
  identify blocks by `wp:NAME` → extract delimiter JSON → for each
  block, run attribute extraction (delimiter JSON for sourceless
  attributes, HTML extraction sources for sourced ones) → produce
  in-memory block tree.
- **Serialization pipeline:** in-memory block tree → for each block,
  invoke registered `save()` (or use stored `originalContent` for
  invalid/unknown blocks) → wrap output in delimiter pair (or emit
  self-closing if save returns null) → concatenate.
- Comments are used as the delimiter mechanism (rather than custom
  HTML elements, processing instructions, or `data-*` attributes)
  because: (a) HTML comments cannot exist inside HTML attributes —
  no ambiguity; (b) the parser does NOT need to fully understand
  HTML — it only needs to find `<!--` ... `-->` boundaries; (c)
  damage to one block's HTML cannot bleed into other blocks.
- Source explicitly: *"by storing data in HTML comments, we would
  know that we wouldn't break the rest of the HTML in the document,
  that browsers should ignore it, and that we could simplify our
  approach to parsing the document."* — graceful degradation: a
  blocks-unaware system can still display the post HTML correctly,
  ignoring the comments.
- **`clientId` is in-memory only.** It identifies a block instance
  during the editing session. It is NOT serialized; reload starts
  fresh `clientId`s. Use `clientId` for editor operations (selection,
  drag, focus); never persist it.
- **Validation cycle** (also covered in `block.edit-save-components`):
  on load, for each parsed block the editor re-invokes `save()` with
  the parsed attributes and compares the regenerated markup byte-by-byte
  to the stored content body. Mismatch → `isValid: false` → user sees
  recovery dialog.
- The "post_content vs tree" round-trip is a **parser/serializer
  pair contract**. If they disagree (e.g., a custom encoder that
  doesn't match the canonical parser), data drift between save / load
  cycles results.
- Dynamic blocks store **attributes only** (no HTML body) in
  `post_content`; the front-end markup is generated server-side per
  request. This means: `post_content` for a dynamic block is small,
  the front-end is fresh, and the editor uses `ServerSideRender` to
  show a preview.
- For attribute storage:
  - **Sourceless attributes** are JSON-encoded into the delimiter
    comment. They round-trip via the comment regardless of the HTML.
  - **Sourced attributes** (`source: 'attribute' | 'text' | 'html' |
    'query'`) live in the HTML body. The block author OWNS the
    round-trip integrity (covered in attributes-html-sources).
- ⚠ **Minimum WP version unknown** — block grammar is part of the
  original Block Editor (WP 5.0+ era) but no `Since:` version is
  documented for the format itself. Frontmatter `wp_min` is
  `"verification-needed"`.

## ANTIPATTERNS

- ❌ Treating `post_content` as the source of truth. It's the
  serialization artifact. The editor's source of truth is the
  in-memory block tree; `post_content` is what comes out of
  serialization. Tools / migrations should round-trip via the parser,
  not regex `post_content` directly (except for read-only or coarse
  bulk-replace operations).
- ❌ Hand-editing delimiters in stored `post_content` without
  understanding the parser contract. Easy to create invalid blocks
  that trigger the recovery UI for users.
- ❌ Putting `--` sequences inside the delimiter JSON without
  escaping. Breaks the comment terminator. WordPress escapes this
  automatically during serialization; manual delimiter construction
  must do the same.
- ❌ Persisting `clientId` (e.g., into attributes or external
  databases). It's session-scoped and changes on every editor reload.
  Use stable identifiers (post IDs, custom attribute slugs) instead.
- ❌ Assuming `post_content` is HTML-pure. It's HTML *plus* invisible
  block delimiters. Generic HTML processors (sanitizers, parsers)
  may strip or corrupt the comments unless explicitly aware of the
  block grammar.
- ❌ Designing a block whose `save()` output cannot be deterministically
  regenerated from its attributes. Validation will mark it invalid on
  every load.
- ❌ Mixing sourceless attribute JSON storage with HTML-source
  storage of "the same" semantic value. Pick one — either store in
  delimiter or in HTML — to avoid round-trip drift.
- ❌ Using paired delimiters when `save: () => null`. The empty body
  between comment markers is wasteful; use the self-closing form
  (`/-->`).
- ❌ Trying to nest delimiters with mismatched `wp:NAMESPACE/SLUG`
  names. Parser fails to pair them; produces unrecognized block.
- ❌ Reasoning about block "validity" purely from looking at
  `post_content`. Validity is determined by the regeneration check —
  i.e., it depends on the current registered `save()` implementation,
  not solely on the stored markup.

## RELATED

- `block.json-attributes-core` — sourceless attributes are JSON-
  encoded into the delimiter; sourced attributes live in the HTML
  body. The split is decided per attribute by the schema.
- `block.edit-save-components` — `save()` produces the HTML body of
  the delimiter pair. The validation cycle regenerates this on every
  editor load.
- `block.json-context` — context is **NOT** part of the IR. It's
  resolved at render-time from ancestor providers' attributes;
  consumed values do not appear in the delimiter or stored HTML.
- `block.deprecation` — when `save()` changes shape, deprecation
  entries describe how to interpret old stored representations.
  Deprecation matching runs against this IR.
- `block.dynamic-rendering` — dynamic blocks use the self-closing
  delimiter form and rely on `render_callback` to produce the
  front-end output fresh per request.
- `block.inner-blocks` — nested blocks appear as their own delimiter
  pairs lexically inside the parent's body. The tree's `innerBlocks`
  array is the in-memory mirror of this nesting.

## RETROACTIVE REFRAMING (post-Phase-7-capstone)

**Status note**: This section was added 2026-05-09 after Phase 7
bounded contexts (bindings + data-layer + interactivity +
hydration) closed. The original chunk above describes block
markup as IR + serialization artifact + parser/serializer
contract. Post-Phase-7, the same markup reads as
**Gutenberg's universal continuity substrate** — not a
serialization artifact, but the transport layer through which
authority crosses every execution boundary.

The original chunk is preserved; this section adds the
post-Phase-7 ontological reading.

**KB pattern**: Third explicit RETROACTIVE REFRAMING section
in KB. The pattern is now firmly established:
- wrapper-attributes (post-style-engine closure)
- dynamic-rendering (post-Phase-7-capstone)
- **markup-representation (post-Phase-7-capstone — THIS SECTION)**

Each retro reveals that earlier chunks documented mechanisms
whose deeper ontological role only became visible after
downstream bounded contexts matured.

### Reframing — HTML as authority continuity carrier

Pre-Phase-7 reading: HTML (with delimiters) is "the serialization
format Gutenberg uses to store post content."

Post-Phase-7 reading:

> HTML in Gutenberg is **the universal continuity substrate**
> that mediates authority crossing every execution boundary.
> It is not the artifact of rendering; it is the **medium**
> through which schema, compiled visuals, persisted entities,
> reactive grammar, and runtime subscriptions all flow.

The KB-level inversion this chunk's retro completes:

| pre-Phase-7 framing | post-Phase-7 framing |
|---|---|
| HTML = serialized content artifact | **HTML = authority continuity carrier** |
| post_content = source of truth (or near-truth) | **post_content = resumable authority graph snapshot** |
| markup = output of save() | **markup = simultaneous attachment topology + authority hosting + reconstruction anchors** |

### RETROACTIVE INVARIANTS

#### A. HTML as authority continuity carrier — backbone

KB has documented authorities flowing through Gutenberg:
- declared authority (block-authoring + theme-config schemas)
- compiled authority (style-engine output)
- persisted authority (data-layer entities)
- reactive authority (interactivity store/directives)

What unifies them: **they all transit through HTML**.

| boundary | mediated through |
|---|---|
| PHP (server) → browser | HTML transmitted across network |
| style-engine compiler → CSS VM | classes/styles attached IN HTML |
| directives (data-wp-*) → JS runtime | attribute declarations IN HTML |
| block parser → editor state | block delimiter comments IN HTML |
| hydration → runtime continuity | runtime attaches to existing HTML |
| bindings → runtime attachment | metadata IN delimiter comments IN HTML |
| generated selectors → cascade | generated classes IN HTML wrapper |
| entity authority → render output | render_callback projects entity → HTML |

HTML is **Gutenberg's universal continuity substrate**. Removing
HTML breaks every layer; introducing other transports (JSON-only
APIs, virtual DOM ownership) breaks WordPress's architectural
philosophy.

#### B. Block delimiters as reconstruction anchors

Pre-Phase-7 framing of `<!-- wp:paragraph {"foo":"bar"} -->`:
"serialization metadata for the parser."

Post-Phase-7 framing:

> Block delimiters are **reconstruction anchors** — points in
> the HTML stream where the parser can recover the in-memory
> block tree, where bindings re-resolve their authority, where
> the editor can re-establish state, and where authority
> continuity restarts after any execution boundary crossing.

Each delimiter encodes simultaneously:
- **Identity** (vendor/slug → which block type owns this region)
- **Attribute payload** (sourceless attributes JSON-encoded)
- **Bindings metadata** (since WP 6.5, metadata.bindings within
  delimiter)
- **Reconstruction parameters** (everything needed to recreate
  the in-memory block at parse time)

Post-bindings, delimiters are not just parser hints — they are
**authority restoration points** that carry attachment
declarations alongside identity and payload.

#### C. Markup carries executable attachment topology

Pre-Phase-7 framing: HTML body inside delimiters is "the
rendered content."

Post-Phase-7 framing:

> HTML body carries **executable attachment topology** —
> declarations of how the runtime should attach behavior,
> styling, and reactivity to the rendered DOM.

Concrete attachments hosted in HTML body:
- `class="wp-block-{type}"` — block-type identity carrier
- `class="has-{slug}-color"` — preset reference carrier
- `class="wp-container-{id}"` — generated selector carrier
- `class="wp-elements-{uuid}"` — element-projection carrier
- `style="color: var(--wp--preset--*)"` — variable consumption
- `data-wp-interactive="namespace"` — interactivity scope
- `data-wp-context='{...}'` — reactive context payload
- `data-wp-bind--{attr}="state.x"` — directive binding
- `data-wp-on--{event}="actions.x"` — event handler attachment
- `id="{anchor}"` — persistent navigation target
- `aria-*` — accessibility hints + directive-bound state mirrors

HTML is no longer "rendered content" alone — it is
**executable authority topology serialized as markup**.
The browser parses it as DOM; multiple runtimes (CSS engine,
Interactivity runtime, accessibility tree) read it as
attachment instructions.

#### D. Serialization becomes partial projection

Pre-Phase-7 framing: post_content is the persisted source of
truth (with the layered-truth nuance about static vs dynamic
acknowledged but framed as exception).

Post-Phase-7 framing:

> post_content is **a resumable authority graph snapshot**, not
> a complete state. It stores enough information to reconstruct
> the block tree + bindings declarations + directive attachments,
> but the AUTHORITATIVE current state often lives elsewhere
> (entities, runtime store, ephemeral state, server-resolved
> values).

This generalizes the dynamic-rendering retro framing:
- Static blocks: post_content carries the full static authority.
- Dynamic blocks: post_content carries the invocation; runtime
  resolves authority.
- **Bound attributes (any block type)**: post_content carries
  the binding declaration; the source resolves authority.
- **Interactive blocks**: post_content carries the directive
  declarations; the runtime store provides reactive authority.

In all but the simplest static cases, **post_content is partial**.
The serialized markup is a projection / snapshot / reconstruction
substrate, not a complete authority record.

This is the retroactive ontological generalization the
dynamic-rendering retro pointed toward; markup-representation
chunk is where it crystallizes at the markup layer.

#### E. HTML mediates between execution environments — Phase 7 capstone linkage

The hydration chunk established that authority continuity
spans 5 execution environments (PHP server / network / JS
runtime / browser CSS / browser cascade). What unifies them
operationally:

> HTML is the **medium** through which authority traverses
> every execution boundary in Gutenberg.

| environment transition | what crosses | medium |
|---|---|---|
| PHP runtime → network | rendered HTML + directives + state | **HTML (transmitted as bytes)** |
| network → browser parse | bytes → DOM tree | **HTML (parsed as DOM)** |
| DOM → browser CSS engine | classes / styles for cascade | **HTML attribute reads** |
| DOM → Interactivity runtime | data-wp-* discovery | **HTML attribute reads** |
| DOM → editor parse | delimiter recovery → block tree | **HTML comment parsing** |
| Interactivity → DOM mutation | reactive updates per state | **HTML attribute writes** |

Every Phase 7 boundary crossing involves HTML as the carrier.
HTML is structurally what makes Gutenberg's distributed
authority system work — without HTML primacy, the boundaries
would need a different transport, and the architecture would
have to change.

### KB-level coherence payoff — HTML primacy

**KB framing extension:**

> Gutenberg's architectural sophistication does NOT abandon
> HTML primacy. As Gutenberg evolved through Phases 1-7
> (schema → compiler → runtime → reactive orchestration),
> HTML's role expanded from "output format" to **"universal
> continuity substrate"** — but it remained foundational.
>
> This is structurally distinct from SPA frameworks, which
> often treat HTML as "intermediate render output" subordinate
> to virtual trees / component models. Gutenberg keeps HTML
> as PRIMARY REALITY through every layer of architectural
> complexity:
>
> - Server renders HTML (not virtual trees).
> - Persistence stores HTML (with delimiter metadata for
>   reconstruction).
> - Style engine attaches to HTML (classes / inline / variables
>   on HTML elements).
> - Directives live in HTML (data-wp-* as authority grammar).
> - Hydration attaches reactivity TO HTML (does not replace it).
> - Interactivity runtime mutates HTML (does not own a virtual
>   alternative).
>
> HTML primacy is WordPress's architectural inheritance, and
> Gutenberg honored it through every modernization step.

### KB narrative closure

This retroactive section, combined with wrapper-attributes
retro and dynamic-rendering retro, completes a 3-level
narrative arc about HTML in Gutenberg:

| retro chunk | what HTML element it reframed | role revealed |
|---|---|---|
| wrapper-attributes | wrapper element + classes/styles/attributes | ABI boundary / authority transport surface |
| dynamic-rendering | server-rendered HTML | authority projection node / first reactive frame producer |
| **markup-representation** | **HTML grammar itself + delimiters + body** | **universal continuity substrate** |

The three retros together produce the structural narrative:

> HTML in Gutenberg is not output, not artifact, not
> serialization format. It is the **operational substrate of
> distributed authority continuity** — the medium through which
> every architectural layer's authority transits, attaches,
> reconstructs, and synchronizes.

This narrative closes the gap between WordPress's
HTML-first historical philosophy and Gutenberg's modern
distributed authority architecture: **the architecture
sophisticated through HTML, not around it**.
