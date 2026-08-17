# Actor JSContact Profile v1

A person's name, said once and serialized twice. Plan only; nothing here is built yet
beyond what the survey below records as already present.

- **Owner**: Axismundi Actors
- **Standards**: [RFC 9553](https://www.rfc-editor.org/rfc/rfc9553.html) (JSContact),
  [RFC 9555](https://www.rfc-editor.org/rfc/rfc9555.html) (JSContact ↔ vCard),
  [RFC 6350](https://www.rfc-editor.org/rfc/rfc6350.html) (vCard 4.0),
  [RFC 9554](https://www.rfc-editor.org/rfc/rfc9554.html) (vCard extensions: phonetics, social profiles),
  [RFC 9982](https://www.rfc-editor.org/rfc/rfc9982.html)
- **Precedes**: GeoData Place, which contributes `kind: location` Cards through the same filter

---

## 1. What is actually there today

Measured against the code, not against memory. Three findings change the shape of the slice.

| Thing | State |
|---|---|
| `wp_ax_actor_person_names` | Exists (Actors DB v16): five components, `display_order`, `display_name`, per language |
| `axismundi_actors_jscontact_card()` | Assembles `@type`/`version`/`kind`/`uid`/`language`/`name`/`localizations` and fires a contributor filter |
| `/@handle.jscontact` route | Serves the Card, gated on `axismundi_actors_is_public_profile()` |
| Calendar contribution | `axismundi_cal_jscontact_calendars()` on the filter — the one existing contributor |
| **Editor for `person_names`** | **Does not exist.** No admin form, no REST write path. Nothing outside tests has ever written a row |
| **`axismundi_actors_person_display_name()`** | **Dead code.** Nothing calls it |
| **ActivityStreams `name` / `nameMap`** | Built from `wp_ax_actor_texts`, a *different* per-language store |

The third is the one that matters. There are two per-language name stores, and they feed
different representations:

```
wp_ax_actor_texts        name per language   →  ActivityStreams  name / nameMap
wp_ax_actor_person_names components + order  →  JSContact        name.full / components
```

Today they never disagree, because nothing writes the second one. The moment somebody fills
in a structured name, the Card would say `김지운` while the Actor document says whatever is in
the text store — the same person, two names, from one site. So v1 is not "adjust the mapper".
It is: **build the editor, and make one source feed both.**

## 2. The boundary this slice exists to draw

Three identities meet on `/wp-admin/profile.php` and must not be collapsed.

```
WordPress User          Username (login), First/Last/Nickname, "Display name publicly as"
                        One per account. WordPress author display. Not federated.

Actor handle            @thaumiel999 — part of the federated address, locked once published.
                        Not a name. Never derived from the WordPress username.

Actor name              Structured, per language, with a stated display rule.
                        One User may manage several Actors, each with its own.
```

Rules:

- **No standing sync.** WordPress `First Name`/`Last Name` may seed a Person Actor's components
  **once**, when the Actor is created or while its name has never been edited. After that,
  nothing syncs in either direction: changing an author display name must not silently change a
  federated name or a published Card, and changing an Actor's name must not touch the login
  account. If a copy is wanted later it is an explicit one-shot command
  (`Copy current WordPress profile name`), never a hook.
- **The handle is not in this model.** It has its own field, its own lock, and its own rules.
  `Display name publicly as` is a WordPress author-archive rule and is not the public-name rule
  for ActivityPub or JSContact.
- **Remote Actors get none of this.** No local UUID, no components, no pronunciation. Whatever
  they published, or nothing.

## 3. The model

```
Person Actor
├─ name parts (per language)
│  ├─ family_name
│  ├─ given_name
│  ├─ additional_name
│  ├─ honorific_prefix
│  └─ honorific_suffix
│
├─ display rule (per language)
│  ├─ order: family-given | given-family | custom
│  └─ display_name          (authoritative when set, or when order is `custom`)
│
├─ pronunciation (per language)          ← new
│  ├─ phonetic per component
│  └─ phonetic_system / phonetic_script  (required when any phonetic value is set)
│
└─ other names                            ← new table
   └─ kind: nickname | former | birth | maiden | alternate_spelling | other
```

Order is a property of the person, not of the language — `김지운`, `Jiwoon Kim` and `Kim Jiwoon`
are all legitimate for the same person — so it is stored, never inferred from the tag. A name
with no parts is a name; `custom` says so, and nothing splits a display name back into pieces.

**Localization vs. other names.** The same name written in another script is a `localizations`
entry, not another name. A nickname is another name. Conflating them is how a directory ends up
listing somebody twice.

**Pronunciation must carry its system.** A phonetic value with no `phoneticSystem` or
`phoneticScript` is a string nobody can read correctly — `jee-WOON` is not IPA and should not be
served as though it were. Storing a phonetic without a system is refused.

## 4. Serialization

One source, three vocabularies, no third copy. vCard is not built in v1, but its column is
here because it is the reason the model has to be right now rather than later — vCard can carry
every one of these facts, so anything the model cannot express is a fact that will be missing
from all three documents forever.

| Source | ActivityStreams | JSContact | vCard 4.0 |
|---|---|---|---|
| assembled name, requested language | `name` | `name.full` | `FN;ALTID=n;LANGUAGE=…` |
| assembled name, other languages | `nameMap` | `localizations[tag].name.full` | further `FN`/`N` with the **same `ALTID`**, different `LANGUAGE` |
| components + order | — | `name.components`, `name.isOrdered: true` | `N;ALTID=n;LANGUAGE=…` |
| phonetic values | — | component `phonetic`, `name.phoneticSystem`/`phoneticScript` | `PHONETIC` / `SCRIPT` parameters (RFC 9554) |
| `kind: nickname` rows | — | `nicknames` | `NICKNAME` |
| other-name kinds | — | **nothing in v1** | **nothing in v1** |
| profile fields | `attachment[]` PropertyValue + `rel=me` | `links` (label + URI only) | `URL` |
| canonical Actor URI | `id` | `onlineServices` | `SOCIALPROFILE` (RFC 9554) |
| profile summary | `summary` (safe HTML) | `notes` (plain text) | `NOTE` (plain text) |
| preferred contact languages *(step 3)* | — | `preferredLanguages` | `LANG` |

### Language is `ALTID` plus `LANGUAGE`, not four names

vCard 4.0 is UTF-8 throughout, and says that several representations of the same logical
property are grouped by a shared `ALTID` and distinguished by `LANGUAGE`:

```
FN;ALTID=1;LANGUAGE=ko-KR:김지운
N;ALTID=1;LANGUAGE=ko-KR:김;지운;;;
FN;ALTID=2;LANGUAGE=en:Jiwoon Kim
N;ALTID=2;LANGUAGE=en:Kim;Jiwoon;;;
```

with one `ALTID` per name *form* — the Korean `FN` and the Korean `N` are the same form and
share an id, so a reader knows these are two writings of one person and not four names. This is
the direct counterpart of JSContact `localizations`, and RFC 9555 maps the two.

Note the component order in `N` is fixed by the format (family; given; additional; prefix;
suffix) regardless of how the name is *read*. Reading order lives in `FN` and, for JSContact, in
`isOrdered`. So `display_order` must never be allowed to permute the `N` value — that is a way
to produce a vCard that every address book will read backwards.

### The profile summary is a note; a note about somebody is not

The Actor's summary is a public self-description, and that is what JSContact `notes` and vCard
`NOTE` are for. One source — the localized Actor summary — and three renderings: safe HTML in
ActivityStreams, normalized plain text in both contact formats, with paragraph structure kept and
markup dropped. Per language it is `NOTE;ALTID=…;LANGUAGE=…` in vCard and `localizations` in
JSContact, exactly as the name is.

**The boundary to hold:** if an Address Book is ever built, "what I wrote about this contact" is a
viewer-private fact. It must not share this store and must not share this note's id. Two different
people's writing under one identifier, one of them published, is a leak that looks like a merge.

### Three meanings of "language", kept apart

| Question | Field | JSContact | vCard |
|---|---|---|---|
| What language is this profile written in? | Actor default language | `language` | `LANGUAGE=` on the properties |
| How is this same fact written in Korean? | localized name / summary | `localizations` | `ALTID` + `LANGUAGE` |
| What language should somebody contact them in? | preferred languages | `preferredLanguages` | `LANG` |

The first two are about the document. The third is a contact policy and belongs with the typed
contact facts in step 3, not with the name.

**Consequence for serializers:** choosing which translation to put in `NOTE` or `FN` reads the
Actor's default language and `localizations` — never `preferredLanguages`. Someone who prefers
being *contacted* in English has not asked for their Korean name to be replaced.

### `UID` — one identifier, both documents

**Decision: JSContact `uid` and vCard `UID` carry the same value, `urn:uuid:<local uuid>`.**

RFC 9555 maps the two directly. Giving vCard the canonical Actor URI instead would mean a
vCard → JSContact → vCard round trip does not return the same identifier, and an address book
holding both representations of one person would see two identities where there is one. The
identifier's job is to be stable and to be the same everywhere; the Actor URI's job is to be
resolvable, which is a different job.

The Actor URI is not lost — it travels as `SOCIALPROFILE` (vCard) and `onlineServices`
(JSContact), which is where a fediverse address belongs and where a client can act on it.

The existing rule survives unchanged: a `uid` is minted only for identities this site owns. A
remote Actor carries whatever it published, or no `UID` at all, in both formats. The exact
property and parameter names in the RFC 9554/9555 columns are to be confirmed against the RFC
text at implementation time; the decision recorded here is the mapping, not the spelling.

Two deliberate omissions:

- **`rel=me` verification does not travel.** It is an HTML link relation this site verified; it is
  not a JSContact concept. The Card carries the label and the URI. Publishing our verification
  state in a standard-looking field would claim an interoperable meaning that does not exist.
- **Former, birth and maiden names project nowhere.** They are stored because people want them
  recorded and searchable locally; JSContact has no unambiguous home for them, and inventing one
  puts a previous name of a real person into a public document. Only `nickname` has a standard
  landing place, so only `nickname` goes out.

For non-Person Actors nothing changes: an Organization has a name and no components, and the
Card keeps falling back to the display name.

## 5. Out of scope for v1

Emails, phone numbers, addresses, birthdays, organisation/title, timezone.

Addresses in particular wait for GeoData Place: an Actor should reference a Place rather than
hold a second copy of one, and deciding that before Place exists would build the copy.

## 6. Slice order

1. **Schema** — pronunciation columns on `person_names`; `wp_ax_actor_alternate_names`
   (identity, language, kind, value, position). Actors DB version up.
2. **Write path** — validation for orders, kinds and the phonetic-system rule; REST for the
   editor; the one-shot seed from the WordPress profile.
3. **One source** — Person Actors resolve `name`/`nameMap` through the structured name, falling
   back to the text store. This is the step that stops the two representations disagreeing, and
   it is what makes `axismundi_actors_person_display_name()` live code.
4. **JSContact** — components already map; add phonetics, `nicknames`, and `links` from the
   existing profile fields.
5. **UI** — the Actor Profile screen, in this order:
   ```
   Identity      Display name · Localized structured names · Other names ·
                 Pronunciation · Handle (read-only, permanent federated address)
   Profile       Bio · avatar · header · Links and rel=me verification
   Representations   ActivityPub Actor · JSContact · vCard (later)
   ```
6. **Audits** — extend `audit-jscontact.php`, add one for the name model.
7. **vCard** — a second serializer beside JSContact, off the same facts, once they are settled.
   Not in v1. Its decisions are locked above so that v1 cannot make it impossible.

Steps 1–3 are the slice; 4–5 are what makes it visible; step 3 must not ship before step 2, or
Person Actors resolve their name through an empty table.

## 7. What the audits must pin

- Korean and English forms of one name come out as one Card with `localizations`, not two Cards.
- `isOrdered` is true and the components are in the stored order — a consumer must not reassemble
  a Korean name as though it were English.
- A phonetic value without a system or script is refused at write time, judged on the merged row
  rather than on what one screen happened to send.
- Removing the last phonetic value removes its notation with it; a notation with nothing to
  pronounce is never stored. Clearing the notation out from under a stored pronunciation is
  refused rather than normalized away, since normalizing there would delete what somebody wrote.
- A translation is chosen by the Actor's default language and `localizations`, never by
  `preferredLanguages`.
- The published profile summary and any future private note about a contact share neither a store
  nor a note id.
- `nicknames` carries nicknames only; former, birth and maiden names appear nowhere in the Card.
- A `rel=me` link appears in `links` with no verification claim attached.
- The ActivityStreams `name` and the JSContact `name.full` agree, for every language, for the
  same Actor.
- A remote Actor gets no local UUID, no components and no pronunciation.
- Editing the WordPress user profile changes no Actor name; editing an Actor name changes no
  WordPress user profile.
- Place can contribute a `kind: location` Card through the same filter without Actors knowing
  what a Place is.

And, when vCard lands:

- Every name form's `FN` and `N` share one `ALTID` and differ only by `LANGUAGE` — two writings
  of one person, never four names.
- `N` keeps the format's fixed component order whatever `display_order` says, so no address book
  reads a Korean name backwards.
- `UID` in the vCard equals `uid` in the Card, byte for byte, and a round trip through either
  format returns the same identifier.
