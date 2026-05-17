---
rule_id: block.edit-and-save-contracts
domain: block-authoring
topic: authoring-contract
field_cluster: role-separated-functions
wp_min: "5.0"
wp_recommended: "6.5+"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/
    section: "Edit and Save — function contracts, props, validation rules"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-wrapper/
    section: "The block — Editor markup vs Save markup vs Dynamic render markup"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/
    section: "Block deprecation — adjacency between save changes and validation"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2020/11/18/block-validation-changes-in-wordpress-5-6/
    section: "Block validation — equivalence checking between expected and actual save markup"
    captured: 2026-05-10
related:
  - block.edit-save-components                  # what to use inside edit/save (RichText, InnerBlocks, useBlockProps, etc.)
  - block.json-attributes-core                  # the schema both functions read from / write into
  - block.markup-representation                 # save() output is the canonical IR for static blocks
  - block.wrapper-attributes                    # useBlockProps / useBlockProps.save shape the wrapper element
  - block.deprecation                           # validation failure flow when save shape drifts
  - block.dynamic-rendering                     # save: () => null delegates serialization to render_callback
  - build-tooling.wp-scripts                    # the build that turns these source functions into runtime artifacts
---

# RULE — `edit` and `save` — role-separated authoring contracts

## WHEN

You are authoring a block and need to understand **why
there are two functions** rather than one, and what role
each one plays in the block's lifecycle. Use this knowledge
when:

- Designing a new block and choosing what attributes to
  declare (the choice is partly driven by what `edit` and
  `save` each need to express).
- Diagnosing block validation errors ("This block contains
  unexpected or invalid content") that point at a mismatch
  between what `save` produced when the post was authored
  and what `save` produces now.
- Deciding whether a block should be static (with a
  meaningful `save`) or dynamic (`save: () => null` plus a
  PHP `render_callback`).
- Reviewing a block whose `edit` and `save` outputs visibly
  differ on the front end and trying to understand which
  is authoritative.

This chunk does **not** cover:

- The specific React components and helpers used *inside*
  `edit` and `save` — `RichText`, `InnerBlocks`,
  `useBlockProps`, etc. Those are in
  `block.edit-save-components`.
- The dynamic-rendering pathway in detail (`render_callback`,
  `render.php`, three rendering modes). That is in
  `block.dynamic-rendering`.
- The deprecation flow that handles legitimate `save`
  shape changes over time. That is in `block.deprecation`.

The principle this chunk operates under: **`edit` and
`save` are not "two pieces of one component." They are two
contracts with different audiences, different lifetimes,
and different success criteria.**

## SHAPE

### A. Two functions, two contracts

The block API declares two top-level functions in
`registerBlockType`:

```js
registerBlockType( 'myplugin/my-block', {
    edit: ( props ) => { /* React element rendered in the editor */ },
    save: ( props ) => { /* React element serialized into post_content */ },
} );
```

Both look like React components. Both receive a props
object containing `attributes`. Both return JSX. The
syntactic similarity is misleading.

Read by their *outputs*:

- `edit` produces a **DOM tree the editor mounts and
  manages**. It exists for the duration of the editing
  session and never appears in `post_content`. Its
  audience is the human authoring the post.
- `save` produces a **string of HTML that becomes part of
  the post's stored content**. It is invoked once at save
  time, the JSX is rendered to a string,
  `serialize-block`'d, and committed to the database. Its
  audience is the parser, the validator, the front-end
  renderer, and any future `edit` invocation that has to
  reconstruct attributes from that string.

Read by their *role*:

- `edit` is an **interaction contract**. The promise it
  makes to the editor is "given these attributes, render
  something the user can interact with such that
  `setAttributes` can be called to change them."
- `save` is a **serialization contract**. The promise it
  makes to the parser is "given these attributes, produce
  exactly the same HTML *every time*. The HTML must
  encode the attributes such that the block can be
  re-recognized and reconstituted from `post_content`."

The two roles share an *input* (attributes) and a *block
identity* (registered name). They do not share output
shape, output lifetime, or success criteria.

### B. The interaction contract (`edit`)

`edit` runs in the editor runtime — a React-based
single-page application that holds blocks as live
JavaScript objects in memory. The function is invoked
many times per editing session, on every state change to
its inputs.

Its responsibilities:

- **Render UI for the current attributes.** Whatever
  `attributes.content` is, draw it visually in a way the
  user recognizes.
- **Provide editing affordances.** Wire inputs (text
  fields, color pickers, dropdowns, inspector controls,
  toolbars) to `setAttributes` so user actions become
  attribute changes.
- **Handle the `clientId`-scoped lifecycle.** Each block
  instance has a transient editor-only `clientId`; `edit`
  uses it via the editor's data store but never serializes
  it.
- **Read `context` from ancestors** when `usesContext`
  declares the block consumes ancestor state. (`context`
  is an `edit`-only prop; it is not available in `save`.)

Its non-responsibilities:

- It does **not** decide what gets persisted. Persistence
  is `save`'s domain.
- It does **not** need to produce HTML that matches what
  `save` will produce. The editor's rendered DOM is
  almost always richer (inspector controls, drag handles,
  selection highlights) than the serialized markup.
- It does **not** run on the front end. Front-end output
  comes from either `save`'s serialized HTML (static
  blocks) or `render_callback` (dynamic blocks); `edit`
  is not a participant in either path.

The interaction contract optimizes for *expressivity*.
Anything that helps the author work productively is on
the table — controls, previews, hints, validation
feedback. None of that ends up in `post_content`.

### C. The serialization contract (`save`)

`save` runs **once per editor save action**, not on the
front end. Its return value is rendered to a string, that
string is wrapped in block delimiters, and the resulting
HTML goes into the post's `post_content`.

Its responsibilities:

- **Encode every attribute that needs to round-trip.**
  Any attribute whose value should survive the
  database is either embedded into the HTML (text
  content, image URLs, structural choices) or stored in
  the block's JSON delimiter comment. The `attributes`
  schema decides which mechanism applies (covered in
  `block.json-attributes-core`).
- **Produce deterministic output.** Given the same
  attributes, `save` must produce *exactly* the same
  HTML — every time, in every WordPress install. This is
  the validation contract (Section D). Any
  non-determinism (random IDs, locale-formatted dates,
  user-specific values) breaks block validation.
- **Cooperate with `useBlockProps.save()`** to produce a
  wrapper element whose class and attribute generation
  matches what the editor expects. The matching mechanics
  live in `block.wrapper-attributes`.

Its non-responsibilities:

- It does **not** render the front-end output for dynamic
  blocks (it returns `null`; see Section E).
- It does **not** participate in editor interactions. The
  editor never calls `save` until a save is triggered.
- It does **not** access `context`. `usesContext` data is
  not available; `save`'s only context is the block's own
  attributes.

The serialization contract optimizes for *stability*.
Determinism, terseness, and parseability matter; visual
flair does not.

### D. The validation seam between them

Block validation is the bridge that ties the two
contracts together at parse time.

The flow:

1. A user opens a post containing a previously saved
   block.
2. The parser reads `post_content`, finds the block's
   delimiter, and extracts:
   - The block's name (from the delimiter).
   - The block's attributes (from the delimiter's JSON
     payload + the parsing rules in
     `block.json-attributes-core`).
   - The block's actual saved HTML (between the
     delimiters).
3. The block type's `save` is invoked with those
   attributes. Its output is rendered to a string.
4. **The parser compares the freshly rendered `save`
   output against the actual saved HTML.** If they
   match (under WordPress's equivalence rules — whitespace
   normalization, attribute ordering, etc.), the block
   loads cleanly.
5. If they do not match, the block is marked invalid.
   Either:
   - A registered `deprecation` entry produces the
     historical HTML, in which case the block migrates
     forward.
   - Otherwise, the user sees the "This block contains
     unexpected or invalid content" warning and is
     offered a recovery path.

Two important consequences:

- `save` is invoked at **load time**, not just save time.
  The same function runs on the server's last
  serialization *and* on every subsequent load that
  re-parses the block. Determinism matters because
  `save(load_time_attributes)` must produce
  `original_save_output` exactly.
- The validation seam is exactly where the *role
  separation* between `edit` and `save` becomes visible.
  `edit` can change freely between WordPress versions;
  the editor mounts whatever `edit` produces with no
  validation. `save` cannot change without coordination
  via `deprecation` — its output is part of the
  historical record stored in posts.

This is also why `edit` and `save` cannot be merged into
one function. The two contracts have different change
costs: `edit` changes are cheap (no historical record),
`save` changes require a migration path (every existing
post is part of the cost).

### E. Dynamic blocks — when the serialization contract is delegated

For dynamic blocks, `save` returns `null`:

```js
registerBlockType( 'myplugin/dynamic', {
    edit: Edit,
    save: () => null,
} );
```

The serialization contract is not removed by this — it
is *delegated*. The block's serialized HTML becomes a
self-closing delimiter:

```
<!-- wp:myplugin/dynamic {"someAttr":"value"} /-->
```

There is no inner HTML to validate against, so the
validation seam from Section D collapses: the parser
reads attributes from the delimiter, recognizes the
block, and stops there. The front-end render is produced
fresh on every page load by `render_callback` /
`render.php`, which receives the attributes and returns
HTML that is *never validated* against any prior
serialization.

The role separation persists, just with different
distribution:

- `edit` still owns the interaction contract.
- The serialization contract is reduced to "encode the
  attributes in the delimiter," which `save: () => null`
  satisfies trivially.
- A new contract — the **runtime rendering contract** —
  is taken on by `render_callback`. This is documented
  in `block.dynamic-rendering`.

For static blocks, `save`'s output is *both* the
serialized form and the front-end form. For dynamic
blocks, the two are split: the delimiter is the
serialization, the `render_callback` output is the
front-end form. The role separation between *interaction*
and *persistence* remains; a third role (*runtime
rendering*) appears alongside.

### F. Build / metadata relation

The two functions reach the runtime through the build
pipeline (covered in `build-tooling.wp-scripts` and
`build-tooling.block-json-build-pipeline`). The relevant
points for this chunk:

- Both functions live in the same `editorScript` source
  file (or get pulled into it via imports).
- The build does not split or re-arrange them; both
  arrive at the editor runtime as part of the registered
  block type's options object.
- The Block API does not require `save` to live in the
  same file as `edit`; it requires only that both be
  passed to `registerBlockType` for the same block name.

The build pipeline is *invisible* to the role separation.
The two contracts exist at the source level, are
preserved through the build, and are interpreted at the
editor runtime. The build does not know or care that
`save` carries different historical-coupling semantics
than `edit`.

## WHY

### Why two functions instead of one

The shortest answer: the editing experience and the
persisted artifact have different design goals.

The editing experience wants to be rich — controls,
previews, drag handles, contextual UI. None of that
should appear in `post_content`. If a single function
produced both, every richness in editor UI would either
leak into the saved markup (cluttering it) or require
conditional logic (`if rendering for editor: do X; if
rendering for save: do Y`) that is harder to reason
about than two functions.

Two functions also let the two contracts evolve at
different rates. `edit` evolves at the pace of UI
ergonomics; `save` evolves only when the persisted
shape needs to change (which triggers a deprecation).
Coupling them would force one rate to dominate.

### Why determinism matters for `save`

Block validation, the ability to re-parse posts years
later, and the ability to round-trip blocks through
different WordPress installs all depend on `save` being
a pure function of its attributes. Any source of
variation — random IDs, current time, server locale,
user identity — breaks at least one of these promises.

The places `save` *needs* dynamic data are exactly the
places to use a dynamic block: `save` returns `null`,
the delimiter encodes attributes, and `render_callback`
is the place where dynamic data is allowed to influence
output.

### Why `context` exists in `edit` but not `save`

Block context is a value passed from an ancestor block
to a descendant at *editor render time*. It exists to
let, for example, a child block read its parent's
"alignment" attribute without that child having to
declare its own duplicate attribute.

`save` cannot use it because the saved HTML must be
self-contained: the child's serialization cannot depend
on the parent's runtime state, because the parent's
state is not part of the child's `post_content`. If the
child needs the parent's value at render time, the
parent is dynamic and `render_callback` performs that
lookup; if the child needs it at save time, the child
must declare its own attribute and copy the value.

The shape of `context`'s availability — `edit` only —
follows directly from the role separation: interaction
can read ambient state; serialization cannot.

## WHEN NOT

Skip the role-separation framing if:

- You are debugging a specific component used inside
  `edit` or `save` — go to `block.edit-save-components`,
  not here.
- You are deciding which `attributes` shape to pick —
  go to `block.json-attributes-core`, with this chunk
  as background.
- You are reading about the post-content delimiter
  format itself — go to `block.markup-representation`,
  with this chunk as background.

This chunk is the **conceptual layer** that makes the
others coherent. It is not the place to find specific
APIs.

## COUNTER-PATTERNS

### Anti-pattern 1 — Putting interaction logic in `save`

```js
save: ( { attributes, isSelected } ) => {
    return isSelected
        ? <div className="selected">…</div>
        : <div>…</div>;
};
```

`save` does not receive `isSelected`, but the deeper
mistake is conceptual: even if some prop signaled
selection, branching `save` output on editor-only state
would mean the serialized HTML differs based on what
was selected when the post was saved. Validation breaks
the next time the post is loaded. Selection styling
belongs in `edit`'s output and the editor's own
stylesheet.

### Anti-pattern 2 — Putting serialization shape in `edit`

```js
edit: ( { attributes } ) => {
    // Trying to render exactly what save will produce, in editor.
    return <div>{ attributes.content }</div>;
};
```

This works mechanically but loses the editing
experience. `edit` is allowed (and encouraged) to
produce a richer DOM than `save` — inspector controls,
toolbars, contextual hints. Constraining it to mirror
`save` exactly forfeits the entire reason `edit` exists
as a separate function.

### Anti-pattern 3 — Non-deterministic `save`

```js
save: ( { attributes } ) => {
    const id = `block-${ Math.random() }`;  // changes every render
    return <div id={ id }>…</div>;
};
```

Block validation will fail on the next load because
freshly rendered `save` output won't match the saved
HTML. If a stable ID is needed, derive it from
attributes (and store it as one).

### Anti-pattern 4 — Mutating attributes inside `save`

```js
save: ( { attributes, setAttributes } ) => {
    setAttributes( { foo: 'bar' } );  // setAttributes is undefined here
    return <div>…</div>;
};
```

`save` does not receive `setAttributes`. Even if it did,
mutation would break the contract: `save`'s job is to
encode current attributes, not to change them.
Attribute changes happen in `edit`, in response to user
interaction.

### Anti-pattern 5 — Skipping `useBlockProps.save()` in `save`

```js
save: ( { attributes } ) => {
    return <div className="my-block">…</div>;
};
```

Without `useBlockProps.save()`, the wrapper does not
emit the auto-generated classes (block name class,
align class, custom class name, color/spacing classes
from supports). The serialized HTML will diverge from
what the editor's `useBlockProps()` produced, and
validation will fail. Both halves of the wrapper-props
helper exist precisely so that the two contracts can
agree on wrapper shape.

## OPERATIONAL NOTES

The contract pair's interpretive shape, in proportional
v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the central fit
  in a refined form. `edit` *declares* an interactive
  representation; `save` *commits* a serialized
  representation. The two are not the same exposure of
  the same declaration — they are two different
  exposures, each with its own commitment shape, both
  consuming the shared attribute schema as their
  declaration substrate. Naming Law 1 here is genuinely
  clarifying because the *bifurcation of exposure* —
  one declaration, two committed forms — is exactly
  what makes this contract pair coherent.
- **Law 6 (Compiler ↔ Runtime Split)** appears in a
  modulated form. The runtime split here is not
  build-vs-runtime but *editor-runtime vs
  serialized-content-as-runtime-input*. `edit` runs in
  one; `save` produces an artifact that another runtime
  (the front-end render path) consumes. The split is
  smaller than `wp-scripts`'s build/runtime split and
  bigger than zero. Worth one section reference, not a
  central frame.
- **Doctrine 5 (Authority Continuity)** appears
  *lightly* — the attribute schema is the continuity
  surface. Both `edit` and `save` honor the same
  declared attribute shape, and that shared honoring is
  what makes the round-trip work. Worth one mention; not
  a section.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** No candidate
  selection. There is one `edit`, one `save`, and one
  registered block name. Omitted.
- **Law 3b (Cross-Runtime Authority Continuity Bridge).**
  The editor → save → front-end journey crosses
  contexts, but it does not preserve runtime *authority*
  across them. Authority is encoded into the artifact at
  save time and read out fresh at front-end time; no
  active state survives the boundary. Adjacent shape,
  different mechanism — the same non-fit pattern as the
  block.json build pipeline. Omitted to preserve Law 3b's
  meaning where it does apply (interactivity hydration).
- **Doctrine 6 (Authority Mediation).** No access
  mediation. Capability checks for who can edit a post
  happen elsewhere in the stack; the `edit` / `save`
  contract pair is not where they live.
- **Section X archetypes.** A pair of authoring
  functions is not a "civilization." The framing would
  inflate ordinary contract semantics into ontological
  language that obscures rather than clarifies.

A small literacy contribution worth pinning, on the
order of "metadata continuity ≠ runtime continuity":

> *Authoring interaction ≠ content persistence.* A
> function that produces an interactive editor surface
> and a function that produces a serialized stored form
> are not "the editor side and the front-end side of
> one component." They are two contracts with different
> audiences (human vs parser), different lifetimes
> (session vs database), and different change costs
> (free vs deprecation-required).

This is useful for future block-authoring chunks that
need to discuss the asymmetry without reaching for it as
a one-off observation each time.

## CHECKLIST

When designing a block's `edit` and `save`:

- [ ] Decide first whether the block is static or
      dynamic. Dynamic = `save: () => null` + a PHP
      `render_callback` (see `block.dynamic-rendering`).
- [ ] Use `useBlockProps()` in `edit` and
      `useBlockProps.save()` in `save`. Both halves are
      needed for the wrapper to round-trip.
- [ ] Make `save` a pure function of attributes. No
      timestamps, randoms, locale-formatted dates, or
      user-specific values.
- [ ] Allow `edit` to be rich. Inspector controls,
      toolbars, previews, hints — none of that needs to
      mirror `save`.
- [ ] When `save`'s shape needs to change, ship a
      `deprecation` entry for the old shape (see
      `block.deprecation`). Do not edit `save` in place
      without one.
- [ ] If the block uses `usesContext`, expect it in
      `edit` only. Plan attribute structure so any
      context value `save` would need is captured into
      the block's own attributes.
- [ ] If validation is failing, suspect non-determinism
      first, wrapper-props mismatch second, attribute
      drift third.

## REFERENCES

- Edit and Save reference. Documents both function
  signatures, prop shapes, the validation contract, and
  the dynamic-block delegation.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/
- Block wrapper handbook. Distinguishes editor markup,
  save markup, and dynamic render markup.
  https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-wrapper/
- Block deprecation handbook. The migration mechanism
  that handles legitimate `save` shape changes over
  time.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/
- Make WordPress Core — block validation changes (WP
  5.6 baseline). Documents the equivalence rules used
  when comparing freshly rendered `save` output against
  saved HTML.
  https://make.wordpress.org/core/2020/11/18/block-validation-changes-in-wordpress-5-6/

Cross-context:

- `block.edit-save-components` — what to use *inside*
  `edit` and `save` (RichText, InnerBlocks, useBlockProps,
  the components and helpers). This chunk is the
  conceptual layer; that one is the practical layer.
- `block.json-attributes-core` — the schema that both
  functions read from. The shape of attribute storage
  affects what each function has to do.
- `block.markup-representation` — the IR form `save`
  produces. The block delimiter format and the
  HTML-between-delimiters convention.
- `block.deprecation` — the handler for `save` shape
  changes that would otherwise break validation.
- `block.dynamic-rendering` — the pathway when `save`
  delegates to `render_callback`.
- `build-tooling.wp-scripts` and
  `build-tooling.block-json-build-pipeline` — how these
  two source functions reach the runtime as registered
  artifacts.
