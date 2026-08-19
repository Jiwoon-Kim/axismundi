# Place and Contacts

Two plugins decided together, because they meet at one question: **what does it mean to save
something so you can act on it later?** Plan only; nothing here is built.

- **Owners**: Axismundi GeoData (Place), Axismundi Contacts (personal directory)
- **Standards**: AS2 `Place`, [RFC 9553](https://www.rfc-editor.org/rfc/rfc9553.html) /
  [RFC 9982](https://www.rfc-editor.org/rfc/rfc9982.html) (JSContact, `kind: location`),
  [RFC 6350](https://www.rfc-editor.org/rfc/rfc6350.html) (`KIND:location`),
  [RFC 7565](https://www.rfc-editor.org/rfc/rfc7565.html) (`acct:`)

---

## 1. What exists today

Measured, not remembered.

| Thing | State |
|---|---|
| `geo_area` taxonomy | Hierarchical, public, REST. Administrative and natural areas |
| `geotag` taxonomy | Flat, public, REST. The location index on content |
| Term meta | `geo_latitude`, `geo_longitude`, `ax_geo_bounds`, `ax_geo_zoom`, `ax_geo_country_code`, `ax_geo_iso_3166_2` |
| `ax_geo_place_type` | Controlled vocabulary, generated from a reviewed TSV |
| `ax_geo_place_id` | Canonical namespaced external id — `google:…`, `osm:node/…`, `wikidata:Q…`, `manual:…` |
| **Place CPT** | **Does not exist** |
| **Contacts plugin** | **Does not exist** |

Two of those already decide things. `place_type` is a **shared vocabulary held as term meta**, not a
taxonomy of one object — so a Place can use the same terms without either owning them. And
`ax_geo_place_id` shows the external-identity habit is already namespaced, which is the shape a
federated `sameAs` wants.

## 2. Why these two are one decision

A phone book makes calling easy. An address book makes mailing easy. Neither question is the one
being asked here:

```
tel:      → call
mailto:   → write
Actor URI → mention · follow · message · invite · RSVP · save
```

Contacts is first an **Actor-owned private address book of JSContact Cards**. URI-addressable
entities enrich some Cards and add actions, but a URI is neither required nor the primary key of a
contact. The type and endpoints a Card holds decide which actions are offered:

```
Person        follow · mention · message · invite
Group         join · mention · invite · post to
Organization  follow · mention · contact
Place         save · set as location · check in · see posts here
Event         RSVP · save · invite · share
```

Place belongs in that directory. And that is exactly why the two plugins cannot be designed apart:
**saving a remote Place is where a personal bookmark and a site's place graph collide.**

## 3. Remote identity: URI, not handle

For a federated identity, the canonical key is the **dereferenceable URI**. Everything else is a
locator.

```
canonical identity   https://example.social/users/alice     ← AP id, fetchable
account identifier   acct:alice@example.social              ← WebFinger, portable
display locator      @alice@example.social                  ← what a person types and reads
```

A handle is **derived and presentational**. It is resolved from the URI, not stored as the identity.
This matters because **a Place has no handle and never will** — it is an Object, not an Actor.

This does not make a URI the identity of a Contact Card. A Card may be created with only a name and
telephone number; an Actor URI, `acct:` identifier, Place URI, or Event URI is an optional link in
that Card. Local Contacts IDs and optional JSContact `uid` values remain distinct from all remote
identifiers.

Consequence: `jiwoon@example.com` and `@jiwoon@example.com` are different namespaces. Nothing may
infer one from the other.

## 4. Place is a profile; Geotag is an index

```
Geotag    "어디인가"  — the location index on content, and its archive
Place     "거기에 무엇이 있는가" — the entity: operator, hours, contact, menu, events, reviews
```

Separate templates, separate URLs, bound UX:

```
/geotag/gwangalli/     everything about this location
/place/gwangalli/      what this place is
```

**Bind the presentation, never copy the data.** A Geotag with no description falls back to the
Place's excerpt; with no image, to the Place's featured image. Copying them into term meta would
create a second authority and a sync problem for a value that is only ever displayed.

### Place ↔ Geotag is one-to-many, and time-bounded

A business closes. Another opens in the same unit. A shop moves. The location is durable; the
occupant is not.

```
Geotag: ABC building          ← coordinates, bounds, area, place_type
   ├── Place: Cafe A     1F   2021-03 – 2024-07   permanently_closed
   ├── Place: Clinic B   2F   2022-01 –           active
   └── Place: Shop C     1F   2024-08 –           active
```

So the relation carries the facts that vary:

```
place ↔ geotag occupancy
  floor · unit · entrance · valid_from · valid_until
```

and the Place carries `business_status` (`active` / `temporarily_closed` / `permanently_closed`).

**Coordinates live on the Geotag.** A Place resolves them through its current occupancy. A business
relocating is not a Place with new coordinates — it is a new occupancy row pointing at a different
Geotag, which is why a per-Place coordinate override is mostly unnecessary. Address, floor and unit
stay on the Place side, because those describe the occupant rather than the point.

### Place is hierarchical, for containment only

```
Shinsegae Centum City
├── Department store
├── Spa
└── Ice rink
```

`post_parent` means *inside*. It does not mean brand, franchise or operator — those are relations to
an Actor (`operator`, `owned_by`), and putting them in the tree is how the hierarchy stops meaning
anything. Permalinks stay flat, because a parent can change and a URL should not.

## 5. Remote Place: binding, not import

If Busan publishes an official `Place`, that is the authority for its name, address and hours — and
we should not fork it into a local copy that immediately goes stale. But a remote object cannot join
a local hierarchy, taxonomy or query either.

So four distinct states, and only the last joins the site's structures:

```
seen         encountered in a document
cached       fetched and stored as raw representation
saved        in somebody's personal directory
materialized a local Place node exists and participates in hierarchy, taxonomy, archives
```

And two distinct relations, which are not the same thing:

```
sameAs   an assertion: these describe one real-world place        → published in AP
binding  an implementation link: that remote is this Place's source → internal only
sync     an operation: pull the bound fields                        → internal only
```

A materialized Place keeps **its own AP id** and publishes `sameAs` to the authoritative one. It does
not adopt the remote id, because it is a different web resource with its own local hierarchy and
editorial content.

**Field authority is per field**, and the split is not negotiable:

```
remote-controlled   official name · address · coordinates · phone · hours
local-controlled    parent · taxonomy · editorial summary · photos · notes · recommendations
local override      a display name somebody chose, which a sync must never overwrite
```

`parent` being local is the whole reason this works: a remote `Update` can refresh the facts without
rearranging the site.

### Saving and materializing have different owners

The two acts sit next to each other in the UI and belong to different authorities, so say it once
here rather than discovering it as a bug report:

```
saving a Place       an acting Actor's private address book   → visible to that Actor
materializing one    the site's place graph                   → visible to everybody
```

A personal bookmark must never enter the site's hierarchy, archives or maps on its own. Somebody
saving a café is filing it for themselves; somebody materializing it is asserting that this site
now carries a page for that place. Without the line drawn, "I saved it, why is it not in Places?"
is the first support question, and the wrong fix for it is to make saving publish.

## 6. Contacts: Actor-owned JSContact address books

Contacts owns address books and Cards. It is not an overlay on the Actors remote cache and it is not
a second public Actor-profile store.

```text
acting Actor
  └─ AddressBook
       ├─ JSContact Card: phone-only local person
       ├─ JSContact Card: manually maintained business
       ├─ JSContact Card: imported Google contact
       └─ JSContact Card: card linked to an Actor or Place
```

The default AddressBook belongs to the current acting Actor. Actors supplies the ownership and
manager gate; it does not dictate a Card's type, fields, or existence. This allows a Person's
personal address book today and a separately authorised Organization/Group directory later, without
turning all Cards into Actors.

Each AddressBook may designate one normal JSContact Card as its **self Card**. It is the card the
owner edits for sharing their contact details, personal contact preferences, and address-book use.
It may link to the owning Actor, but it is not the Actor profile and is not a second serialization of
it.

```text
Actor public profile              AddressBook self Card
ActivityPub / public JSContact    private or selectively shared JSContact
owned by Actors                   owned by Contacts
profile-page and federation use   contact sharing, import/export, local workflows
```

The two can seed or copy selected values only through an explicit command. There is no background
two-way sync: editing a private telephone number must not publish it, and a remote/public Actor
update must not overwrite the contact details its owner maintains.

### Canonical storage

The canonical contact payload is a **JSContact 2.0 Card**. Contacts stores that Card as authored or
explicitly imported, plus local bookkeeping needed to address, query, and synchronise it:

```text
address_books       local id, owner_actor_id, title, access policy, timestamps
contact_cards       local id, Card JSON, JSContact uid (nullable), revision, timestamps
card_sources        card id, provider/resource id, cursor/etag, sync policy, provenance
card_index          card id, searchable normalized projections only
card_media_override card id, locally chosen photo/media reference (optional)
```

The exact physical representation may be a JSON document plus indexes rather than a table for each
JSContact property. The invariant is that JSContact's nested/multi-value structure is retained and
that the storage model does not collapse it into scalar contact columns.

`ContactCard.id` is a local store/JMAP identifier. JSContact 2.0 `uid` is optional and is preserved
when received; it is minted only when Contacts creates a portable Card identity. Neither value is an
Actor UUID, Actor URI, Place URI, email address, or handle.

A Card belongs to one AddressBook in v1. The same Actor URI, Place URI, email address, or telephone
number may therefore appear in independent Cards in several AddressBooks. For example, a Person
Actor and an Organization Actor may both save the same remote photographer but use different label,
photo override, notes, endpoints, and provider connections. Their linked Actor avatar may refresh
from the same Actors cache; their Card data never becomes shared.

```text
Person AddressBook       Card 41 -- linked Actor URI --> remote photographer
Organization AddressBook Card 98 -- linked Actor URI --> remote photographer
```

The UI may warn that the current AddressBook already has a matching endpoint or link, but it must
not merge automatically. Multi-AddressBook membership from JMAP is deferred until there is an
explicit same-owner/shared-Card product requirement; it must not accidentally make a manager's edit
to one directory rewrite another directory's Card.

### Actor links and avatar resolution

Actors is a required dependency for two narrow purposes:

1. an AddressBook is managed through its owning Actor and the existing acting-Actor/manager gate;
2. a Card may link to a local Actor UUID or canonical Actor URI and resolve its public profile.

The avatar resolver is explicit and does not guess a person from an email address:

```text
Card's explicit local photo/media override
    -> Card's own JSContact media photo
    -> linked local Actor icon (Actor UUID)
    -> linked remote Actor cached icon (Actor URI)
    -> initials/default avatar
```

Remote Actor caching remains Actors' responsibility. Contacts only asks Actors for a current public
avatar snapshot and records the link that justified it. Gravatar or another enrichment service, if
ever added, is an opt-in provider after these explicit sources; it is not identity proof and must
not silently upload or disclose an email address.

### Resolution, import, and edit authority

- **A phone-only Card is first-class.** Resolution failure never makes it invalid.
- **Capabilities are computed from Card endpoints**, never stored as a fake entity type.
- **Adding by URI is an explicit convenience.** Paste a handle → WebFinger → Actor URI → fetch →
  propose Card values. Paste a URL → fetch → propose values. The user decides whether to create,
  update, or link a Card.
- **Local editing is primary.** Import, provider sync, and autocomplete are optional sources with
  provenance and explicit conflict choices; they must not overwrite user-authored fields silently.
- **No automatic merge.** Similar name, shared email, or a matching avatar is not evidence that two
  Cards or a Card and an Actor are the same person.
- **A private Card note is not a profile summary.** Different store, different id, never published.

### Google People API adapter (deferred, reference implementation)

Google People API is the reference for adapter behaviour and browser-address-book workflows, not
the Contacts domain model. Its `Person` response may merge contact, public profile, and Workspace
sources; only contact-based people can be mutated. An Axismundi adapter imports a selected Google
source into a user-managed JSContact Card and retains provenance instead of treating the returned
Person as authoritative.

#### Connection and storage contract

```text
google_connections
  owner_actor_id        AddressBook owner
  wp_user_id            OAuth grant holder; must currently manage owner_actor_id
  scopes                contacts.readonly or contacts
  token reference       encrypted credential-store reference, never Card JSON
  contact_field_mask    immutable for a sync-token generation
  sync_token            nullable; final page of a completed full sync only
  status / last_sync_at / next_retry_at

google_card_sources
  contact_card_id
  google_connection_id
  resource_name         people/<id>
  contact_source_etag
  import_mode           copy-once | pull | two-way
  source_snapshot       last accepted Google contact source only
  field provenance / conflict state

google_group_sources
  google_connection_id, resource_name, etag, local Contact-group mapping
```

The OAuth connection belongs to the acting Actor's AddressBook but is granted by a WordPress user.
Every sync and mutation rechecks that the user still manages that Actor. Credentials are never
stored in a Card, REST response, activity, or browser-localised script.

Start with the read-only `contacts.readonly` scope. Two-way sync is a separate opt-in upgrade to
the broader `contacts` scope. The adapter must offer a disconnect-and-delete-credentials path
without deleting the user's local Cards.

#### Initial import

1. Call `people.connections.list` for `people/me`, request a deliberate stable `personFields`
   mask, and paginate every result.
2. Restrict imported data to contact sources where possible. Google may merge profile/domain data
   into its Person view; that enrichment must not silently become an editable local contact fact.
3. Store the `nextSyncToken` only after the final page completes. A partial full import has no valid
   incremental baseline.
4. Present cards for create/link/skip; never auto-merge by name, email, phone, or avatar.
5. Treat `otherContacts` as a separate, explicitly enabled import queue. Gmail-discovered entries
   are not equivalent to Contacts the user intentionally maintains.

The field mask is a protocol contract, not a UI convenience. Google requires later page-token and
sync-token calls to use the same parameters as the initial request. Changing fields, source mode,
or mapping version invalidates the stored token and requires a new full sync.

#### Field mapping rules

| Google Person | JSContact Card | Import rule |
| --- | --- | --- |
| `names` | `name` | Retain the complete display value and structured components where the mapping is lossless. |
| `emailAddresses`, `phoneNumbers`, `sipAddresses`, `imClients` | `emails`, `phones`, `onlineServices` | Retain label/type, primary preference, and provider provenance. |
| `addresses`, `urls`, `calendarUrls` | `addresses`, `links`, `calendars` | Preserve structured values and labels; do not turn an address into a GeoData Place automatically. |
| `organizations`, `occupations` | `organizations`, `titles` or `personalInfo` | An affiliation is not an Axismundi Organization Actor. Link one only by explicit user action. |
| `birthdays`, `events` | `anniversaries` | Preserve as private Card facts; Contacts/Calendar may later derive a birthday calendar. |
| `nicknames`, `relations`, `biographies`, `userDefined` | matching Card fields or private extension/provenance | Do not force deprecated Google name categories into public Actor fields. |
| `photos` | `media` | Keep a provider photo reference/snapshot under source policy; do not replace a user-selected local photo. |
| `memberships` / ContactGroup | Contacts labels/groups | Google contact groups map to local labels/groups, not AddressBooks: they are membership filters, not ownership/ACL containers. |

Google's phonetic name strings have no notation/system guarantee suitable for Axismundi's structured
phonetic contract. Preserve the raw provider value in source provenance until the user supplies a
notation or script; do not publish an ambiguous phonetic value as a JSContact fact.

#### Incremental pull and deletion

- Use `syncToken` after a completed full import and process every page.
- A returned `PersonMetadata.deleted` means the Google source was removed. Mark the source link
  deleted; never silently delete the locally owned Card.
- An expired token requires a new full sync. Google documents a seven-day token lifetime and returns
  an expiration error for it.
- Incremental sync is not a read-after-write confirmation channel: Google documents propagation
  delay. Local mutation responses, not a later pull, settle the immediate UI state.
- `otherContacts` has the same sync-token/deletion shape but remains separately opted in.

#### Two-way mutation, only after pull is reliable

Google updates replace every field named in `updatePersonFields`; they are not per-item patches.
For each outbound change the adapter must:

1. obtain or retain the contact-source `etag`;
2. compute a Google field mask from an explicit user-approved change set;
3. send only one user's mutations sequentially;
4. on an etag precondition failure, fetch current contact data, show a field conflict, and require a
   new choice rather than retrying an old Card over it;
5. record the returned resource name and etag as the new source snapshot.

Create, update, and delete affect only Google contact-source data. Deleting a Google source must
not destroy Axismundi-local edits or a linked Actor/Place. Batch endpoints are a throughput tool,
not permission to bypass the same per-Card conflict rules.

## 7. Representations and protocols

For Actors and Places, JSContact and vCard are projections from their domain facts:

```
                 Actor / Place domain model
                      │
        ┌─────────────┼─────────────┐
        ▼             ▼             ▼
   ActivityStreams  JSContact     vCard
```

Not a chain. Generating vCard from JSContact from AS2 loses meaning at each hop.

For Contacts, the direction is different: the JSContact Card is canonical; vCard is an import/export
projection. JMAP Contacts is a later C2S protocol over the AddressBook and ContactCard model, not a
requirement for Contacts v1 and not an ActivityPub federation mechanism.

```
Person / Organization   →  Card                    KIND:individual / organization
Place                   →  Card kind: location     KIND:location
Event                   →  JSCalendar
```

Discovery from the Actor, using what AS2 already has rather than a private field:

```json
"attachment": [
  { "type": "Link", "name": "Contact",
    "href": "https://example.com/@alice/contact",
    "mediaType": "application/jscontact+json" }
]
```

`mediaType` is the discriminator — never `name`. One `/contact` URI, content-negotiated to
`application/jscontact+json` or `text/vcard`.

**Do not inline the Card into the Actor document.** Contact facts have per-field visibility that the
Actor document has no way to express, and every fetch of an Actor would carry a phone number nobody
asked for.

**A fediverse handle has a standard home**: JSContact `onlineServices` (with `user` = `@handle`,
`uri` = Actor URI) and vCard `SOCIALPROFILE`. No custom top-level field is needed.

**`sameAs` does not go into the vCard.** The URI is the entry point; a reader follows it and finds
the AP representation with the assertion in it. Duplicating the relation across serializations only
creates a second thing to keep in step.

## 8. Names and addresses are written, not translated

Already settled for Actors; it applies to Places and repeats here because Places have more of it.

```
original        東京都台東区浅草              language + script
romanized       Taito City, Tokyo            transliteration, not translation
transcribed     도쿄도 다이토구 아사쿠사       how a Korean reader says it
localized       Tokyo, Japan                 an actual other-language name
```

`부산 → Busan` is a romanization; `대한민국 → South Korea` is a localization; `北海道 → 홋카이도` is a
transcription while `북해도` is a Han reading. Storing all of these in one "translation" field loses
which is which, so the model carries **language + script** (BCP 47), not a language alone.

Korean official romanization is not a naive transliteration — `종로` is `Jong-ro` as a road and
`Jongno-gu` as a district — so a generated value is a fallback, and the official one wins when it can
be looked up.

For travellers the display rule is: **their language first, the local original second**, because the
first is read and the second is shown to a taxi driver.

## 9. Where the line between the two plugins falls

```
GeoData        geotag · geo_area · place_type vocabulary · Place CPT · occupancy ·
               remote Place binding and sync
Contacts       Actor-owned AddressBooks · canonical JSContact Cards · imports/sync provenance ·
               private notes · capability and avatar resolution
Actors         Actor identity and profile facts · JSContact/vCard for Actors
Object Proj.   URI resolution · public gate · content negotiation · transformer registry
Calendar       recurrence, secondary calendars, the birthday calendar built from Contacts
```

Contacts stores a **reference** to a Place, not a Place. Saving a remote Place to a personal
directory does not materialize it into the site's place graph — those are different acts by
different authorities, and conflating them means one person's bookmark edits everybody's map.

## 10. Decisions taken

The four that were open are settled, with the reasoning kept because each of them will look
arbitrary in six months otherwise.

**Materializing needs its own capability.** A personal save is cheap and reversible; materializing
enters the site's graph, so it is gated by `manage_places` rather than by whoever can edit a post.
This is the same line §5 draws between the two owners — the capability is how it is enforced rather
than merely described.

**A Place does not require a Geotag.** It may exist `unlocated` and be bound to one later. Demanding
a location at creation sounds tidy until the first import of a business list arrives without
coordinates: the requirement does not produce locations, it produces invented Geotags nobody
reviewed, and those are worse than an honest blank.

**Place lives in GeoData, with its seams drawn now.** Splitting it into its own plugin today would
have two plugins reaching across each other for `geotag`, `geo_area` and the `place_type`
vocabulary on every query. So it starts inside GeoData but keeps its own tables and a `place_`
function prefix, which is what makes a later split a move rather than a rewrite. The moment Place
carries menus and reviews in earnest, that split becomes worth doing.

**v1 consumes remote Places and publishes none.** Publishing means becoming an authority that other
servers bind to and sync from, and taking that on before the occupancy and binding models have been
watched working would be promising stability we have not yet demonstrated. Consumption exercises
every part of the design — fetch, cache, bind, sync, field authority — without anybody else
depending on the answer.

## 11. Identity and audience (decided while building B1)

**JMAP is an adapter, never the store.** `AddressBook` and `ContactCard` are defined by RFC 9610,
but the domain owns them: Contacts keeps address books and would keep them if JMAP never arrives.
A later JMAP plugin is a thin Core — session, capabilities, method routing — over which Contacts
registers the contacts capability and Calendar registers its own. The reverse arrangement, one
plugin owning both data sets because one protocol describes both, couples two domains through a
transport.

**A uid identifies a Card, not a person and not an endpoint.** Two Cards can describe one person —
a personal one and a work one, or two that separate systems minted independently — so
`UNIQUE (owner_actor_id, uid)` says *the same Card is not stored twice by one owner* and nothing
more. Deciding two differing Cards are one person is a merge: proposed from names, numbers and
Actor URIs, and confirmed by somebody.

**Ownership is a column, containment is a relation.** A Card is owned by one Actor and filed into
as many of that Actor's books as they choose. Access is decided by ownership, which never has two
answers; `개인 / 업무 / 가족` are containers, which is also the shape JMAP addresses as
`addressBookIds`. Keeping ownership out of the relation makes it structurally impossible for one
Card to appear in two different people's books.

**Fetch by Actor URI; match by uid.** `/profile/{uid}` cannot be the route: uid is optional in
JSContact 2.0, is usually a `urn:uuid:` that dereferences to nothing, and a Card somebody *received*
carries a foreign one. The Actor URI is already discoverable and already signed. The uid travels
inside the returned document, where it does the one job it is for — letting a receiver recognise the
Card they saved before, so a wider audience or a first sync adds to that contact instead of creating
a second one.

**Knowing a uid is never permission.** A UUID is hard to guess and that is not access control.
Identity, authorisation and projection are three separate questions and the code answers them
separately.

**An audience only this site can decide does not federate.** `contacts` is answered from the owner's
own address book — whether *they* saved the requester — because the other direction cannot be
checked without reading somebody else's book. A request arriving from another server is not measured
against it by a weaker test; it is not served at all. `public` federates, `contacts` is local-only,
`off` is owner-only. `followers` and `mutuals` are refused today for the same reason they may be
accepted later: an audience is offered once it can be verified.

**One profile Card per Actor, not per account.** Samsung and Google give an account one profile
card; here the unit is the Actor, because one person acts as themselves, as a Group and as an
Organization, and each publishes something different — a Person has a birthday and relatives, an
Organization has a department and a main number. Switching the acting Actor switches which profile
is in front of somebody. Personal versus work is not two Cards: it is `contexts` on the entries
inside one Card, which is what JSContact is shaped for. Several profile Cards per Actor would
immediately ask which is primary, which the Actor endpoint advertises, which the QR encodes, and
whether a receiver holding both should see one contact or two — questions with no good answers and
no need to exist.

That rule holds for the self card only. Contacts imported into an address book keep the opposite
property: one real person can arrive as several Cards with different uids, from Google, from a
company directory, from somebody typing one in, and reconciling those is the merge layer's job.

**The profile binding owns the audience, not the address book.** `ax_contact_actor_profiles` binds
one Actor to one Card and carries `sharing`. Putting it on the default book worked only because that
row happened to exist per Actor; the moment an Actor opens a second book it asks which book's
audience wins, out of a question that has one answer. Putting it on the Card would mix contact data
with publication policy. The binding is the thing that is actually about publishing, so it holds the
setting — and QR projections, profile discovery and per-audience field lists land there later
without touching the address book schema.

**A Card is not a by-product of an Actor or a Place existing.** `Card.kind` covers `location`,
`device` and `application` as well as people, so a Card is an independent representation of a
contactable entity rather than a shadow of an Actor. Nothing auto-creates one: a Person gets a
profile card because they are the contact keeper, an Organization or Group is offered one, and an
Application, a Service or a Place gets none — those are administered elsewhere, and a Place that
minted a location Card on creation would fill an address book with rows nobody wrote. A Place is an
autocomplete source for a location Card, not its owner and not its identity: two people saving the
same Place get two Cards, and neither uid comes from the Place.

**Actor type suggests a kind; it never fixes one.** ActivityStreams actor types and JSContact kinds
are different vocabularies and do not align — `Service` has no JSContact kind at all, and a Service
Actor may honestly be `application` or `org`. So the type provides a starting value at creation, the
Card owns it from then on, and changing an Actor's type never rewrites an existing Card. What *is*
enforced is JSContact's rule about its own document: a Card listing `members` is a `group`. Unknown
and vendor kinds are stored as written.

**A uid is minted only for what this site is the authority on.** The profile card gets one because
other people will hold copies of it. Cards about other people do not: a uid somebody can quote
should come from the entity it describes, and inventing one for every contact in a private address
book would put this site's identifiers into other people's exports. A location card usually has
none, and that is correct rather than incomplete.

**Ownership is the deletion root, and the trigger differs by Actor type.** What goes is what an
Actor *owned* (`owner_actor_id`); what stays is every Card somebody else wrote *about* them, which
is that person's record. A Person Actor shares a lifetime with the WordPress account it is, so
deleting the account takes the personal address book — leaving it would keep other people's phone
numbers and somebody's private notes in a book no account may ever open again. An Organization
outlives whichever administrator was deleted and its client and partner lists are the
Organization's, so it is purged only when somebody asks, having been shown a count first. `tombstone`
is deliberately not the trigger: every Actor reaches it, and it says an identity ended rather than
that this data may be destroyed.

| Actor | Profile card | Address books | Ends with |
| --- | --- | --- | --- |
| Person | auto, `individual` | auto | the WordPress account |
| Organization | on request, `org` | on request | an explicit purge |
| Group | on request, `group` | none | an explicit purge |
| Application, Service | none | none | nothing to end |

Default policy is manual purge for everything except the account path. A delayed automatic purge
after tombstone is a later option, not built: it needs a scheduler and a grace period, and personal
servers and cautious installations want different answers.

**Contacts owns the JSContact document; every other domain contributes to it.** Actors used to build
a Card of its own and mint a `uid` from the Actor's UUID, so two plugins published a Card for one
Actor under different identifiers. Now Contacts holds the Card, serves it at the unchanged
`/@handle.jscontact`, and opens a filter; Actors adds the names in the Actor's other languages and
its anniversaries, Calendar adds calendars. `uid` and `kind` are restored after the filter runs — a
contributor may add what it owns and may not change which card this is.

**The name is written down twice, deliberately, and only for a profile card.** The Card stores the
whole JSContact name because a Card holding only the parts Actors owns would lose a title on the
first round trip, and a Card that rebuilt its name on render would not be a store. Actors keeps
`full, given, given2, surname, surname2` and the reading order; Contacts keeps what a contact card
adds — title, credential, separator, phonetics. A save on either side carries the shared parts over
and leaves the other side's alone. Neither side ever splits a `full` into components: deciding which
half of `Kim Jiwoon` is the surname is a guess, and a guess written into an authority field stops
looking like one. An ordinary card obeys none of this — it is entirely its owner's, and saving
`앨리스 - 디자인팀` for someone whose Actor says otherwise is right, not out of step.

**Serving is gated on the audience, not just on the profile being public.** The route this replaced
published a name and a kind; this document carries whatever is on somebody's card, which is
telephone numbers and home addresses. Sharing defaults to `off`, so a card is served only once its
Actor has said `public`. Every refusal is the same 404 — answering differently would turn the route
into a way to ask who somebody keeps in their address book.

**A romanisation and a foreign name are different facts.** `ko-Latn` is how `김지운` is written in
Latin script; `en` is the name somebody uses in English, which may be `Trump` and have nothing to do
with the Korean one. Collapsing them loses which is which, so Contacts keeps both as separate
localizations, each with its own components and its own reading order. Nothing ever derives one from
the other, and nothing splits a localized `full` into components — that would be this site deciding
which half of `Jiwoon Kim` is the surname.

**Contacts keeps name representations; Actors keeps what to show per locale.** They answer different
questions — *what writing of this name is this* versus *what does a viewer in this locale see* — so
an Actor's `nameMap["en-US"]` is a choice among the available representations, not a copy of one.
The same person may show `Trump` in `en-US` and `Jiwoon Kim` in `en-GB`, and one romanisation may
serve four locales.

Each `nameMap` slot is therefore a binding or a custom string. A bound slot names the representation
it follows, so correcting `Jiwoon Kim` to `Ji-woon Kim` reaches every locale that follows it; a
custom slot is typed once and no upstream edit touches it. The binding is local editing metadata and
never ships — ActivityStreams receives resolved strings only.

**Per-property visibility will live outside the Card.** JSContact has nowhere inside an entry to
record who may read it, and sync bookkeeping must never ship to whoever asked for a vCard. When it
comes, it is a table keyed by the same JSON pointers as provenance — `emails/work` answering both
*where did this come from* and *who may see it*. Not built in B1: the projection semantics have to
be real first.

## References

- [RFC 9982: JSContact Version 2.0](https://www.rfc-editor.org/rfc/rfc9982.html)
- [RFC 9610: JMAP for Contacts](https://www.rfc-editor.org/rfc/rfc9610.html)
- [Google People API overview](https://developers.google.com/people)
- [Google contact CRUD and sync guide](https://developers.google.com/people/v1/contacts)
- [people.connections.list reference](https://developers.google.com/people/api/rest/v1/people.connections/list)
- [Google Person resource](https://developers.google.com/people/api/rest/v1/people)
