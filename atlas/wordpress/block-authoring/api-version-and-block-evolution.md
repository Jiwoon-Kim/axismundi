---
rule_id: block.api-version-and-block-evolution
domain: block-authoring
topic: platform-contract-versioning
field_cluster: block-api-version-substrate
wp_min: "5.0"
wp_recommended: "6.3+"
status: stable
language: js-and-php
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#api-version
    section: "block.json apiVersion field reference"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2020/11/18/block-api-version-2/
    section: "Make Core — Block API Version 2 (WP 5.6) — useBlockProps + author-controlled wrapper"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2023/07/18/blocks-in-an-iframed-canvas/
    section: "Make Core — Blocks in an iframed canvas (WP 6.3, apiVersion 3)"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/
    section: "Edit and Save — useBlockProps usage by API version"
    captured: 2026-05-10
related:
  - block.save-validation-and-equivalence         # the per-content validation pair; this chunk is platform-contract pair
  - block.deprecation                             # historical-content fallback; distinct mechanism from apiVersion
  - block.edit-and-save-contracts                 # the contract API versioned by this field
  - block.wrapper-attributes                      # useBlockProps — apiVersion 2+'s required wrapper-prop helper
  - build-tooling.wp-scripts                      # iframed editor (apiVersion 3) interacts with bundling assumptions
---

# RULE — `apiVersion` — block contract grammar versioning across WordPress generations

## WHEN

You are deciding which `apiVersion` to declare in a
new block's `block.json`, considering whether to
upgrade an existing block's `apiVersion`, or
diagnosing why a block's editor render or
serialization differs unexpectedly from what your
code looks like it should produce. Use this
knowledge when:

- Authoring a new block and choosing the right
  `apiVersion` for the WordPress versions you
  support.
- Migrating an existing block from `apiVersion: 1`
  to `apiVersion: 2` (gaining wrapper control)
  or `apiVersion: 3` (gaining iframed-editor
  compatibility).
- Reading core code that branches on
  `block_type->api_version` and wanting to follow
  what each branch does.
- Diagnosing "my block renders without the
  wrapper class I expect" — almost always:
  apiVersion mismatch between declared value and
  what the JS code assumes.
- Understanding why a block written for
  `apiVersion: 2` works fine in the post editor
  but fails in the Site Editor (iframed canvas
  needs `apiVersion: 3`).

This chunk does **not** cover:

- The deprecation mechanism for handling old
  *stored content* — that is `block.deprecation`.
  Deprecation governs *historical content
  fallback*; this chunk governs *platform
  contract evolution*. Section F documents the
  boundary explicitly.
- The validation/equivalence mechanism for
  *individual block instances* — that is
  `block.save-validation-and-equivalence`. This
  chunk pairs with that one as platform-contract
  half to its per-instance-validation half.
- The `useBlockProps` API surface in detail —
  covered in `block.wrapper-attributes`. This
  chunk references when each `apiVersion`
  requires its use.
- The iframed editor architecture in full —
  Section D covers the apiVersion-3 implications
  at the level needed for declaring blocks; the
  full architecture is its own topic.

The principle this chunk operates under: **An
`apiVersion` is the block type's *contract
generation* with the WordPress platform — which
features it opts into, which conventions it
follows. It is not the age of the block's content,
not a quality ranking, not a migration ladder.
Multiple blocks at multiple apiVersions co-exist
on the same site simultaneously and indefinitely.**

## SHAPE

### A. The field — what `apiVersion` declares

In `block.json`:

```json
{
  "apiVersion": 3,
  "name": "myplugin/my-block",
  ...
}
```

Three currently-defined values:

| Value | Introduced in WP | What it opts into                                          |
| ----- | ---------------- | ---------------------------------------------------------- |
| `1`   | (default; original Gutenberg) | Automatic wrapper element; legacy `edit`/`save` shape       |
| `2`   | 5.6 (Nov 2020)   | Author-controlled wrapper via `useBlockProps()`             |
| `3`   | 6.3 (Aug 2023)   | Iframed-canvas compatibility (Site Editor, fully iframed editor) |

If `apiVersion` is omitted, core treats the block
as `apiVersion: 1`. New blocks should typically
declare `apiVersion: 3` unless targeting older
WordPress versions.

The field is read by core's `WP_Block_Type`
registration machinery and feeds into
*version-branched code paths* (Section E) — not
just at registration but at editor render, save,
and various other points where the block's
behavior is dispatched.

### B. apiVersion 1 — legacy auto-wrap

Default if `apiVersion` is omitted. The original
Block API as shipped with Gutenberg.

**Behavior:**

- Core wraps the block's `edit()` return value in
  a WordPress-controlled wrapper element (a
  `<div>` with auto-generated classes like
  `wp-block`, `wp-block-myplugin-my-block`,
  selection state, alignment classes).
- `useBlockProps()` does not exist (or, in newer
  WordPress, is not consulted for the wrapper).
- The block author returns just the *inner
  content* from `edit()` and `save()`.
- The wrapper element shape is fixed by core: a
  `<div>` always.

**Authoring shape:**

```js
// apiVersion: 1 (or apiVersion omitted):
const Edit = ( { attributes } ) => {
    return (
        <p>{ attributes.content }</p>  // inner content only
    );
    // Core wraps this in <div class="wp-block ...">
};
```

apiVersion 1 blocks still work in current
WordPress; they just don't get the wrapper
control that apiVersion 2 introduced. For new
code there is little reason to choose
apiVersion 1; it persists for backwards
compatibility with the large body of existing
plugins that haven't migrated.

### C. apiVersion 2 — author-controlled wrapper (WP 5.6+)

Introduced WP 5.6. The author owns the wrapper
element; `useBlockProps()` provides the props
core would have applied automatically.

**Behavior:**

- Core does *not* auto-wrap. The block's
  `edit()` return is mounted as-is.
- `useBlockProps()` is required. It returns the
  wrapper props (className, style, accessibility
  attributes, drag handle wiring, etc.) the
  author must spread onto the wrapper.
- Same shape on the save side via
  `useBlockProps.save()`.
- The author can choose the wrapper element —
  `<section>`, `<article>`, `<button>`, anything
  semantic.

**Authoring shape:**

```js
// apiVersion: 2:
import { useBlockProps } from '@wordpress/block-editor';

const Edit = ( { attributes } ) => {
    const blockProps = useBlockProps();
    return (
        <section { ...blockProps }>  // author-chosen wrapper
            <p>{ attributes.content }</p>
        </section>
    );
};
```

The trade-off: more code (the explicit
`useBlockProps`) in exchange for control (the
wrapper element shape). This was the dominant
practical reason for apiVersion 2 — themes and
designers needed semantic flexibility.

### D. apiVersion 3 — iframed canvas compatibility (WP 6.3+)

Introduced WP 6.3. Required for blocks to render
correctly in WordPress's Site Editor and other
iframed editor contexts.

**Why iframing matters:**

- The Site Editor renders the front-end-style
  block canvas inside an `<iframe>` to isolate
  the editor's UI styles from the actual block
  rendering.
- Blocks rendered inside the iframe need
  iframe-aware behavior — for asset loading,
  style injection, and certain DOM APIs.

**What `apiVersion: 3` opts into:**

- Iframe-aware editor mounting. Core knows it can
  mount the block inside an iframe without
  breaking the block's expectations.
- Compatible imports (the block's JS uses
  iframe-aware versions of certain APIs that
  newer Gutenberg packages export).

**Authoring shape:** Identical to apiVersion 2's
shape. The difference is structural — the block
declares it can run iframed; core takes that
permission and uses it where appropriate. No new
required hook calls.

```json
{
    "apiVersion": 3,
    ...
}
```

A block at apiVersion 2 still works in the post
editor (which is not iframed by default). It may
not render correctly inside the Site Editor's
iframed canvas. Updating to apiVersion 3 is the
opt-in for iframed contexts.

### E. Version-branched behavior in core

The `apiVersion` field doesn't just sit in
`block.json` — core's editor and runtime code
branches on it at several points:

| Where                     | What changes by apiVersion                                  |
| ------------------------- | ----------------------------------------------------------- |
| Editor block mount        | Auto-wrap (v1) vs author-wrap (v2+)                         |
| `useBlockProps` consumption | Required (v2+) vs ignored (v1)                              |
| Iframed canvas render     | Iframed (v3) vs non-iframed (v1, v2)                        |
| Asset enqueue assumptions | Per-version dependency expectations                         |

The branching is *deterministic* — given a block's
declared `apiVersion`, core knows exactly which
code path to take. There is no
"try-version-3-then-fall-back-to-version-1"
logic; the declared value drives a single
dispatch.

This is important for the chunk's central
non-fit: *version branching is feature-gate
dispatch, not candidate arbitration*. (Section
F's literacy contribution and Operational Notes
expand this.)

### F. Independence from content — `apiVersion` vs deprecation

The most important conceptual property: **the
block's `apiVersion` and the block's stored
content's age are completely independent**. They
operate at different layers and govern different
concerns.

| Concern                                         | Mechanism             |
| ----------------------------------------------- | --------------------- |
| "What contract does the *current block type*    | `apiVersion` (this   |
| follow?"                                        | chunk)               |
| "How does *previously-stored content* survive  | `block.deprecation`   |
| schema changes in the current block type?"      |                      |

A concrete example to anchor:

```
Plugin A v1.0 ships a block at apiVersion: 1.
   ↓ time passes, content is created
Plugin A v2.0 upgrades to apiVersion: 2.
   - apiVersion declares the contract is now v2.
   - Existing content was saved under v1's save() function.
   - For the existing content to remain valid:
     - Either the v2 save() produces the same output as v1 did
       (no deprecation needed), OR
     - A deprecation entry captures v1's save shape (deprecation
       handles the historical case while apiVersion declares
       the present grammar).
```

The two mechanisms intersect when the *content*
was authored under one apiVersion and the *block
type* now declares another. The deprecation
machinery handles content; `apiVersion` handles
the block type's current contract grammar.

The other direction matters too: a block can
**update its `apiVersion` without any deprecation
entry** if its `save()` output remains
equivalent. The apiVersion change is purely about
the editor-side contract (which hooks are
required, where the wrapper lives); it doesn't
necessarily change the saved HTML.

This independence is why "version branch ≠
migration ladder" — `apiVersion` is platform
contract, not historical fallback.

## WHY

### Why a single integer rather than feature flags

A flags-shaped approach (`{ wrapperControl: true,
iframedSafe: true, ... }`) was considered. The
single-integer approach won because:

- It's a contract version, not a feature menu.
  Each `apiVersion` is a coherent bundle of
  conventions; mixing flags arbitrarily would
  produce combinations that core doesn't test.
- Predictability: "this block follows api 2"
  tells core everything it needs; "this block
  uses these 7 of the 10 flags" requires more
  cognitive load.
- Backwards compatibility is cleaner — bumping
  the number signals "this is a different
  contract generation" while leaving older
  blocks alone.

The cost is some loss of granularity (you can't
opt into wrapper control without iframed
support, for instance — though in practice you
*should* want both together when targeting
modern WordPress). The benefit is a clean
versioning narrative.

### Why apiVersion declares contract, not capability

A block's `apiVersion: 2` doesn't mean the block
*can* do something. It means the block *follows*
the version 2 contract — which includes the
*requirement* to use `useBlockProps()`, not just
the *option* to.

This is the difference between "I am compatible
with feature X" and "I subscribe to convention
X." Compatibility is bidirectional and weak;
subscription is unidirectional and binding. The
contract framing makes it clear that core can
make assumptions: an apiVersion 2 block *will*
use `useBlockProps`, so core can rely on the
wrapper props being declared by the block.

### Why old apiVersions persist

A flag day where all apiVersion 1 blocks stopped
working would have meant breaking countless
plugins that haven't been updated. WordPress's
backwards-compatibility commitment means
apiVersion 1 must continue to work indefinitely.

The cost is core maintains version-branched code
paths forever (or until WordPress decides the
ecosystem has migrated enough). The benefit is
plugin authors choose when to upgrade; users
don't lose blocks when WordPress updates.

### Why `apiVersion` and deprecation are separate

Conflating them would be conceptually messy:

- **`apiVersion` is forward-looking**: "from now
  on, this block follows the v2 contract."
- **Deprecation is backward-looking**: "if you
  encounter old content saved under shape X,
  here's how to read it."

The two answer different questions. A block can
update `apiVersion` *without* changing its `save`
output (no deprecation needed). A block can ship
deprecation entries *without* changing
`apiVersion` (the contract is the same; just the
attribute schema migrated). The two mechanisms
compose; collapsing them would lose the
distinction.

## WHEN NOT

Skip `apiVersion` reasoning if:

- The block targets **only one specific WordPress
  version** that pins a particular apiVersion.
  Just declare it and move on.
- You are **diagnosing a content issue** rather
  than a contract issue. Content issues live in
  validation / deprecation territory; apiVersion
  is the contract layer above.
- You are working on **registration internals**
  (the apiVersion is a field core reads; the
  field's role is documented here, but the
  registration handlers themselves are core
  internals).
- You are working with **non-block code** (a
  classic theme, a non-block plugin). The
  apiVersion is a per-block-type field; code
  outside the block-registration flow doesn't
  consult it.

## COUNTER-PATTERNS

### Anti-pattern 1 — "Higher version = better" reasoning

```json
{
    "apiVersion": 3
}
```

…declared on a block whose JavaScript still uses
the apiVersion 1 shape (returning inner content
without `useBlockProps()`). The block declares
"I follow v3 conventions" but doesn't actually
follow them. Result: unpredictable rendering.

`apiVersion` is a contract subscription, not a
performance dial. Pick the version your code
actually implements.

### Anti-pattern 2 — Treating apiVersion as a migration entry

```js
// Hoping that bumping apiVersion will "migrate" old content.
{
    "apiVersion": 2,  // bumped from 1
    // No deprecation entry for the old save() shape.
}
```

Bumping `apiVersion` does not migrate stored
content. The change applies to the contract
between block type and core; existing content
must still serialize-equivalently to whatever
the new `save()` produces, or fall back to a
deprecation entry. Bump apiVersion *and* ship
the deprecation if the save shape changed too.

### Anti-pattern 3 — Mixing apiVersion conventions in code

```js
// apiVersion: 2 declared, but Edit returns inner content as if v1:
const Edit = ( { attributes } ) => (
    <p>{ attributes.content }</p>  // no useBlockProps
);
```

apiVersion 2 requires `useBlockProps`. Without
it, the wrapper props don't get applied; classes,
alignment, etc. don't render. Use the version's
required hooks consistently.

### Anti-pattern 4 — Assuming Site Editor compatibility automatically

```json
{
    "apiVersion": 2
}
```

…and finding the block doesn't render correctly
in the Site Editor's iframed canvas. Site Editor
needs apiVersion 3; the block needs to opt in.
Bumping the value is the opt-in.

### Anti-pattern 5 — Editing apiVersion mid-development without testing

```
Day 1: built block at apiVersion 2.
Day 30: bumped to apiVersion 3 because "newer is better."
Day 31: editor preview broken in some contexts.
```

Each apiVersion bump is a contract change. Test
in the contexts the new version is meant to
support (post editor, Site Editor, widget editor,
etc.). The version bump should be a deliberate
move, not a casual update.

### Anti-pattern 6 — Looking up "what was added in apiVersion N" by trial

The `apiVersion` mapping to features is
documented (Section A's table is the summary).
Don't experiment to find out what each version
unlocks. Reading the Make Core posts (linked
under Sources) is faster than guessing.

## OPERATIONAL NOTES

The apiVersion substrate's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit. The `apiVersion` value is
  *declared* in `block.json`; it is *exposed*
  through core's version-branched code paths
  every time the block is mounted, rendered, or
  serialized. The value declares which contract
  the block subscribes to; core's branched
  handlers dispatch accordingly. Naming Law 1
  here is genuinely clarifying because the *gap*
  between "the block declares apiVersion 2" and
  "core treats this block under version-2
  conventions" is the substrate's defining
  property.
- **Doctrine 5 (Authority Continuity)** is
  **strong**, in a *platform-evolution* form.
  The block's identity (its name, its registered
  type) persists across apiVersion bumps. A
  block updated from apiVersion 1 → 2 → 3 is
  *the same block* — its content remains valid,
  its name doesn't change, its semantic role on
  the page doesn't shift. The apiVersion is a
  *contract generation* the block subscribes to;
  the block's identity continues across the
  generations. This is parallel to but distinct
  from the validation chunk's
  representational-drift continuity: there it
  was *content* surviving HTML normalization;
  here it is *block type identity* surviving
  contract evolution.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Explicit
  non-fit.* Version branching is *deterministic
  feature-gate dispatch*, not candidate
  arbitration:
  - Each block has *one* declared apiVersion.
  - Core's branched handlers select *one* path
    based on that value.
  - There is no "try v3 first, fall back to v2"
    logic; no candidates compete.
  - The mechanism is `if apiVersion === N do X`,
    not "walk a ladder until match."
  Naming Law 4 here would conflate
  *deterministic dispatch* with *candidate
  selection*. The phrasing worth pinning:
  *version branch ≠ candidate arbitration; a
  switch-on-declared-value is not a search
  through ordered alternatives*.
- **Federation.** *Explicit non-fit, with
  precision.* Multiple blocks at different
  apiVersions co-exist on the same site, but
  this is *parallel co-existence*, not
  federation. Each block subscribes to its own
  contract independently; there is no shared
  registry that they federate around (in the
  way that hooks federate around a shared
  dispatch table or stores federate around the
  registry). A block at apiVersion 1 doesn't
  affect a block at apiVersion 3; they are
  independent occupants of the same plugin
  ecosystem. *Co-existence ≠ federation.*
- **Doctrine 6 (Authority Mediation).** No
  access mediation. The apiVersion declaration
  is open; any block can declare any value.
  Core dispatches; it doesn't authorize.
  Omitted.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** apiVersion 3's iframed-canvas
  compatibility *involves* a runtime boundary
  (the iframe), but the apiVersion field
  itself doesn't bridge. It opts the block
  into being safely-mountable across that
  boundary; the bridging logic lives in core's
  iframe-aware mounting code. This chunk's
  topic — the field and its branching — is
  not the bridge mechanism. Adjacent;
  not-the-same. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** The
  apiVersion field is read at runtime
  (registration). The build pipeline doesn't
  consume it. Omitted.
- **Section X archetypes.** A platform-version
  field is not a "civilization." Same
  framework-omission discipline as the
  surrounding chunks. Omitted.

Three literacy contributions worth pinning:

> *Platform versioning ≠ content versioning.*
> The version a block type declares (which
> contract it follows) is not the same as the
> age of the content stored under that block
> type. A block at apiVersion 3 may have
> content authored years ago under apiVersion 1
> conventions; the content's saved HTML remains
> valid (or recovers via deprecation) while the
> block type's contract has evolved
> independently. Two distinct continuity
> surfaces — platform contract and content
> serialization — operate in parallel.

This contribution adds a *platform-vs-content
versioning* form to the existence-vs-operation
toolkit. Where prior toolkit entries focused on
state transitions within one entity, this names
the asymmetry between the *contract layer* and
the *content layer* — both can evolve, but their
evolution rates and mechanisms are independent.

> *Version branch ≠ candidate arbitration.* A
> mechanism that dispatches on a declared
> integer value to select one of N predefined
> code paths is not the same as a mechanism
> that walks ordered candidates until one
> matches. Both involve "choosing among
> alternatives," but version branching's choice
> is *direct lookup* (the declared value names
> its branch); arbitration's choice is *search*
> (each candidate is tested in turn). Different
> shapes; different time complexity; different
> mental models.

This contribution adds an eighth distinct
example to the anti-Law-4 inventory:

- *Need fulfillment ≠ option arbitration*
  (resolver lifecycle).
- *Availability ≠ activation* (JIT
  translations) implicit.
- *Formula-driven selection ≠ candidate
  arbitration* (plural forms).
- *Operator-selected ordering ≠ candidate
  arbitration* (list tables).
- *Layer precedence ≠ candidate arbitration*
  (theme.json source layering).
- *Hook priority ≠ candidate arbitration*
  (hooks lifecycle).
- *Scheduled queue ≠ candidate arbitration*
  (cron).
- *Version branch ≠ candidate arbitration*
  (this chunk, apiVersion).

Eight distinct mechanisms whose surface
vocabulary tempts a Law 4 reading without
sharing the underlying mechanism.

> *Contract evolution ≠ historical fallback.*
> A mechanism that declares "this block now
> follows the v2 contract" is structurally
> distinct from a mechanism that says "if you
> encounter content saved under the old
> shape, here's how to read it." Both relate
> to "change over time," but `apiVersion`
> faces forward (which contract from here on)
> while deprecation faces backward (which old
> content can still be read). The two compose
> when needed, but they are different chunks
> with different audiences.

This contribution clarifies the boundary
between `apiVersion` (this chunk) and
`block.deprecation` — the same kind of
boundary specification produced in Phase
8.37 between validation (yes/no comparison)
and deprecation (recovery ladder).

## CHECKLIST

When choosing or upgrading `apiVersion`:

- [ ] Pick the highest apiVersion supported by
      your minimum WP version: 3 for WP 6.3+,
      2 for WP 5.6–6.2, 1 for older.
- [ ] If declaring apiVersion 2 or 3, use
      `useBlockProps()` in `edit` and
      `useBlockProps.save()` in `save`.
      Required, not optional.
- [ ] When bumping apiVersion, *also* check
      whether `save()` output changed. If yes,
      ship a deprecation entry too — the two
      mechanisms compose.
- [ ] When bumping to apiVersion 3, test in
      the Site Editor specifically (not just
      the post editor) to verify iframed
      compatibility.
- [ ] Don't treat apiVersion as a quality dial.
      It's a contract subscription; pick what
      your code actually implements.
- [ ] When migrating a long-lived block,
      consider whether the apiVersion bump is
      worth the testing cost. Often the
      pragmatic choice is to ship the bump in
      a deliberate update with documentation.

## REFERENCES

- block.json apiVersion reference. Documents
  the field and its accepted values.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#api-version
- Make WordPress Core — Block API Version 2
  announcement (WP 5.6). Documents
  `useBlockProps`, author-controlled wrapper.
  https://make.wordpress.org/core/2020/11/18/block-api-version-2/
- Make WordPress Core — Blocks in an iframed
  canvas (WP 6.3, apiVersion 3 introduction).
  https://make.wordpress.org/core/2023/07/18/blocks-in-an-iframed-canvas/
- Edit and Save reference. Documents
  `useBlockProps` usage by API version.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/

Cross-context:

- `block.save-validation-and-equivalence` —
  the per-block-instance validation pair.
  Together with this chunk: validation owns
  the per-content equivalence check;
  apiVersion owns the per-block-type contract
  generation. The two together form
  block-authoring's *current-content + current-
  contract* governance pair.
- `block.deprecation` — the
  historical-content fallback mechanism.
  Distinct from `apiVersion` (which faces
  forward). Section F documents the boundary;
  the two mechanisms compose when both
  content shape and contract version evolve
  together.
- `block.edit-and-save-contracts` — the
  contract API that `apiVersion` versions.
  Different apiVersions correspond to
  different conventions for `edit` / `save`
  shape; this chunk documents the version
  field, that chunk documents the contract
  itself.
- `block.wrapper-attributes` — `useBlockProps`,
  required for apiVersion 2+. The wrapper-prop
  helper is the principal practical
  consequence of the apiVersion 1 → 2
  transition.
- `build-tooling.wp-scripts` — apiVersion 3's
  iframed-editor compatibility interacts with
  bundling assumptions (some imports must
  use iframe-aware versions). The build
  pipeline's awareness of this is the
  upstream concern.
