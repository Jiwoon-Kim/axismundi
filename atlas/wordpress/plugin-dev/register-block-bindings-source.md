---
rule_id: plugin-dev.register-block-bindings-source
domain: plugin-dev
topic: extensibility
field_cluster: authority-federation
wp_min: "6.5"
wp_recommended: "6.5+"
status: evolving
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/register_block_bindings_source/
    section: "register_block_bindings_source() — PHP API for source provider registration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/
    section: "Block Bindings API — source registration + callbacks"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/news/2024/02/20/an-introduction-to-block-bindings/
    section: "Introduction to Block Bindings — custom source provider examples"
    captured: 2026-05-09
related:
  - block-authoring.block-json.bindings        # consumer side — declares the attachment that this API provides the source for
  - data-layer.entity-resolution               # if source resolves through entity store, depends on data-layer substrate
  - data-layer.persistence                     # writable sources cross persistence boundary
  - block.json-context                         # uses_context array references context propagation system
  - interactivity.runtime-state                # actions may invoke registered sources during reactive flows
  - (planned) plugin-dev.register-meta         # parallel registration: meta = persistence extension; bindings source = authority origin
  - (planned) plugin-dev.rest-route-permission # capability/permission enforcement at REST boundary
  - (planned) plugin-dev.security-boundaries   # trust/permission ontology — first surfacing in this chunk
---

# RULE — `register_block_bindings_source()` — plugin-side authority provider registration

## WHEN

A plugin needs to make new runtime authority origins available to
block bindings. Use this API when:

- An entity field, external API, computed value, or non-WordPress
  data source should be available as a bindings `source` for
  block attributes.
- The plugin extends `core/post-meta` with custom resolution
  (e.g., transformed values, multi-source aggregation).
- A SaaS / external service / headless data source needs to feed
  block content through bindings.
- Pattern overrides or custom workflow state should be surfaced as
  bindings sources for blocks to project.

This is the **first plugin-dev chunk in KB**. It is positioned as
the bounded context's **Phase-7-native entry point** rather than
beginning with classic procedural APIs (register_post_type,
register_taxonomy). Modern Gutenberg-native plugin development
extends Gutenberg's authority architecture through registration
APIs like this one — "old-school plugin = procedural extensions"
gives way to **plugin = authority federation participant**.

## SHAPE

### Registration call

```php
register_block_bindings_source( 'myplugin/my-source', array(
    'label'              => __( 'My Custom Source', 'myplugin' ),
    'get_value_callback' => 'myplugin_get_source_value',
    'set_value_callback' => 'myplugin_set_source_value', // optional
    'uses_context'       => array( 'postId', 'postType' ), // optional
) );
```

### Parameters

| key | type | role |
|---|---|---|
| `label` | `string` (translatable) | Human-readable name shown in editor UI when source is referenced |
| `get_value_callback` | `callable` | Returns the resolved value for a binding (read path) |
| `set_value_callback` | `callable` (optional) | Persists a new value back through the source (write path; presence enables writable bindings) |
| `uses_context` | `array<string>` (optional) | Block-context keys the source depends on (parallels block.json `usesContext`) |

### get_value_callback signature

```php
function myplugin_get_source_value( array $source_args, WP_Block $block, string $attribute_name ) {
    // $source_args — args passed in block bindings metadata
    //   (e.g., { "key": "custom_field" })
    // $block — the WP_Block instance with $block->context for context values
    // $attribute_name — which attribute is being resolved
    return $resolved_value; // string typically; varies by attribute type
}
```

### set_value_callback signature (writable sources)

```php
function myplugin_set_source_value( $value, array $source_args, WP_Block $block, string $attribute_name ) {
    // Persist $value back through the source
    // Return value semantics — verification-needed
}
```

### Source name namespacing

Source names follow `vendor/slug` convention parallel to block
names. Core sources: `core/post-meta`, `core/pattern-overrides`.
Plugin-registered sources MUST namespace to avoid collisions.

### Authority directionality (3 modes by callback presence)

| mode | get_value_callback | set_value_callback | semantics |
|---|---|---|---|
| **read-only** | provided | not provided | external authority projects into block; editor cannot persist |
| **writable** | provided | provided | editor mutations persist back through the source |
| **bidirectional** | provided | provided + change emission | full reactive sync (verification-needed for exact protocol) |

### Runtime boundary considerations

| boundary | callback role |
|---|---|
| server-side render (`render_callback` / dynamic rendering) | get_value_callback runs to resolve block attribute values during PHP render |
| editor canvas | get_value_callback (typically via REST or JS-equivalent) populates block UI |
| save / persistence | set_value_callback (when writable) flushes editor edits through the source |
| hydration | server-resolved values bake into HTML; client may re-resolve via additional fetches |

## REQUIRES

- WP 6.5+ (Block Bindings API).
- Source name MUST be `vendor/slug` format with valid namespace.
- `get_value_callback` MUST be a valid callable (function name,
  method, closure).
- For writable sources: `set_value_callback` MUST be provided AND
  the underlying authority must support persistence (post meta is
  writable; computed/external read-only sources can't be made
  writable trivially).
- `uses_context` keys (if provided) MUST match block.json
  `usesContext` declarations on consuming blocks (or context
  values won't be available in `$block->context`).
- For sources that read from entities / external services:
  appropriate capability checks inside the callbacks (no
  automatic enforcement at this API layer).
- ⚠ Exact set_value_callback contract (return value, error
  semantics, async support), interaction with block validation,
  hydration timing for plugin-registered sources —
  verification-needed.

## INVARIANTS

### 1. Plugin APIs register authority origins, not merely content types

Pre-Gutenberg-Phase-7 plugin development was largely about
registering content types and procedural extensions:
- `register_post_type()` — new content type
- `register_taxonomy()` — new classification
- `add_action()` / `add_filter()` — procedural intervention

`register_block_bindings_source()` is qualitatively different:

> **Plugins register new AUTHORITY ORIGINS** that block bindings
> can attach to. The plugin doesn't just add content; it adds a
> NEW LOCUS in Gutenberg's distributed authority architecture.

This is the modern Gutenberg-native plugin model: extending
authority surfaces rather than adding procedural endpoints.

### 2. Bindings sources externalize Gutenberg's authority graph

Pre-bindings, the authority graph in Gutenberg was largely
internal:
- block attributes (block-authoring)
- theme.json declarations (theme-config)
- entity state (data-layer — but accessed only through core APIs)

With bindings sources:

> The authority graph becomes **externalized** — plugins can
> introduce entirely new authority origins that participate in
> the same graph as core's `core/post-meta` and
> `core/pattern-overrides`.

A binding declared as `source: "myplugin/external-api"` triggers
the same materialization pipeline as `source: "core/post-meta"`.
Block markup, materialization, hydration, and persistence all
treat plugin-registered sources as first-class authority origins.

### 3. Source providers are authority federation nodes

Each registered source is a **node in WordPress's authority
federation**:

```
authority federation (post-bindings, post-plugin-dev):

   core/post-meta                ── core
   core/pattern-overrides        ── core
   myplugin/external-api         ── plugin A
   anotherplug/computed-field    ── plugin B
   themex/template-override      ── theme
   ...
```

WordPress is no longer a closed authority architecture; it is a
**governed extensibility ecosystem** where authority origins
federate from multiple actors (core, plugins, themes, custom
code). Each registered source becomes part of the bindings
graph; consumers (block bindings declarations) reference them
by name.

### 4. Plugin-dev enters at architecture extension, NOT surface customization

This invariant positions plugin-dev bounded context within KB:

| plugin-dev framing | character |
|---|---|
| classic ("plugins customize WP") | surface-level: hooks, admin pages, custom post types |
| **modern ("plugins extend Gutenberg's authority architecture")** | **architecture-level: register new authority origins, persistence substrates, transport boundaries, authoring governance** |

`register_block_bindings_source` exemplifies the modern model.
Subsequent plugin-dev chunks (register_meta, REST routes, block
filters, CPT, slotfills) should be framed within this
architecture-extension paradigm rather than as standalone
"WordPress APIs."

### 5. Read / write directionality determines authority permeability

The presence of `set_value_callback` is a **qualitative gate**,
not a feature toggle:

| mode | authority direction | complexity tier |
|---|---|---|
| read-only (no set_value_callback) | external authority → block (one-way projection) | simple |
| writable (set_value_callback present) | block ↔ external authority (bidirectional permeability) | qualitatively more complex |

Writable sources introduce all the persistence reconciliation
concerns documented in `data-layer.persistence` (optimistic UI,
conflict detection, capability checks, async semantics) —
applied to a custom source instead of core entity state.

Plugins choosing to provide writable sources take on
**reconciliation responsibility** — the runtime can't
automatically resolve conflicts in custom-source authority.

### 6. Authority extensibility introduces trust / security boundaries

This is the **first explicit security framing** in KB. Required
because plugin-dev introduces it structurally:

> When plugins inject authority origins into Gutenberg's
> graph, the runtime must enforce **trust boundaries** — not
> every actor should be able to provide every authority, and
> not every consumer should be allowed to read / write every
> source.

Security surfaces this API exposes:
- **Capability checks** inside `get_value_callback` /
  `set_value_callback` — the plugin author's responsibility;
  no automatic enforcement at registration.
- **Source name uniqueness** — namespacing prevents collisions
  but does not prevent malicious shadowing.
- **Editor visibility** — the editor surfaces the source in UI;
  user-visible labels need translation + careful wording.
- **Persistence implications** — writable sources persist
  through whatever channel the callback uses; requires careful
  capability + sanitization at the persistence point.

This invariant introduces the broader **trust / security**
ontology that subsequent plugin-dev chunks (register_meta,
REST routes) will deepen. Plugin-dev is the first KB context
where security becomes structurally central.

### 7. Plugin APIs expose governance, not raw execution

`register_block_bindings_source()` does NOT give plugins
arbitrary access to the bindings runtime. It gives them a
**governance surface**:

- The runtime invokes registered callbacks at well-defined
  points in the materialization lifecycle.
- The plugin doesn't get to override the materialization
  pipeline — only to participate in source resolution.
- The plugin can't bypass the consumer side (block.json
  bindings declarations); it can only register what consumers
  may opt-in to.

This is **governed extensibility** — plugins extend authority
surfaces but don't get unconstrained execution power. Most
modern Gutenberg plugin APIs follow this pattern.

### 8. Bindings source registration is plugin-dev's Phase-7-native entry point

KB-level positioning:

> Beginning plugin-dev with `register_block_bindings_source`
> rather than `register_post_type` aligns plugin-dev with KB's
> current ontology phase. Old-school plugin entry points
> (CPTs, taxonomies) belong in plugin-dev too — they extend
> entity schemas — but they should be framed within Phase 7's
> "authority extension" paradigm rather than read as
> independent procedural APIs.

The plugin-dev family taxonomy this entry chunk anticipates:

| family | ontology |
|---|---|
| **bindings source** (this chunk) | authority origin federation |
| register_meta | persistence substrate extension |
| REST route | transport boundary registration |
| block filters | authoring governance |
| CPT / taxonomy | entity schema extension |
| slotfills | UI governance |

All read as **external authority architecture extensions**, not
just "WordPress APIs."

## VERIFICATION NEEDED

`status: evolving`. Items requiring verification:

- Exact `set_value_callback` signature and return semantics
  (success / failure handling).
- How asynchronous resolution works in `get_value_callback` —
  can it return a future / Promise / yield?
- Editor-side hydration timing for plugin-registered sources:
  when does the editor re-fetch the resolved value?
- Caching behavior across requests: does the runtime cache
  resolved values, and what invalidates them?
- Behavior when `uses_context` references a context the
  consuming block doesn't declare.
- Conflict resolution semantics for writable sources during
  multi-client / async scenarios.
- Permission failure handling — what does the editor display
  when capability checks reject a save?
- Plugin deactivation behavior — what happens to bindings
  declarations referring to a deactivated plugin's source?
- Capability gating for the registration call itself — can any
  PHP context register sources, or are there enforcement points?
- Source visibility in editor UI — which sources appear in the
  bindings UI when configuring a block.
- Migration path for blocks with bindings to a since-removed
  source.

For practical decisions: trust empirical observation
(`var_dump` in callbacks, browser DevTools for editor flow)
over inferred behavior; the API contract surface is documented
but runtime semantics remain implementation-derived.

## ANTIPATTERNS

- ❌ Registering sources without proper `vendor/slug` namespacing.
  Core sources are unprefixed (`core/...`); plugin sources MUST
  use the plugin's vendor slug to avoid collisions.
- ❌ Skipping capability checks inside callbacks. The API does
  NOT enforce capabilities; if the source provides sensitive
  data or accepts writes, the callback is responsible for
  `current_user_can()` checks.
- ❌ Returning unsanitized values from `get_value_callback` that
  will appear in HTML. Block bindings render the value; XSS
  surface if not escaped at the appropriate point.
- ❌ Registering sources during request processing (e.g., in
  REST callbacks or inside render callbacks). Register at
  `init` (or `plugins_loaded`) so the source is available
  consistently.
- ❌ Assuming `set_value_callback` makes any binding writable.
  The consuming block's UI must support editing the bound
  attribute; some attribute types or block contexts may not
  expose editable bindings even when the source supports
  writes.
- ❌ Building writable sources without conflict resolution
  strategy. Async editing surfaces conflicts (data-layer.
  persistence chunk's invariant 8); plugin authors take on
  reconciliation responsibility.
- ❌ Using bindings sources to inject HTML / executable content.
  Source values are attribute strings; HTML injection bypasses
  block sanitization and should not be the design.
- ❌ Hardcoding the source name in many places throughout the
  plugin. Namespace once; reference via constant.
- ❌ Registering sources that depend on global state mutated
  per-request without considering caching implications. The
  runtime may invoke callbacks more or fewer times than expected.
- ❌ Treating `uses_context` as optional for sources that need
  block context. If the source's `get_value_callback` reads
  `$block->context['postId']`, that key MUST be in `uses_context`
  AND consuming blocks must declare it in `usesContext`.
- ❌ Replacing `core/post-meta` semantics by registering a
  source with similar name pattern. The runtime resolves by
  exact name; competing implementations should namespace
  distinctly.

## RELATED

- `block-authoring.block-json.bindings` — consumer side. Block
  bindings declarations reference registered sources by name;
  this API provides the source side. The two APIs together
  form the bindings system.
- `data-layer.entity-resolution` — many sources resolve through
  the entity store (e.g., custom source reading post meta).
  The entity-resolution chunk's substrate underlies what
  sources project from.
- `data-layer.persistence` — writable sources cross the
  persistence boundary. The reconciliation lifecycle from
  data-layer.persistence applies to plugin-registered writable
  sources.
- `block.json-context` — `uses_context` parameter parallels
  block.json `usesContext`. The block-context propagation
  system is shared infrastructure.
- `interactivity.runtime-state` — actions may invoke source
  resolution during reactive flows (e.g., refetch on state
  change). Cross-substrate coordination uses these APIs.
- (planned) `plugin-dev.register-meta` — parallel registration
  for post meta. register_meta extends the persistence
  substrate; bindings sources extend the authority origin
  set. The two often compose (register_meta makes the meta
  available; bindings source projects it as block authority).
- (planned) `plugin-dev.rest-route-permission` —
  permission_callback patterns for custom REST routes.
  Capability enforcement at the transport boundary; this
  chunk's invariant 6 anticipates this domain.
- (planned) `plugin-dev.security-boundaries` — trust /
  capability / sanitization ontology. First surfaced in this
  chunk's invariant 6; will be formalized as separate chunk
  when plugin-dev has multiple chunks needing the cross-ref.

## META

**plugin-dev bounded context entry chunk.**

This is the FIRST plugin-dev chunk. Positioned as Phase-7-native
entry to align plugin-dev with KB's current ontology rather than
beginning with classic procedural APIs.

**plugin-dev bounded context positioning:**

| plugin-dev family (anticipated) | first chunk |
|---|---|
| **bindings source** | **register-block-bindings-source (THIS)** ✓ |
| register_meta | (planned) |
| REST routes | (planned) |
| block filters | (planned) |
| CPT / taxonomy | (planned — re-framed via Phase 7 ontology) |
| slotfills | (planned) |
| security boundaries (cross-cutting) | (planned — separate chunk when needed) |

**KB-level framing extension:**

> WordPress is not a closed authority architecture. It is a
> **governed extensibility ecosystem** where authority origins
> federate from multiple actors (core, plugins, themes).
> Plugin-dev bounded context documents the registration APIs
> that enable this federation.

This is the first chunk to introduce the "authority federation"
framing. Subsequent plugin-dev chunks should reuse and extend
it rather than reinventing per-API ontology.

**Security ontology entry point:**

This chunk surfaces the trust/security boundary for the first
time as a structural concern (invariant 6). Subsequent
plugin-dev chunks (register_meta, REST routes) will extend the
security ontology. A dedicated cross-cutting chunk
(`plugin-dev.security-boundaries`) is anticipated when at
least 3 plugin-dev chunks need to reference shared security
patterns.

**DSL extensions applied:**

VERIFICATION NEEDED + META, per the runtime/implementation-
derived applicability rule. Plugin extensibility APIs are
implementation-derived in many semantics (caching, async
behavior, conflict resolution).

**Status `evolving`** — Block Bindings API is recent (WP 6.5+);
specific semantics continue to refine.

**KB self-evaluation against spec criteria:**

- ✅ Accuracy — describes documented API contract surface.
- ✅ Structural fit — extends Phase 7 ontology coherently;
  introduces "authority federation" framing for plugin-dev.
- ✅ Reusability — uses authority ontology glossary terms
  (authority origin, projection, attachment, federation).
- ✅ Phase fit — positioned as Phase 7 follow-up; references
  data-layer + interactivity substrate appropriately.
- ✅ Doctrine respect — implicit HTML primacy preserved
  (sources resolve to attribute values projected through
  HTML markup); does not introduce SPA-style framings.

**Anticipated next chunks (priority):**

1. **`plugin-dev.register-meta`** — parallels bindings source as
   persistence substrate extension. Bindings source + register_meta
   often compose (custom meta becomes a custom binding source).

2. **`plugin-dev.rest-route-permission`** — permission_callback
   ontology. Will deepen the security framing introduced here.

3. **`plugin-dev.register-post-type`** — entity schema extension,
   reframed via Phase 7 ontology rather than as standalone API.

4. **`plugin-dev.security-boundaries`** — when 3+ chunks reference
   security patterns, formalize as cross-cutting chunk.

5. Other plugin-dev families (filters, slotfills) on demand.

Recommended next: `register-meta` (high cross-ref leverage
with this chunk; many bindings sources resolve through
register_meta-declared meta fields).
