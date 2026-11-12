# SocialHub draft

Status: **게시됨** 2026-11-13 — https://socialhub.activitypub.rocks/t/pre-fep-activitypub-c2s-session-discovery-and-multi-actor-access/8990 (post 31661).
되읽기 일치 `265800e1…`. Discourse 가 고쳐 쓴 흔적 없음. FEP 번호는 아직 없다.

> 되읽기: `curl -sS https://socialhub.activitypub.rocks/posts/<id>.json` 의 `.raw`.
> 글 id 는 `t/<topic>.json` 의 `post_stream.posts[0].id`.

Topic title: **Pre-FEP: ActivityPub C2S Session Discovery and Multi-Actor Access**

> `Access` rather than `Accounts`: the draft defines no account model, and the point is that one credential may act for several actors. `for Multi-Actor Credentials` was rejected because it reads as excluding single-actor credentials, which get the same response shape.

<!-- BEGIN SOCIALHUB BODY -->
## Summary

An ActivityPub client that knows an actor's URI can retrieve the actor and find its outbox. This proposal addresses a different question: **given this authorization, which local actors can the client act for, and what C2S operations are available?**

This draft defines discovery of an authenticated Session endpoint and an ActivityStreams 2.0 representation of its response. It borrows the separation of credential, accessible scope and advertised capabilities from JMAP without requiring JMAP, and names the scope by what a client needs: the actors it may act for. The Session is a client-to-server resource, not part of federation.

## The situation

A single login may let someone switch between several personal profiles or manage a shared group. That does not imply that every application authorized by that person may act for all of those actors. The [Basic Profile for Social API Servers] describes a single-actor authorization model and a way to return that actor's ID. It returns one actor for that credential, recommending an `activitypub_actor_id` property in the token endpoint result, and a Session describes the same case unchanged. Looking a Session up never widens an authorization: a credential issued for a single actor still reaches that one actor. What a Session adds is the case where a credential is authorized for several, which it can describe without the client inferring access from public actor documents.

[FEP-65ca: Common Client Configuration][FEP-65ca] is the nearest existing work and answers a neighbouring question: what a server will accept before a user presses "post" — upload and composer limits, account limits, human-facing pages — with a public document and, for an authenticated request, one scoped to the signed-in account. The two meet at server capability and separate at the actor axis: FEP-65ca has no notion of one credential reaching several actors, and this draft has nothing to say about limits. Whether that makes this a second discovery document or an authenticated resource FEP-65ca should point at is the first question below.

## Requirements

The key words MUST, MUST NOT, and SHOULD are used as described in [BCP 14] when written in capitals. These requirements are proposed for discussion, not an assigned FEP.

### Discovery

This draft examines one discovery mechanism — a public well-known document naming the Session endpoint — and specifies it concretely so that it can be argued with. Other mechanisms are plausible and the Discussion asks about them.

A server implementing this draft MUST provide a public discovery document at `/.well-known/activitypub` on its HTTPS origin. A successful `GET` returns `application/json` with a `session` member containing the absolute HTTPS URL of the authenticated Session endpoint. The URL MUST have the same origin as the discovery document. The public response MUST NOT expose which actors any credential may act for, nor any user-specific permission. `activitypub` is a proposed well-known suffix here, not a claim of registration.

```json
{
  "session": "https://example.org/wp-json/axismundi/activitypub/session"
}
```

### Session representation

For an authenticated and authorized `GET`, the Session endpoint MUST return an AS2 extension object as `application/activity+json`. The object MUST have the extension type `c2s:Session`, which is a subtype of `as:Object` — it describes a condition rather than an action, so it is neither an `Activity` nor, despite carrying a list, a `Collection` — and an AS2 `url` giving the endpoint at which the client can request a fresh view. A Session is an authenticated discovery response defined by this extension, for the use of a client-to-server client alone. It MUST NOT carry an AS2 `id`, MUST NOT be submitted to an ActivityPub outbox, and MUST NOT be delivered to an ActivityPub inbox. Its `url` identifies the endpoint, not an individual snapshot: the endpoint returns a view of the caller's authorization rather than an independently identified object. The response MUST include `Cache-Control: private, no-store`.

Its `url` does not make it available to ordinary federated object fetches.

Being authenticated is not sufficient. A server MUST return a Session only to a credential that is authorized for this endpoint, and MUST reject one that is merely valid for some other API on the same host, as [RFC 6750 Section 5][RFC6750-5] describes for bearer tokens. Sharing an origin with the discovery document does not establish that a token was issued for this resource. This draft does not name an OAuth scope for it.

The Session SHOULD carry a `state` string identifying the version of this view, after the [JMAP Session][JMAP Session] property of the same name. A client that holds a `state` can tell whether a re-read returned anything new. On its own that is weak: it learns of a change only by re-reading. JMAP pairs it with a `sessionState` on every API response, so a client detects the change without polling; whether ActivityPub responses should carry such a value is a protocol decision this draft does not take, and the Discussion asks about it. A `state` is a cache hint and nothing else — a revoked authorization MUST be enforced by the outbox on each submission, not by waiting for a client to re-read its Session.

### Available actors and operations

The Session MUST list only actors the presented credential may act for. Each entry MUST reference a local actor by its canonical AS2 `id` URI, which the client can retrieve to discover that actor's outbox through ordinary [ActivityPub] mechanisms. An entry is a statement about this credential, not an actor document, and carries no authority of its own. A server MUST NOT list an actor merely because the authenticated person can reach it through a different credential, and MUST NOT omit an actor the presented credential may act for. The list is complete for that credential while an operation advertisement is not, and the asymmetry is deliberate: finding the actors one can switch to is what the Session is for, and a client cannot discover an omitted actor by any other means, whereas an unadvertised operation costs it only a conservative interface.

The Session MUST distinguish server-supported operations from operations available to the caller for each listed actor. Each operation pairs an activity type with an object type where the latter is constrained. For example, advertising `Update` of `Person` and `Create` of `Note` does not advertise `Create` of `Person`. A per-actor advertisement MUST NOT include an operation outside the intersection of server support, actor policy, and credential authorization. It does not promise that an arbitrary object or payload will pass validation. Servers MUST still authorize each outbox submission.

An advertisement is not a complete list. In this version a client MUST NOT read the absence of an operation as a denial, and MUST NOT read an entry with no `objectType` as permission for every object type: an omission means the server did not advertise it. A client may use what is advertised to offer an action confidently. Unadvertised operations remain unknown; the Session is not a preflight check, and an outbox submission is a real post or a real follow, not a capability query. Making omission significant would require servers to enumerate exhaustively, which is a larger commitment than this draft asks for.

### Example

The namespace below is illustrative. An eventual specification would assign stable IRIs for the extension terms. Activity and object type values are vocabulary IRIs, not display labels.

```json
{
  "@context": [
    "https://www.w3.org/ns/activitystreams",
    {
      "c2s": "https://example.org/ns/c2s#",
      "as": "https://www.w3.org/ns/activitystreams#",
      "availableActors": {"@id": "c2s:availableActors", "@container": "@set"},
      "actorRef": {"@id": "c2s:actorRef", "@type": "@id"},
      "state": {"@id": "c2s:state"},
      "supportedOperations": {"@id": "c2s:supportedOperations", "@container": "@set"},
      "allowedOperations": {"@id": "c2s:allowedOperations", "@container": "@set"},
      "activityType": {"@id": "c2s:activityType", "@type": "@vocab"},
      "objectType": {"@id": "c2s:objectType", "@type": "@vocab"}
    }
  ],
  "type": "c2s:Session",
  "url": "https://example.org/wp-json/axismundi/activitypub/session",
  "state": "c1f4a0",
  "supportedOperations": [
    {"activityType": "as:Create", "objectType": "as:Note"},
    {"activityType": "as:Update", "objectType": "as:Person"}
  ],
  "availableActors": [
    {
      "type": "c2s:AvailableActor",
      "actorRef": "https://example.org/actors/alice",
      "allowedOperations": [
        {"activityType": "as:Create", "objectType": "as:Note"},
        {"activityType": "as:Update", "objectType": "as:Person"}
      ]
    },
    {
      "type": "c2s:AvailableActor",
      "actorRef": "https://example.org/actors/community",
      "allowedOperations": [
        {"activityType": "as:Create", "objectType": "as:Note"}
      ]
    }
  ]
}
```

## What this does not specify

This proposal does not define OAuth grants, account provisioning, access-control storage, or a new outbox API. It does not define field-level editing rules for `Update(Actor)`. An operation advertisement says that actor updates are available; it does not say that fields such as `inbox` or `outbox` are writable.

## Implementation notes

Axismundi's account model started with profile switching: one WordPress login can access multiple actor accounts. The design later drew on the [JMAP Session] distinction between an authenticated user, accessible accounts, and capabilities. Axismundi treats an application account and its ActivityPub actor as the same underlying entity. That gives future JMAP extensions the option of using the same account and access-control model, without equating JMAP Mail's Inbox and Sent mailboxes with ActivityPub's inbox and outbox. No such storage model is required by this draft.

The JMAP half of that is implemented and is the experience behind this draft: Axismundi serves an RFC 8620 Session with `capabilities`, `accounts`, `primaryAccounts`, `username` and `state`, discovered at `/.well-known/jmap`. Its `state` is a hash of the canonicalised Session, so a server can offer one without storing a version anywhere. What is not implemented is the subject of this draft: there is no ActivityPub Session, no `availableActors` or `allowedOperations`, and authorization is by WordPress cookie or application password rather than per-token scope. The draft is written from the account model, not from a shipped implementation of itself.

## References

- [ActivityPub] and [ActivityStreams 2.0]
- [Basic Profile for Social API Servers]
- [JMAP Session] (RFC 8620, Section 2)
- [FEP-65ca: Common Client Configuration][FEP-65ca]

---

## Discussion

1. Would a [FEP-65ca] discovery document pointing at a separate authenticated Session resource be enough, instead of a second well-known suffix? FEP-65ca leaves OAuth server discovery to [RFC 8414][RFC8414], but that does not settle where a Session URL belongs. If a well-known URL is useful here, is a redirect sufficient?
2. Is the split between server support and operations available to the credential for each actor enough for clients, or do clients also need to distinguish actor policy from credential authorization? Should an omitted operation ever be significant?
3. Does an AS2 extension earn its keep over plain JSON? The draft prefers it because actor and operation types are already IRIs in a vocabulary everyone has agreed on. Against that: the Session has no `id`, is never delivered and is never fetched by another server, and an existing AS2 parser does not know what `availableActors` means. JMAP's Session and FEP-65ca's configuration document are both ordinary JSON.
4. How should a client learn that its Session changed? JMAP returns `sessionState` on every API response, so a client notices without re-reading. The ActivityPub equivalent would mean putting a value on outbox responses, which is a larger decision than this draft should take alone.
5. Should writable-property discovery for `Update(Actor)` be a separate extension? One possible starting point is JMAP's distinction between server-set and immutable properties, without bringing JMAP's CRUD methods into ActivityPub.

[ActivityPub]: https://www.w3.org/TR/activitypub/
[ActivityStreams 2.0]: https://www.w3.org/TR/activitystreams-core/
[Basic Profile for Social API Servers]: https://swicg.github.io/activitypub-api/basicprofile
[JMAP Session]: https://www.rfc-editor.org/rfc/rfc8620.html#section-2
[FEP-65ca]: https://codeberg.org/fediverse/fep/src/branch/main/fep/65ca/fep-65ca.md
[RFC6750-5]: https://www.rfc-editor.org/rfc/rfc6750.html#section-5
[RFC8414]: https://www.rfc-editor.org/rfc/rfc8414.html
[BCP 14]: https://www.rfc-editor.org/info/bcp14
<!-- END SOCIALHUB BODY -->
