# ActivityStreams Object Contract

**Status:** working decision, 2026-10-18. This fixes the contract that the
frontend application, the block presenters and any future consumer share: one
JSON-LD Object assembled from a registered source reader and the registered type
hierarchy. It records value priority, the payload fallback, extension
preservation, provenance and disclosure, so the next implementation cannot
settle into either re-sending a received payload or reassembling an incomplete
set of columns.

## 1. What this corrects

Object Projections already resolves local and remote sources through one
function. `axismundi_op_object_view_model()` walks registered adapters by
priority, and `remote-cache` is one of them at priority 90 beside `local-note`
at 10 and `local-post-object` at 50.

**One dispatcher is not one contract.** The adapters are keyed by source and
locality, while the AS2 type is something each adapter *produces* — a local post
comes back with `type` of `Article` among 26 keys. Two adapters can therefore
both emit an `Article` with nothing shared between them, which is the same shape
as the defect measured earlier the same day: this plugin's Article emitted `url`
then `mediaType` while its own standalone Image emitted the reverse, two paths
having each reinvented the Object part of a document. That was closed at the
renderer boundary by declaring one member order. The view model layer has the
same exposure and no equivalent contract.

## 2. The pipeline

```txt
Object id/URI
  -> registered source reader            (locality lives here, and only here)
  -> structured facts + relations + preserved extensions
  -> JSON-LD assembly by type hierarchy  (shared by local and remote)
  -> React components / block presenters
```

A local Object's source is a `WP_Post` with its meta, taxonomy and block
content. A remote Object's source is a cached document in `wp_ax_remote_objects`
with its side tables. Those cannot share transform code, so the source reader is
where the difference belongs — including the fact that they answer to different
ledgers. Everything above the reader is shared.

## 3. Assembly follows the registered type hierarchy

The hierarchy is already registered data rather than something to invent. The
vocabulary snapshot carries 20 types, each with `parents`, `category`,
`declares` and `excludes`, and `effective_properties()` computes
`union(parents) + declares - excludes` for real.

```txt
Note      category object    parents [Object]              declares []
Article   category object    parents [Object]
Document  category object    parents [Object]
Question  category activity  parents [IntransitiveActivity] declares [oneOf, anyOf, closed]
```

Two consequences that a naive type list gets wrong.

**`Question` reaches Object through the Activity branch.** Its ancestry is
`Question -> IntransitiveActivity -> Activity -> Object`; `category: activity`
is a dispatch classification, not an assertion that Activity lies outside
Object. It may inherit the base Object presentation while retaining its poll
facts. It must not be registered as a direct sibling subtype of Note.

**`Quote` is not a type at all.** The storage boundaries record quote as an
AS2-native property contract, and `wp_ax_object_relations` holds the
Object-to-Object quote edges with their evidence, consent and authorization. So
there is no `Quote` assembly contract: there is a `Note` whose assembly renders
a quote edge. Any plan that registers `Quote` as a type contradicts a decision
already taken.

### 3.1 Quote belongs to Object, and OP already owns half of it

FEP-044f defines `quote` as a property, `https://w3id.org/fep/044f#quote` with
`@type: @id`, carried by the quoting object. Nothing restricts it to a `Note`,
so an `Article` quoting is unremarkable, and the property belongs to the shared
Object assembly rather than to any one product.

What the FEP does introduce as types is the approval machinery, not the quote:
`QuoteAuthorization` (`https://w3id.org/fep/044f#QuoteAuthorization`) as a
dereferenceable stamp carrying `interactingObject`, `interactionTarget` and
`attributedTo`, the `QuoteRequest` activity
(`https://w3id.org/fep/044f#QuoteRequest`) whose `object` is the quoted object
and whose `instrument` is the quote post, and the
`quoteAuthorization` property that points the quote post at its stamp. The
Activity ledger already indexes `instrument_uri_hash`, and the Bridge's Inbox
audit already pins that a verified `QuoteRequest` composes into one
Activities-owned `Accept` with its authorization, so the request and stamp half
of this FEP is implemented.

The policy half is **also implemented**, and conformantly. That is recorded here
because three successive readings of this area concluded otherwise from the
absence of a grep match rather than from running it, and the corrections are
cheaper to write down than to rediscover.

The policy vocabulary is not FEP-044f's own: it is GoToSocial's
`interactionPolicy` with a `canQuote` sub-policy, where `automaticApproval` and
`manualApproval` take Actors and Collections of Actors under
`https://gotosocial.org/ns#`. `_ax_op_quote_policy` is a sanitized scalar with
three values, and `axismundi_act_quote_automatic_approval()` resolves each one
into the audience the FEP asks for, measured on the running site:

```txt
'anyone'     -> https://www.w3.org/ns/activitystreams#Public
'followers'  -> the Actor's own followers collection URL, supplied by
                representation through the axismundi_act_actor_followers_uri
                filter, which is registered and returns a real URL
'me'         -> the author's own Actor URI
''           -> no interactionPolicy member is emitted at all
```

`'me'` is exactly the form the FEP prescribes for forbidding every quote:
`automaticApproval` SHOULD carry the author's own identifier as its single value,
because an empty array is indistinguishable from a missing property under
JSON-LD canonicalization. And emitting nothing for an unset policy matches the
same observation from the other side. `renderer.php` declares the `gts:` context
terms only when the member is present, so a document without a policy carries no
vocabulary for one.

Those three values are also the interoperable subset: the FEP records that
Mastodon 4.5 restricts authoring to automatic approval with `as:Public`, the
author's followers, or nobody.

**The policy is advisory, and the FEP states a MUST NOT about it:** it SHOULD
provide interface hints such as enabling a Quote button or explaining why an
object cannot be quoted, but it MUST NOT be used to verify whether a quote post
is valid. Verification is the stamp: a recipient MUST treat a third-party quote
as unapproved unless the two authors are the same or a valid, dereferenceable
`quoteAuthorization` exists. So any code path that reads
`_ax_op_quote_policy` to decide whether an inbound quote may be displayed is a
conformance defect, not a policy choice. That is worth checking before the
authoring gap below is closed, because closing the gap makes the policy more
visible, not less.

Measured authoring state, 2026-10-18:

| | policy, who may quote me | target, whom I quote |
|---|---|---|
| core `post` / `Article` | `_ax_op_quote_policy` post meta, registered for `'post'` only | **nothing** |
| `ax_note` / `Note` | `quote_policy` and `quote_policy_authored` in the Note envelope, with the Note plugin's own validator | `quote_target_uri` in the Note envelope |

The read side is already type-agnostic and does not delegate to the Note
plugin: `axismundi_op_quote_target_for_source()` resolves a target from
`wp_ax_object_relations`, `quote_uri` is a view model field, `quote-context.php`
renders it, and `object-relations.php` already accepts the legacy `quoteUrl`
spelling on receipt — one of the four compatibility spellings the FEP lists
beside `quoteUri` and `_misskey_quote`.

So what remains is narrower than the feature looks, and none of it is "move the
Note plugin's code":

1. **An `Article` cannot author a quote target.** A core `post` carries the
   policy and has nowhere to record whom it quotes. This is the real gap: the
   policy says who may quote me, and quoting itself is unavailable.
2. **One validator, not two.** The value set already agrees -- both sides accept
   exactly `anyone`, `followers`, `me` and unset -- so the duplication is the
   validator and its error codes, not the vocabulary. Domains keep storing their
   authored values, because the Note envelope is domain-owned by an existing
   decision.
3. **Whether a type carries a quote is declared by its registration**, per
   section 7. The `'post'` gate is generalized by declaration, never by adding
   another post type to a list. A core `post` gains its target field through that
   declaration, writing the relation edge that is already the authority rather
   than a parallel column.
4. **`manualApproval` is not expressible.** The three values are
   automatic-approval only. That is a feature gap rather than a conformance one,
   since the FEP permits advertising `automaticApproval` alone -- but the
   `QuoteRequest` and `Accept` path already implements manual review
   mechanically, so the policy currently cannot advertise something this project
   can already do.

## 4. Value priority

Four tiers, in this order, and the third is **not** the fourth.

| Tier | Source | Rule |
|---|---|---|
| 1 | structured column | the authoritative projection of that member |
| 2 | side table | relations, mentions, hashtags, thread edges |
| 3 | **declared payload fallback** | a named member the vocabulary declares but storage does not structure yet; read from the preserved payload, per member, by name |
| 4 | **preserved extension** | members we model nowhere, carried verbatim and never interpreted |

Tier 3 is a temporary, enumerated list of known gaps. Tier 4 is a permanent,
open mechanism for everything we have not modelled. Conflating them turns a
structuring debt into a silent interpretation of arbitrary remote input, and
turns a closed list into an excuse not to finish structuring.

The measured tier 3 case is `oneOf`. The vocabulary already declares `oneOf`,
`anyOf` and `closed` as Question properties, while `wp_ax_remote_objects`
structures none of them: across 40 sampled cached remote Objects, `oneOf` and
`votersCount` appear in all 8 Questions and exist **only** inside
`payload_json`. So the gap is not between the specification and this project —
it is between this project's own vocabulary declaration and its own storage.
Without the fallback, a remote poll assembles with no options.

Every tier 3 entry carries the reason it exists and is removed when the
structuring lands. The list is the structuring backlog, in machine-readable
form.

## 5. Extension preservation, and its one safety rule

Activity Streams 2.0 Core requires a conforming consumer to ignore properties
that are not applicable to its application domain. **Ignore means do not act
on, not do not carry.** A fediverse browser meets unmodelled members constantly
— `_misskey_reaction` was measured carrying the entire meaning of an inbound
Like earlier the same day — so an API that drops them leaves the interface
unable to show anything at all for a type nobody has modelled yet.

Preserved extensions are therefore carried, and they are carried **as data that
has not been normalized**. Structured members pass through receipt-time
normalization; the extension tail does not. It must be exposed under a shape
that a consumer cannot mistake for trusted content, must never be rendered as
HTML, and must never be promoted into a structured member by a renderer. The
tail is the one place in this pipeline where unsanitized remote input would
otherwise reach a view.

## 6. Provenance: reassembly is safe when it is labelled

Reassembling a remote Object is not in itself a risk. Publishing it without
saying what it is would be. Every assembled remote Object declares:

- that it is a remote observation rather than something this site authored;
- the source it was observed from;
- when it was observed, and when the origin last said it changed.

`wp_ax_remote_objects` already carries `fetched_at`, `last_success_at`,
`remote_updated_at`, `payload_hash`, `etag` and `last_modified`, so the facts
exist; the contract is that the API states them rather than omitting them. With
them stated, this site claims neither authorship nor currency, which is what
makes a dynamic reassembly honest rather than a forgery.

The separate rule still holds: an assembled document is this site's document.
It is correct to serve to the frontend and incorrect to re-emit to a peer as the
remote Object's own representation, for the same reason recorded for Update
history in the Activities plugin's `LIFECYCLE.md` section 4. The frontend API
and the federation representation are different exits.

## 7. Registration is not bound to post type creation

A product declares its **type, its source reader and its projection together**,
through the existing registration action. Registration is deliberately not
coupled to `register_post_type()`:

- remote Objects have no post type at all;
- product types exist that are not posts;
- and a geodata or event product may need `Place` rendered by the frontend
  application before any block exists for it.

What the single declaration does buy is the end of one drift class: today a
product registers a post type and separately registers a local source profile,
and a disagreement between them surfaces only as the
`ax_op_unknown_profile_type` error path. One declaration makes the disagreement
impossible rather than merely detected.

## 8. Presenters, and why the block one is optional

The JSON-LD Object is the centre. Presenters sit thinly on top of it, and they
are **not co-equal consumers**:

- the frontend application consumes JSON-LD over REST, for arbitrary Objects,
  paged, client side;
- a block presenter consumes the same facts inside a PHP render callback, bound
  to one Object per request — which is what
  `axismundi_op_set_current_object_view_model()` and its `_enriched` flag
  already do.

A block presenter may not exist for a given type. The frontend application may
render `Place` from a geodata or event product with no block at all, and may
browse a `followers` collection that the block theme never gives a page. So the
contract may not assume a block consumer, and the existing block view model
becomes a **compatibility adapter over the assembled Object** rather than a
parallel model.

One artifact for both would be the wrong simplification: it leaks pre-escaped
markup toward a client that must re-render, or block markup toward an API. Two
thin presenters over one fact set is not duplication. The check that keeps them
honest is the one that caught the member-order defect: for the same Object, both
presenters must agree on every field they share.

## 9. Disclosure is per requester, here too

The collection model records that a total's disclosure is a map per requester
class rather than one value. The same holds for an Object's members. Assembly
resolves the facts; disclosure decides which of them this requester may see, and
it is decided **in the contract rather than in each presenter**. A presenter
that filtered for itself would leave the frontend application reimplementing a
policy the block theme had already implemented differently, which is the outcome
this document exists to prevent.

## 10. Measurements, 2026-10-18

```txt
view model adapters      3, keyed by source and locality:
                         local-note (10), local-post-object (50),
                         remote-cache (90)
local post view model    26 keys, with type=Article -- the type is produced by
                         the adapter, not the key it was selected by
vocabulary snapshot      20 types, each with parents / category / declares /
                         excludes; Note parents [Object], Question category
                         activity with parents [IntransitiveActivity] and
                         declares [oneOf, anyOf, closed]; Quote absent
remote object storage    structured columns for object_type, attributed_to_uri,
                         in_reply_to_uri, human_url, name, summary, content,
                         content_language, media_type, is_sensitive,
                         published_at, remote_updated_at, beside payload_json
                         and payload_hash
remote payload coverage  40 sampled cached Objects (Note 32, Question 8):
                         6 distinct members column-carried, 2 payload-only --
                         oneOf and votersCount, in 8 of 8 Questions
side tables              ax_object_mentions 331, ax_object_relations 255,
                         ax_thread_edges 1423, ax_remote_object_hashtags 0
```

The remote sample is development fixture data and thin: only eight distinct
top-level members appear across it, while real federated Objects carry `tag`,
`attachment`, `to`, `cc`, `url`, `published`, `sensitive`, `summary`, `replies`,
`likes` and more. The payload fallback and the extension tail therefore matter
more than this sample demonstrates, and the sample could not confirm whether the
populated side tables are fed from remote payloads or from local activity.
Measure that before building the remote source reader.

## 11. Open

- Whether the assembled Object is cached, and if so keyed on what. The source
  reader is dynamic by design, so a cache here is an optimisation with an
  invalidation contract, not a default.
- The tier 3 list's initial contents beyond `oneOf`, `anyOf`, `closed` and
  `votersCount`, which needs the side-table provenance measurement above.
- How `Question` assembles while sitting on the Activity branch of the type
  hierarchy, given that a reader experiences it as a poll Object.
- Whether `Place` assembly belongs to the geodata product's reader or to a core
  profile, which the first frontend surface will decide.
- Registering the quote terms. The vocabulary snapshot holds no quote property
  among its 63, and `Object` declares none, which the closed-subset rule makes
  correct until they are needed. When they are, the sources are FEP-044f for
  `quote`, `quoteAuthorization`, `QuoteAuthorization` and `QuoteRequest`, and
  GoToSocial's namespace for `interactionPolicy`, `canQuote`,
  `automaticApproval` and `manualApproval`. Two of them are not one
  specification, and neither is the ActivityStreams Vocabulary — recording a
  Vocabulary anchor for a term it does not define is the error corrected for
  `orderedItems`.
- Whether any current code path uses `_ax_op_quote_policy` to decide the
  validity or display of an inbound quote. FEP-044f makes that a MUST NOT, so
  this is a conformance audit rather than a design question.

## Source-reader split, 2026-10-19

The first implementation separates source acquisition from view-fact assembly.
`axismundi_op_register_object_source_reader()` registers `supports` and `read`
callbacks; the former view-model registration API remains a compatibility
wrapper. `axismundi_op_read_object_source()` returns acquired facts before any
type assembly runs. Existing source extraction and its payload reads are preserved, not
replaced by a new remote reassembly policy in this slice.

`axismundi_op_register_object_assembly()` registers a source-free callback by
vocabulary type. Resolution selects the nearest registered ancestor by breadth
first traversal of `parents`, with parent order breaking ties. The base Object
assembly supplies shared text/media normalization and defaults. Image can
inherit a Document specialization; Question inherits Object through Activity.
Unknown observed extension types retain the previous base Object presentation.
Assembly registrations have an action and read-back API and reject duplicate
types. This is view-fact assembly, not the future JSON-LD read API.

Source-specific URLs, visibility, and current-user editing capabilities are read
before assembly. The compatibility view-model filter still runs afterwards;
it remains an existing extension hook, not a new type assembly surface.
`_enriched` and request-current binding stay with the block presenter.

`audit-object-assembly.php` checks shared local/observed Article assembly,
common fields, defaults, and hierarchy resolution. The read-only
`audit-object-assembly-baseline.php` records raw JSON SHA-256 values captured
before this split for the site's Article and media fixtures, including actual
embedded media descriptors. Its Article attachment array is empty, so that
array hash alone is not evidence for embedded media preservation. These are
live development fixtures, not portable canonical AS2 document hashes.

## Sources

- Activity Streams 2.0 Core, section 9.2.2 on conforming consumers.
- ActivityPub, W3C Recommendation 2018-01-23.
- `activitystreams-collection-model.md` for the collection contract axes and the
  specification-versus-project-choice rule, which applies to every pin here.
- `activitystreams-storage-boundaries.md` for the quote property contract and
  the Question storage decision.
