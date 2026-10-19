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

언어 멤버는 일반 컬럼 우선순위의 명시적 예외다. 원격 `name`/`summary`/
`content` 컬럼은 scalar와 map을 평탄화한 인덱싱 값이므로 원본의 멤버 존재
여부나 전체 언어 집합의 권위가 아니다. 읽기 API는 이 여섯 scalar/map 멤버를
이름으로 선언된 payload fallback에서 읽고 정제한다. 원본 scalar와 map이 서로
달라도 둘을 합치거나 덮어쓰지 않는다. 나머지 구조화 필드의 우선순위는 유지한다.

### 4.1 로컬 언어 정책의 모양과 이유

2026-10-19 소유자가 재확인한 기존 결정이다. 같은 타입 조립기를 쓴다는 것은
모든 소스가 같은 언어 멤버 모양을 저작해야 한다는 뜻이 아니다.

| 로컬 소스 | 투영 | 결정의 드라이버 |
|---|---|---|
| Note | 제목이 있으면 `name`, 본문은 `content`; 언어가 알려지면 각각 `nameMap`/`contentMap`을 함께 | Misskey, Mastodon, Pixelfed, Lemmy, PeerTube에서 공통 댓글 객체로 쓰이므로 상호호환 폭이 크기보다 우선한다. 평문 키만 읽는 소비자에게도 값이 있어야 한다. |
| Core post / Article | `nameMap` / `contentMap`만, 같은 값의 scalar는 생략 | Misskey의 Article 수용 제약과 FEP별로 고르지 않은 호환성을 배경으로, 프로젝트는 Activity 스냅샷 중복 절감을 택했다. Note와 같은 모양으로 바꾸는 것이 개선은 아니다. |
| Attachment | 기존 scalar 제목 | 이 API가 언어나 map을 새로 추정하지 않는다. |
| Question 안의 선택지 Note | 기존 scalar 선택지 이름 | 독립 댓글 Note의 저작 정책을 내장 선택지에 강제하지 않는다. |

Activities의 `LIFECYCLE.md` §4가 설명하듯 Create와 Update는 불변 Object
스냅샷을 embed한다. Article의 같은 본문을 scalar와 map에 모두 넣으면 그
본문 문자열이 매 스냅샷에 두 번 저장된다. 문서 전체 크기가 정확히 두 배라는
주장이 아니라, 반복되는 본문 바이트를 피하려는 결정이다.

위 제품명과 호환성 제약은 소유자가 설명한 결정 배경이며, 현재 각 피어 버전을
새로 측정한 지원 행렬은 아니다. 이 근거를 이유 없이 일반 타입 규칙으로
바꾸거나, 원격 Article/Note의 수신 표현에 소급 적용하지 않는다.

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

## JSON-LD read API, 2026-10-19

애플리케이션 읽기 진입점은 `axismundi_op_read_object_jsonld($id)`이다.
문자열은 정확한 canonical URI, 정수는 WordPress post ID가 아닌 공통 identity
ID다. REST는 `GET /wp-json/axismundi/v1/objects/read?uri=...` 또는 `?id=...`로
노출하며 둘 중 하나만 받는다. 기존 객체 URL과 연합 응답은 바꾸지 않는다.

해소는 identity와 Object 행에서 시작한다. 등록되지 않은 URI는 실제 WP 글이나
원격 캐시가 있어도 404다. source reader의 `bindings`가 `owner`/`kind` 쌍을
선언하고 `load($object, $identity)`가 정확한 `source_ref`를 읽는다.
`document($source, $object, $identity)`가 조립할 문서 사실을 반환한다.
기존 `supports`/`read`와 블록 뷰모델 계약은 유지된다. JSON-LD callback은
`register_object_assembly($type, $view_callback, $jsonld_callback)`의 세 번째
인자로 등록하며, 해당 callback이 없는 특수화는 `parents`를 따라 상속한다.
원본 소스와 조립 결과 모두 registry의 `id`/`as2_type`과 같아야 한다.

원격 읽기는 네트워크 요청이나 lease 갱신 없이 컬럼, Mention/Hashtag 인덱스,
reply/quote 관계를 조립한다. 같은 타입의 로컬/원격 문서는 같은 JSON-LD
조립기를 사용하되 언어 멤버의 모양을 강제로 통일하지 않는다. 로컬 Article의
map-only, 로컬 Note의 scalar+map, attachment와 Question 선택지의 scalar는
각 소스의 기존 투영 정책을 따른다. 타입 이름만으로 언어 멤버를 생성하지 않는다.
원격은 scalar-only/map-only/both/neither를 수신한 대로 보존하며, 인덱싱 컬럼과
충돌해도 원본의 언어 멤버가 우선한다. HTML과 언어 맵은 공통 정제를 거친다.

Tier 3의 기계 판독 목록은 `axismundi_op_object_payload_fallbacks()`다.
Question의 `oneOf`/`anyOf`/`closed`/`votersCount`, scalar/map 언어 멤버, 원격 미디어
descriptor와 주소지정 멤버가 각자의 구조화 공백 사유를 가진다. 사용된 항목과
사유는 `axPayloadFallback`으로 반환한다. 구조화가 추가되면 그 항목의 fallback을
제거한다. 원격 확장 전체를 이 목록으로 취급하지 않는다.

원격 부가 정보는 프로젝트 전용 멤버 세 개로 분리한다:

- `axObservation`: `kind=remote-observation`, `source_uri`와 저장된
  `fetched_at`, `last_success_at`, `remote_updated_at`, `payload_hash`, `etag`,
  `last_modified`. 시각은 저장된 UTC 값이며, 알 수 없는 값은 null이다.
- `axUninterpreted`: `trusted=false`, 원본 `context`, 해석하지 않은 `members`.
  HTML 문자열도 이 안에서는 원형 그대로인 데이터이며 HTML로 렌더하지 않는다.
- `axPayloadFallback`: 구조화된 출력에 사용된 named fallback과 그 사유.

이 세 멤버의 context IRI는 프로젝트 저장소의 `ns#observation`,
`ns#uninterpreted`, `ns#payloadFallback`이며 `@type: @json`으로 선언한다.
따라서 보존된 원격 context와 확장 내용은 활성 JSON-LD context가 아니다.
발신자가 보낸 blind addressing은 구조화 출력으로 복원하지 않는다.
Tombstone은 이전 본문이나 확장을 되살리지 않고 최소 문서와 관측 정보만 낸다.

기본 requester는 `public`이다. PHP의 `owner`/`authenticated-peer`는 신뢰하는
서버 코드가 명시적으로 grant할 때만 허용한다. REST는 공개 읽기만 노출하고
query의 requester/grant를 신뢰하지 않는다. 비공개 원격 객체는 공개 요청에서
404, 로컬은 기존 public projection gate를 유지한다. 응답은
`application/ld+json`, `Cache-Control: no-store`다.

`audit-object-read-api.php`가 이 프로젝트 선택들을 검사한다. 기존 발행물의
무변 검사는 `audit-object-assembly-baseline.php`가 계속 담당한다. 이 API는
등록을 자동 보충하지 않으며 attachment 발행, page 기본 투영, 쓰기 경로,
컬렉션 페이징, 블록 presenter를 바꾸지 않는다.

## Canonical Activity reads, 2026-10-19

`/activities/{local_uuid}/`의 협상 GET/HEAD는 같은 URI에서
`application/activity+json`을 반환한다. Accept 협상과 `?activitypub` 편의
선택자를 사용하며, REST 읽기 URL로 리디렉션하지 않는다. HTML 경로는 새로
만들거나 바꾸지 않는다.

Activity는 Object registry에 가상 등록하지 않고 Activities 원장에서 조회한다.
로컬 outbound·effective 행, 공개 로컬 Actor, 공개 Outbox의 domain admission
filter를 통과한 스냅샷만 허용한다. 비공개, inbound, 철회, 알 수 없는 UUID는
404다. 의도적인 공개 Outbox opt-in도 같은 filter를 통해 적용된다.

타입 등록의 JSON-LD callback은 애플리케이션 읽기와 공유하지만, 서버가 지정한
두 번째 인자 `snapshot`은 현재 Object의 재조립이 아니라 이미 저작된 Activity
스냅샷의 읽기임을 뜻한다. 기본 callback은 blind recipient만 제거한다.
저장된 embedded Object와 context를 유지하고, callback이 스냅샷을 고쳐 쓰면
응답을 거부한다. 따라서 Article의 map-only 정책도 과거 상태 그대로 유지된다.
응답에는 `Vary: Accept`와 no-cache 경계를 붙이고 HEAD에는 본문을 보내지 않는다.

`audit-activity-read-route.php`는 공유 assembly 호출, 공개 경계, Outbox 스냅샷
일치, 역사 재작성 거부와 namespace 판정을 검사한다. 이 추가는 원격
`ax_object` HTML 뷰를 연합 표현으로 바꾸지 않는다. 기존 Actor 후행 슬래시
리디렉션에서 디버깅 query가 사라지는 문제와 collection 링크의 `object=`
인코딩은 이번 슬라이스에서 수정하지 않는다.

## Hashtag Is Not A Registered Type, 2026-10-20

`Hashtag`를 어휘 레지스트리에 타입으로 등록하지 않는다. **사양과 FEP 어디에도
근거가 없다.** Mastodon 이 그렇게 구현했다는 것은 따를 이유가 되지 않는다.

확인한 근거(2026-10-20):

- AS2 Core §4.1 — "All properties are optional (including the `id` and
  `type`)." 즉 `type` 은 MUST 가 아니고, `type` 없는 JSON 객체는 Link 가
  아니면 Object 의 인스턴스 또는 확장으로 해석된다. W3C 의 microsyntax
  예제에 나오는 `{ id, name }` 태그가 바로 그 모양이며 적법하다.
- `Hashtag` 는 AS2 어휘에 정의된 클래스가 아니다. Mastodon 문서가 보내는
  `@context` 의 `"Hashtag":"as:Hashtag"` 는 **이름을 IRI 에 연결할 뿐
  `Link` 상속을 선언하지 않는다.** `href` 가 있다는 사실만으로 상속이
  확정되지도 않는다.
- FEP-eb48 은 DRAFT 이며 문자열의 해시태그 판별·표시 규칙을 제안한다.
  `Hashtag` 클래스나 그 상속을 정의하지 않는다.

### The Discriminator Is The `#` Prefix

```txt
판정   tag[] 항목의 name 이 '#' 로 시작하면 해시태그로 간주한다
표현   본문에서 그 name 과 일치하는 문자열을 우리가 <a> 로 바꾼다
대상   1. 로컬 taxonomy 의 아카이브 주소
       2. 폴백: 상대방이 id 또는 href 에 명시한 경로
```

타입 대신 `name` 의 접두사로 판정하므로, 타입을 붙이지 않은 W3C 모양과 타입을
붙인 Mastodon 모양이 **같은 경로로 들어온다.** 둘을 받기 위해 두 분기를 둘
필요가 없어진다.

링크화는 수신자의 몫이라는 기존 결정과 같은 방향이다. 평문 멘션을 우리가
링크로 만드는 것과 동일한 근거이며, 상대가 보낸 렌더된 마크업을 신뢰하는 것이
아니다.

폴백 순서가 로컬 우선인 이유: 로컬 아카이브는 우리가 서빙하고 있고 협상 시
AS2 를 반환한다(2026-10-20 측정: `/hashtag/hashtag/` 에 AS2 Accept →
`200`, `{"@context":…,"id":"…/hashtag/hashtag/","type":"OrderedCollection"}`).
원격이 명시한 `id`/`href` 는 **관측 증거로 보존하되 우리 주소로 치환하지
않는다.** 로컬 term 으로 묶었다고 원격 URL 의 정체성까지 같아지지 않는다.

### What This Changes In Code

현재 구현은 이 결정과 두 지점에서 어긋난다. 2026-10-20 확인:

```txt
hashtags.php:268   if ( ! in_array( 'Hashtag', $type, true ) ) { … }
                   원격 tag 증거를 받을 조건이 타입 일치다. 결정에 따르면
                   판정은 name 의 '#' 접두사여야 한다. 타입만 보는 현재
                   코드는 W3C 모양의 무타입 {id,name} 태그를 버린다.

hashtags.php:183   array( 'type' => 'Hashtag', 'name' => …, 'href' => … )
                   우리가 내보내는 쪽에는 여전히 type 이 붙는다.
```

### Outbound Emits `id` And `name` Only

발신 모양도 같은 결정을 따른다(owner, 2026-10-20). `tag[]` 항목은 **`id` 와
`name` 만 발행하고 `type` 을 생략한다.**

```txt
{ "id": "…/?tag_id=123", "name": "#example" }
```

이것이 W3C microsyntax 예제의 모양이며, 등록하지 않은 타입을 와이어에 쓰지
않는다는 결정과 일관된다. `type` 을 생략해도 정보가 사라지지 않는다는 점이
이 모양을 성립시킨다: `id` 는 그 term 의 아카이브 컬렉션 식별자와 **같은
주소**이고, 그 주소를 역참조하면 `type: OrderedCollection` 이 나온다. 즉
**타입은 inline 에서 빠지고 역참조로 발견된다.** 아카이브의 `id` 형태와 그
`OrderedCollection` 선택은 `activitystreams-collection-model.md` 가 소유한다.

`href` 는 더 이상 발신의 필수 요소가 아니다. 사람이 읽는 아카이브 주소는
그 컬렉션 문서의 `url` 로 노출되고, `id` 와 `url` 의 분리는 post·attachment
와 같은 규칙이다.

비용은 명시해 둔다. **Mastodon 은 `tag[]` 를 `type` 으로 걸러서 읽으므로
`{id,name}` 만 받은 해시태그를 링크로 만들지 않을 것이다.** 이것은 Lemmy
호환성에서 이미 한 번 선택한 것과 같은 방향의 선택이다 — 사양에 근거가 없는
구현 관행을 따르기보다 표준 모양으로 간다. 측정 전까지는 "링크화되지 않을
것"이라는 예상이며, 실제 수신 동작은 와이어 테스트로 확인해야 한다.

이미 연합된 문서는 회수할 수 없다. 기존 발행물의 `tag[]` 에는
`type: Hashtag` 와 pretty permalink `href` 가 들어 있고 원격 캐시에 그 모양이
남는다. 이 변경은 이후 발행에만 적용된다.

따라서 아래 핀들은 이 결정과 함께 움직인다. 이들은 사양 핀이 아니라 project
choice 핀이므로, 선택이 바뀌면 핀도 바뀌는 것이 정상이다:
`audit-note-federation.php:150`, `audit-hashtags.php:75`, 그리고
`audit-hashtag-localization.php` · `audit-remote-fetch.php` 의 수신 fixture.

`tag` 자체는 여전히 `Object | Link` 로 열려 있다. 이미지에 `Person` 을 붙이는
AS2 예제가 실재하므로 모든 태그를 링크로 좁히지 않는다. 해시태그는 `tag` 의
한 슬라이스이고, 그 판정 기준이 `#` 접두사라는 것이 이 절의 결정이다.
`tag` 관계 전체의 저장 모양은 `activitystreams-storage-boundaries.md` 의
"Tag Is a Relation Family, Not a Taxonomy Alias" 가 계속 소유한다.

## Sources

- Activity Streams 2.0 Core, section 9.2.2 on conforming consumers.
- ActivityPub, W3C Recommendation 2018-01-23.
- `activitystreams-collection-model.md` for the collection contract axes and the
  specification-versus-project-choice rule, which applies to every pin here.
- `activitystreams-storage-boundaries.md` for the quote property contract and
  the Question storage decision.
