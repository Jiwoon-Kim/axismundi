# SocialHub draft

Status: unpublished discussion draft. No FEP number has been assigned.

Topic title: **Pre-FEP: ActivityPub Client-to-Server Session Discovery**

<!-- BEGIN SOCIALHUB BODY -->
## Summary

An ActivityPub client that knows an actor's URI can retrieve the actor and find its outbox. This proposal addresses a different question: **given this authorization, which local actors can the client act for, and what C2S operations are available?**

This draft defines discovery of an authenticated Session endpoint and an ActivityStreams 2.0 representation of its response. It borrows the separation of credential, accessible scope and advertised capabilities from JMAP without requiring JMAP, and names the scope by what a client needs: the actors it may act for. The Session is a client-to-server resource, not part of federation.

## The situation

A single login may let someone switch between several personal profiles or manage a shared group. That does not imply that every application authorized by that person may act for all of those actors. The [Basic Profile for Social API Servers] describes a single-actor authorization model and a way to return that actor's ID. A Session can describe the same case with one account; when a credential is authorized for several actors, it can describe each of them without requiring the client to infer access from public actor documents.

## Requirements

The key words MUST, MUST NOT, and SHOULD are used as described in [BCP 14] when written in capitals. These requirements are proposed for discussion, not an assigned FEP.

### Discovery

A server implementing this draft MUST provide a public discovery document at `/.well-known/activitypub` on its HTTPS origin. A successful `GET` returns `application/json` with a `session` member containing the absolute HTTPS URL of the authenticated Session endpoint. The URL MUST have the same origin as the discovery document. The public response MUST NOT expose account membership or user-specific permissions. `activitypub` is a proposed well-known suffix here, not a claim of registration.

```json
{
  "session": "https://example.org/wp-json/axismundi/activitypub/session"
}
```

### Session representation

An authenticated `GET` to the Session endpoint MUST return an AS2 extension object as `application/activity+json`. The object MUST have the extension type `Session`, which is a subtype of `as:Object` — it describes a state rather than an action, so it is neither an `Activity` nor, despite carrying a list, a `Collection` — and an AS2 `url` giving the endpoint at which the client can request a fresh view. It MUST NOT carry an AS2 `id`: the endpoint returns a view of the caller's authorization, not an independently identified object. The requirement that objects carry global identifiers applies to objects distributed by the protocol, and a Session is never distributed. The response MUST include `Cache-Control: private, no-store`.

The Session MUST NOT be accepted for outbox publication or delivered through S2S. Its `url` does not make it available to ordinary federated object fetches. A request without valid authentication MUST NOT receive a Session representation.

### Available actors and operations

The Session MUST list only actors the presented credential may act for. Each entry MUST reference a local actor by its canonical AS2 `id` URI, which the client can retrieve to discover that actor's outbox through ordinary [ActivityPub] mechanisms. An entry is a statement about this credential, not an actor document, and carries no authority of its own. A server MUST NOT list an actor merely because the authenticated person can reach it through a different credential.

The Session MUST distinguish server-supported operations from operations available to the caller for each listed account. Each operation pairs an activity type with an object type where the latter is constrained. For example, advertising `Update` of `Person` and `Create` of `Note` does not advertise `Create` of `Person`. A per-account advertisement MUST NOT include an operation outside the intersection of server support, account policy, and credential authorization. It does not promise that an arbitrary object or payload will pass validation. Servers MUST still authorize each outbox submission.

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
      "supportedOperations": {"@id": "c2s:supportedOperations", "@container": "@set"},
      "allowedOperations": {"@id": "c2s:allowedOperations", "@container": "@set"},
      "activityType": {"@id": "c2s:activityType", "@type": "@vocab"},
      "objectType": {"@id": "c2s:objectType", "@type": "@vocab"}
    }
  ],
  "type": "c2s:Session",
  "url": "https://example.org/wp-json/axismundi/activitypub/session",
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

The same design leaves room for separate representations of shared data: an AS2 `Event` could have a [JSCalendar] representation, and a `Place` could have a [JSContact] card of `kind: location` or a geospatial representation served through [OGC API Features]. These are possible implementation mappings, not standardized conversions or dependencies of Session discovery.

## References

- [ActivityPub] and [ActivityStreams 2.0]
- [Basic Profile for Social API Servers]
- [JMAP Session] (RFC 8620, Section 2)

---

## Discussion

1. Is there existing C2S discovery work this should build on? In particular, should the Session URL be advertised through OAuth metadata rather than a new well-known suffix? If a well-known URL is useful, is a redirect sufficient?
2. Is the split between server support and operations available to the credential for each account enough for clients, or do clients also need to distinguish account policy from authorization?
3. Should writable-property discovery for `Update(Actor)` be a separate extension? One possible starting point is JMAP's distinction between server-set and immutable properties, without bringing JMAP's CRUD methods into ActivityPub.

[ActivityPub]: https://www.w3.org/TR/activitypub/
[ActivityStreams 2.0]: https://www.w3.org/TR/activitystreams-core/
[Basic Profile for Social API Servers]: https://swicg.github.io/activitypub-api/basicprofile
[JMAP Session]: https://www.rfc-editor.org/rfc/rfc8620.html#section-2
[BCP 14]: https://www.rfc-editor.org/info/bcp14
[JSCalendar]: https://www.rfc-editor.org/rfc/rfc8984.html
[JSContact]: https://www.rfc-editor.org/rfc/rfc9553.html
[OGC API Features]: https://ogcapi.ogc.org/features/
