---
rule_id: block.save-validation-and-equivalence
domain: block-authoring
topic: equivalence-governance
field_cluster: parse-time-validation-substrate
wp_min: "5.0"
wp_recommended: "6.0+"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#validation
    section: "Edit & Save — block validation overview"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2020/11/18/block-validation-changes-in-wordpress-5-6/
    section: "Make Core — WP 5.6 validation changes (equivalence rules baseline)"
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/blocks/src/api/validation/index.js
    section: "@wordpress/blocks validation source — token-level equivalence implementation"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/
    section: "Deprecation — invoked when validation fails"
    captured: 2026-05-10
related:
  - block.deprecation                              # the historical-fallback chunk this chunk pairs with
  - block.edit-and-save-contracts                  # Phase 8.29 chunk; Section D surfaced this as a seam, this chunk deepens it
  - block.markup-representation                    # the IR/grammar that validation tokenizes
  - block.json-attributes-core                     # attribute schema; attributes are the trusted truth
  - block.edit-save-components                     # useBlockProps.save() must match useBlockProps() to validate
---

# RULE — block save validation — semantic equivalence between stored content and fresh `save()` output

## WHEN

You are reasoning about *whether* a block in stored
post content is considered valid when the editor
re-loads it, *what counts as equivalent* (vs
strictly identical), and *what happens* when
validation fails. Use this knowledge when:

- Diagnosing the "This block contains unexpected
  or invalid content" warning that appears in the
  editor for previously-saved blocks.
- Understanding why two slightly-different HTML
  strings can both be considered valid for the
  same attributes (the equivalence rules in
  Section B explain).
- Reading core's `isValidBlockContent` /
  `validateBlock` source to follow the comparison
  algorithm.
- Choosing whether to ship a `deprecation` entry
  (when `save()` shape changes) or accept the
  validation cost (when the change is
  equivalence-preserving).
- Authoring blocks with attribute formats that
  serialize differently across runs (rare; usually
  a sign of `save()` non-determinism — see
  Anti-pattern 1).

This chunk does **not** cover:

- The deprecation fallback flow that fires *when
  validation fails* — covered in
  `block.deprecation`. This chunk is the
  *equivalence enforcement* half of the pair;
  that chunk is the *historical compatibility*
  half.
- The `edit` / `save` contract framework — covered
  in `block.edit-and-save-contracts`. This chunk
  zooms into one section of that chunk (the
  validation seam).
- The block delimiter format / IR grammar that
  validation tokenizes against — covered in
  `block.markup-representation`.
- The recovery UI in detail. Section D
  documents the user-facing options at a level
  enough to understand the decision tree.

The principle this chunk operates under: **Block
validation is not bit-equal-string comparison. It
is equivalence under documented normalization rules
— whitespace, attribute order, void-element form,
boolean-attribute form. Two HTML strings that look
different to the human eye can both be valid
serializations of the same attribute set. The
mechanism is *equivalence governance*, not *strict
equality enforcement*.**

## SHAPE

### A. The validation question — what gets compared

When the editor loads a post with a saved block,
the validation step asks one question:

```
For this block instance:
   stored_html  = the content between this block's delimiters
                  (extracted from post_content)
   fresh_html   = save( current_attributes ) re-rendered to string
                  (from the registered block type's save function)

Is fresh_html EQUIVALENT to stored_html
under WordPress's documented equivalence rules?
```

Not "are they identical strings." *Equivalent*
under specific rules (Section B).

The trusted truth is the **block's attributes** —
the JSON object encoded in the delimiter comment
(`<!-- wp:my/block {"foo":"bar"} -->`). The
attributes feed back into `save()` to regenerate
the fresh form; the regenerated fresh form is then
compared against the stored body.

The mental model:

```
attributes (trusted) ──┐
                       ├──► fresh save() output ──┐
                                                  │
                                                  ▼
                                          equivalence check
                                                  ▲
                                                  │
stored HTML body (from post_content) ─────────────┘
```

Validation passes if both arms produce equivalent
serializations *for the same attributes*.

### B. The equivalence rules

WordPress's validation tokenizes both strings into
a comparable form before comparing. Differences
the tokenizer normalizes away — and therefore
*do not cause invalidation*:

| Difference                              | Treatment                                                 |
| --------------------------------------- | --------------------------------------------------------- |
| Whitespace inside text nodes            | Collapsed and trimmed; `"hi  there"` ≡ `"hi there"`        |
| Whitespace between elements             | Ignored; `<a><b>` ≡ `<a> <b>` ≡ `<a>\n  <b>`              |
| Attribute order                         | Sorted; `<a x="1" y="2">` ≡ `<a y="2" x="1">`              |
| Self-closing void elements              | Normalized; `<br>` ≡ `<br/>` ≡ `<br />`                    |
| Boolean attributes                      | Presence-only; `disabled` ≡ `disabled=""` ≡ `disabled="disabled"` |
| Default-valued attributes               | Omittable when equal to default; `<input type="text">` ≡ `<input>` |
| HTML entity casing                      | Normalized; `&amp;` ≡ `&AMP;`                              |
| Equivalent Unicode/ASCII representations | Treated equally where standards permit                     |

Differences that **do** cause invalidation:

- Different element tag names.
- Different visible text content (after whitespace
  normalization).
- Different attribute *values* (after order /
  default normalization).
- Different element nesting structure.
- Missing or extra elements.
- Different number of comment nodes (in some
  cases).

The intent is to tolerate the kinds of
representation drift that arise from:

- HTML parser normalization across browsers.
- React's HTML serialization vs hand-written HTML.
- Whitespace introduced by code editors.
- Attribute reordering by formatters.

…while catching genuine semantic divergence (the
content actually differs).

### C. The 4-outcome decision tree

When the editor loads a block, validation produces
one of four outcomes — and what happens next
depends on which:

```
                  validate( stored, fresh )
                          │
            ┌─────────────┼─────────────┐
            ▼             ▼             ▼
        Outcome 1     Outcome 2     Outcome 3 / 4
        Valid         Invalid +     Invalid +
                      deprecation   no recovery
                      matched       found
            │             │             │
            ▼             ▼             ▼
   Use stored content  Run migrate(),  Mark block invalid;
   as-is; mount edit   then mount      surface recovery
   with current        edit with       UI to operator.
   attributes.         migrated        (Outcome 3 = isEligible
                       attributes.     forced; Outcome 4 = no
                                       deprecation matched.)
```

The four outcomes in detail:

- **Outcome 1 — Valid.** Fresh `save(attributes)`
  is equivalent to the stored body. Block mounts
  cleanly with current `edit` and current
  attributes.
- **Outcome 2 — Invalid, deprecation matched
  through `save`.** Current `save` output
  doesn't equal stored, but a deprecation
  entry's `save` does. The deprecation's
  `migrate()` (if present) updates the
  attributes; the block then mounts under
  current `edit`.
- **Outcome 3 — Invalid, deprecation matched
  through `isEligible`.** A deprecation entry
  declares `isEligible: () => true` (or some
  conditional that returns true). This forces
  the deprecation path even when the current
  `save` would have validated. Useful for
  attribute-only migrations that don't change
  HTML.
- **Outcome 4 — Invalid, no deprecation
  matched.** None of the deprecations produced
  matching `save` output, and none had
  `isEligible` returning true. The editor
  surfaces the recovery UI:
  - **Attempt to recover** — re-render with
    current `save` (loses any customizations
    that lived only in the stored HTML).
  - **Convert to HTML block** — preserve the
    stored HTML verbatim by switching block
    type.
  - **Convert to classic block** — legacy
    fallback for very old content.

The validation step is the *gatekeeper* between
the four outcomes; the deprecation chunk
(`block.deprecation`) documents what happens
inside Outcomes 2 and 3.

### D. The recovery UI surface

When Outcome 4 fires, the user sees:

```
┌──────────────────────────────────────────────────┐
│  ⚠ This block contains unexpected or invalid    │
│    content.                                      │
│                                                  │
│    [ Attempt Block Recovery ] [ Convert to HTML ]│
│                                                  │
└──────────────────────────────────────────────────┘
```

Three properties to pin:

- **The block is not deleted.** Its stored HTML
  is preserved verbatim until the operator
  picks a recovery action.
- **Recovery is operator-driven.** WordPress
  doesn't auto-recover; an invalid block sits
  invalid until someone clicks one of the
  options (or until a plugin update ships a
  matching deprecation).
- **The choice has consequences.** "Attempt
  Block Recovery" re-runs `edit` with the
  block's attributes — but if some content lived
  in the HTML beyond what attributes capture
  (e.g., user-edited HTML the block didn't
  parse back into attributes), that content is
  lost. "Convert to HTML" preserves the bytes but
  loses the block's edit affordances.

For block authors, the practical implication: the
validation surface is a user-facing UI moment.
Frequent invalidations after a plugin update
produce a poor user experience even when
recovery technically works. Ship deprecation
entries when `save()` changes; minimize the
surface area where Outcome 4 can fire.

### E. Diagnostic surface

Validation failures emit console warnings in
development builds (via `@wordpress/blocks`'s
`logger`):

```
Block validation: Block validation failed for `myplugin/myblock`
  ({ name: "myplugin/myblock", … }).

  Content generated by `save` function:
  <div class="wp-block-myplugin-myblock">…</div>

  Content retrieved from post body:
  <div class="wp-block-myplugin-myblock" id="ext-id">…</div>
```

The warning includes both the fresh and the stored
HTML side-by-side, with the difference visible.
This is the primary diagnostic for "why did this
block fail validation" investigations.

In production builds, the warning is suppressed
to avoid console noise on user-facing logs. The
recovery UI is the user-visible signal in
production.

### F. Relationship to deprecation

The validation chunk and the deprecation chunk
form a tight pair:

| Chunk             | Owns                                                        |
| ----------------- | ----------------------------------------------------------- |
| **This chunk**    | The equivalence comparison; whether stored ≡ fresh          |
| **`block.deprecation`** | The historical-fallback ladder when comparison fails        |

The flow:

```
validation runs (this chunk's territory)
   │
   ▼
if equivalent: Outcome 1 — done
   │
   ▼
if not: deprecation matching runs (deprecation chunk's territory)
   │
   ▼
deprecations may produce migrated attributes
   │
   ▼
Outcome 2/3 — done; or Outcome 4 — recovery UI
```

The chunks describe two halves of one mechanism.
This chunk owns "what equivalence means and how
the comparison runs"; the deprecation chunk owns
"what happens when equivalence fails and a
historical alternative exists."

## WHY

### Why equivalence rather than strict equality

Strict-equality validation would reject any HTML
string that differed from the fresh output by a
single byte — including:

- Whitespace introduced by code editors saving
  the post HTML.
- Attribute reordering by tools / formatters.
- Browser HTML normalization (e.g., `<br>` →
  `<br/>` depending on serializer).

These differences are not semantic; they're
representational. Strict equality would surface
recovery UI for blocks that mean exactly what they
meant before, just spelled slightly differently.

The equivalence rules (Section B) draw the line
between "looks different but means the same"
(allowed) and "actually different content"
(invalid). The line is not perfect — there are
edge cases where the rules under- or over-tolerate
— but it captures the dominant cases.

### Why validation runs at *parse* time, not save time

If validation ran at save time, it would catch
problems with the block currently being edited.
That's useful but doesn't address the bigger
problem: the block was already saved, perhaps
months ago, and the *block type definition* has
changed since.

Parse-time validation runs *every time the post is
loaded*. It catches drift between stored content
and current block-type definitions. The cost — a
parsing pass through every block on every load —
is paid for editor reliability.

### Why the trusted truth is attributes, not HTML

The block delimiter encodes attributes as JSON
(`<!-- wp:my/block {"foo":"bar"} -->`). Those
attributes are what `save` will receive when
re-rendered. Treating the attributes as
authoritative means: "the save function is
deterministic from attributes; if its output
differs from stored, something is wrong." Treating
the HTML body as authoritative would mean
attributes can drift from what the HTML expresses
— harder to reason about.

The asymmetry — JSON attrs trusted, HTML body
checked against fresh re-render — gives the
mechanism a single source of truth at parse time.

### Why operator-driven recovery rather than auto-fix

Auto-recovery would either:

- Always rerun `save` and overwrite the stored
  HTML (losing any user customizations the
  attributes don't capture).
- Always preserve the stored HTML (defeating the
  validation entirely).

Neither default fits all cases. Letting the
operator choose surfaces the trade-off explicitly:
"do you want this block's current content
(preserve HTML) or this block type's current
shape (regenerate from save)?"

The cost — operators see the warning UI when
content drifts — is the visible signal that
something needs attention. Auto-fix would hide
the signal.

## WHEN NOT

Skip validation reasoning if:

- You are working with **dynamic blocks**
  (`save: () => null`). Their stored HTML is
  empty (just delimiters); validation doesn't
  apply. The render callback produces output
  fresh on every page load with no equivalence
  check.
- You are working with **classic content**
  (pre-block content, the "Classic" block
  itself). Validation runs only on properly-
  delimited blocks; classic content is treated
  as raw HTML.
- You are debugging a **block-type registration**
  issue rather than a per-instance content
  issue. Registration problems show different
  symptoms (no block at all, wrong icon, etc.)
  than validation failures (block exists but
  shows recovery UI).

## COUNTER-PATTERNS

### Anti-pattern 1 — Non-deterministic `save()`

```js
save: ( { attributes } ) => {
    const id = `block-${ Math.random() }`;  // changes every render
    return <div id={ id }>{ attributes.text }</div>;
};
```

The block invalidates on every load because the
fresh render produces a different `id` than the
stored one. Equivalence rules don't help — the
attribute *value* differs.

`save()` must be a pure function of `attributes`
(and `innerBlocks` if applicable). If a stable
ID is needed, derive it from the attributes
deterministically (e.g., hash of content).

### Anti-pattern 2 — Conditional rendering based on missing attributes

```js
save: ( { attributes } ) => {
    return attributes.advanced
        ? <div data-advanced>{ attributes.content }</div>
        : <div>{ attributes.content }</div>;
};
```

If `attributes.advanced` is `undefined` (rather
than `false`) for some stored content, the
fresh render goes one way and the stored may
have gone another. Use explicit defaults in
attribute schema:

```js
attributes: {
    advanced: { type: 'boolean', default: false },
}
```

…so `attributes.advanced` is always `false` (not
`undefined`) when the user hasn't set it.

### Anti-pattern 3 — Editing `save()` without a deprecation entry

```js
// v1 (already shipped):
save: ( { content } ) => <p>{ content }</p>

// v2 (current):
save: ( { content } ) => <div>{ content }</div>  // tag changed
```

Every existing post with the v1 form invalidates
on next load. Add a deprecation entry capturing
v1:

```js
deprecated: [
    {
        attributes: { content: { type: 'string' } },
        save: ( { content } ) => <p>{ content }</p>,
    },
],
```

The deprecation handles existing v1 content; new
content uses v2 from the start.

### Anti-pattern 4 — Whitespace-sensitive `save()`

```js
save: ( { attributes } ) => {
    return (
        <div>
            { /* this comment introduces whitespace */ }
            { attributes.content }
        </div>
    );
};
```

The whitespace from the JSX comment may serialize
differently than expected. Equivalence rules
should normalize, but edge cases exist. Avoid
JSX comments inside save output; if you need
notes, put them outside the JSX.

### Anti-pattern 5 — Treating Outcome 4's recovery UI as a feature

Some authors treat "we'll just let users click
Recover when needed" as an acceptable UX. It
isn't:

- Operators may not understand the warning.
- Recovery may lose data they didn't realize was
  in the HTML.
- A site with hundreds of invalid blocks (after a
  plugin update without deprecations) becomes
  unusable.

If a `save()` change is unavoidable, ship a
deprecation. The recovery UI is a safety net,
not a deployment plan.

### Anti-pattern 6 — Suppressing console warnings without fixing the underlying issue

```js
// Don't do this just to silence the warning:
const original = console.warn;
console.warn = () => {};
```

Validation warnings are diagnostic signal. If a
block frequently invalidates, fix the cause
(usually non-determinism, schema drift, or a
missing deprecation). Suppressing the warning
hides the symptom; the underlying problem
remains.

## OPERATIONAL NOTES

The validation substrate's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in an *equivalence-checking* form.
  The block's attributes are the *declared*
  truth; the stored HTML body is *one
  exposure* of those attributes; the fresh
  `save()` output is *another exposure* of the
  same attributes. Validation asks whether the
  two exposures are equivalent. Naming Law 1
  here is genuinely clarifying because the
  *gap* between "what the attributes say" and
  "what the stored HTML shows" is exactly the
  surface validation polices. The framing
  *"declared attributes ≠ stored representation,
  unless equivalent"* captures the substrate.
- **Doctrine 5 (Authority Continuity)** is a
  **very strong** fit. The block instance's
  identity persists across representational
  drift: the same attributes can produce
  bit-different but semantically equivalent
  HTML across saves, browsers, parser
  versions. The continuity is *semantic*, not
  *representational*. The mechanism's design
  premise is that this kind of continuity is
  what matters; bit-equality is too brittle.
  The equivalence rules (Section B) operationalize
  the doctrine: *which differences are tolerable
  while preserving identity?*

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Important
  non-fit*, in *clean contrast* to the adjacent
  `block.deprecation` chunk. Validation is a
  binary equivalence check: either the stored
  and fresh forms are equivalent, or they aren't.
  No candidates, no ladder, no first-match-wins.
  The deprecation chunk *does* fit Law 4 (its
  matching algorithm is candidate arbitration);
  validation is its trigger, not its mechanism.
  Pinning this distinction matters because the
  two chunks pair tightly: validation *answers
  yes/no*, deprecation *walks the ladder when
  the answer is no*. Conflating them would
  lose the structural difference between
  comparison and selection.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** All validation runs in the editor
  JS runtime per parse. No cross-runtime
  authority preservation. Omitted.
- **Doctrine 6 (Authority Mediation).** No
  access mediation. Validation is a comparison
  algorithm, not a permission gate. Omitted.
- **Federation.** No federation in the validation
  mechanism itself. (The deprecation entries are
  per-block, not federated; even if many plugins
  modify many blocks, each block's validation
  runs independently against its own
  deprecations.) Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** No
  build / runtime split. Omitted.
- **Section X archetypes.** A validation
  algorithm is not a "civilization." Same
  framework-omission discipline. Omitted.

Two literacy contributions worth pinning:

> *Bit-exact match ≠ semantic equivalence.* A
> mechanism that compares two representations and
> tolerates documented differences (whitespace,
> attribute order, void-element form) is not the
> same shape as a mechanism that requires byte-
> for-byte identity. The equivalence rules
> *define* the tolerated differences; what falls
> within the rules is "the same"; what falls
> outside is "different." Identity and
> representation are not the same: identity is
> what the rules preserve; representation is
> what the rules normalize away.

This contribution extends the existence-vs-
operation toolkit with a *representational drift*
form: where the earlier toolkit entries
distinguished states (declared / activated /
executed), this one names the shape when the
question is whether two existing states are
*the same as each other under documented
normalization*. Comparison-with-tolerance, not
strict equality.

> *Validation outcome ≠ block fate.* A failed
> validation is not the end of the block's
> lifecycle. It is the entry point to a 4-outcome
> decision tree (valid / deprecation-recovered /
> isEligible-recovered / user-recovery). The
> validation substrate decides yes/no; the wider
> machinery (deprecation, migration, recovery
> UI) decides what happens with the no.

This contribution clarifies the boundary between
this chunk and the deprecation chunk: validation
is the *trigger condition*, not the *response*.
The pair forms one mechanism with two distinct
sub-mechanisms — comparison and recovery — each
owning its own chunk.

A small additional observation, doctrinal:

> *Doctrine 5 (Authority Continuity) here applies
> at the **representation-drift** level — semantic
> identity persisting through documented
> normalization. This is structurally different
> from Doctrine 5's other applications:*
>
> - *Per-locale grammar continuity (i18n
>   plurals)* — same string identity across
>   locales.
> - *Token name continuity (style-engine
>   layering)* — same path identity across
>   layer merges.
> - *Hook name continuity (hooks)* — same
>   namespace identity across registrations.
> - *Representational equivalence (this chunk)*
>   — same semantic identity across HTML
>   normalization variants.
>
> *All four are continuity, but the substrate
> over which continuity holds differs in each.
> Recognizing the variant family helps map
> Doctrine 5's reach across the KB.*

This doesn't introduce a new toolkit; it surfaces
the breadth of Doctrine 5's existing reach.

## CHECKLIST

When reasoning about block validation:

- [ ] Treat `save()` as a pure function of
      attributes. Non-determinism breaks
      validation reliably.
- [ ] Set explicit defaults on optional
      boolean attributes; `undefined` vs `false`
      can produce different `save()` output.
- [ ] When changing `save()`, ship a
      deprecation entry capturing the old
      shape. Don't rely on the recovery UI as
      a deployment plan.
- [ ] If validation fails unexpectedly, check
      the dev-console diff side-by-side — the
      mismatch usually points at a single
      attribute or whitespace difference.
- [ ] Use `useBlockProps()` in `edit` and
      `useBlockProps.save()` in `save` so the
      wrapper's auto-generated classes /
      attributes match between the two
      contexts.
- [ ] Don't suppress validation warnings.
      They're diagnostic signal.
- [ ] When validation passes but the
      attribute schema is changing, consider
      `isEligible: () => true` on a
      deprecation entry to force migration.

## REFERENCES

- Edit and Save reference — validation section.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#validation
- Make WordPress Core — block validation changes
  in WP 5.6. Documents the equivalence rules
  baseline.
  https://make.wordpress.org/core/2020/11/18/block-validation-changes-in-wordpress-5-6/
- `@wordpress/blocks` validation source on
  GitHub. The token-level comparison
  implementation; canonical reference for
  edge-case equivalence behavior.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/blocks/src/api/validation/index.js
- Block deprecation reference. The historical
  fallback chunk that handles validation
  failures.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/

Cross-context:

- `block.deprecation` — the paired chunk that
  owns the historical-fallback ladder when
  validation fails. Together they form the
  *equivalence + fallback* pair: this chunk
  owns the comparison; that chunk owns the
  recovery ladder.
- `block.edit-and-save-contracts` — Phase 8.29
  chunk that surfaced this validation step as
  a "seam" between the two contracts. This
  chunk deepens that section.
- `block.markup-representation` — the IR /
  grammar that validation tokenizes. The
  delimiter format and the HTML-between-
  delimiters convention.
- `block.json-attributes-core` — the attribute
  schema. Attributes are the trusted truth;
  validation polices the gap between what the
  attributes say and what the HTML body shows.
- `block.edit-save-components` — `useBlockProps`
  / `useBlockProps.save()` — using both halves
  is required for wrapper-prop equivalence
  between edit and save.
