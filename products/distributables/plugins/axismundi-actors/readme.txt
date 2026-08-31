=== Axismundi Actors ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, identity, actor, federation

Identity registry for Axismundi: one immutable actor URI and one profile hub per identity.

== Description ==

Axismundi Actors gives every identity this site knows about **one stable identity
record** and **one human profile hub** (`/@handle/`), without collapsing that identity
into the WordPress user account and without owning the content it points at.

Each domain plugin keeps its own storage and screens; Actors holds identity and wires
each archive in as a **projection** (Posts, Media, Notes, ...) under one actor. The
identity URI (`/actors/{uuid}`, with `/?ax_actor={uuid}` as the plain fallback) is
derived from an immutable UUID. The `/@handle/` alias is registered once when the Actor
is activated and does not follow later WordPress username changes, so an address that
has been given out keeps working.

= What this version does =

* An identity record and profile hub for each **local person**, built from their
  WordPress user account without becoming that account.
* **Managed actors** -- a Group, Organization or Service that belongs to no single user
  and is administered by the people listed as its managers.
* An **acting Actor** switch in the admin bar, so somebody who manages more than one
  identity can choose which one they are working as. It is not an account switch: no
  password is shared and the WordPress user does not change.
* **WebFinger** and **NodeInfo** endpoints, so other servers can find the identities
  this site publishes.
* A **cache of remote actors** this site has been asked about, with cached copies of
  their avatar and header images, and their follower/following counts where they
  publish them.
* Profile links with **`rel="me"` verification**, checked against the page the person
  entered.
* A **JSContact Card** at `/@handle.jscontact` for a local public Actor.

= What this version does not do =

Nothing here sends anything to anybody. There is no activity ledger, no likes, no
inbox or outbox processing, no follow, no HTTP signatures, and no delivery -- those
belong to other plugins in the Axismundi suite. The site's own Instance Actor is seeded
in a disabled state and has no screen, because what it should be has not been designed
yet.

= Data, and what happens when a user is deleted =

Deleting a WordPress user does not delete the identity it belonged to. The identity is
**tombstoned** instead: the record stays, marked as gone, and its URI keeps answering as
gone rather than as never having existed. That is deliberate. An address that has been
published to other servers should be able to say "this person is no longer here"; an
address that simply disappears tells a reader nothing, and the same UUID could later be
handed to somebody else.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it through
   **Plugins > Add New**.
2. Activate it through the **Plugins** screen.
3. Go to **Users > Actor Profile** and activate an Actor for your account. This is where
   the handle is chosen; it is fixed once set.
4. Optionally, go to **Users > Managed actors** to create a Group, Organization or
   Service that is not tied to one account.

Activation creates this plugin's own database tables. Nothing is contacted on the
internet during installation.

== External services ==

This plugin contacts other websites, and only ever reads from them -- every request is a
`GET`, and nothing about your site's content or your users is sent as data. As with any
outgoing HTTP request, the server being contacted receives **your site's IP address**,
and a `User-Agent` header naming this plugin, its version, and **your site's home URL**,
which is how that server can tell who asked. The address of the site being contacted is
always one that somebody at your site entered or that a request to your site named.

* **Discovering somebody at another site.** When a remote address (`@name@example.com`)
  or a remote profile is looked up, this plugin reads that server's WebFinger document
  (`/.well-known/webfinger`), its actor document, and its NodeInfo document. What is
  stored locally is a copy of that public actor document: handle, display name, summary,
  profile URL, and the addresses on it.
* **Avatar and header images.** Images named by a cached remote actor are downloaded and
  stored in this site's uploads directory, so that showing them does not send your
  visitors' browsers to another server. This happens in the background, on a scheduled
  task, after the actor has been cached.
* **Follower and following counts.** Where a cached remote actor publishes its follower
  or following collection, the collection is read for its `totalItems` number, hourly on
  a scheduled task. Nothing but the number is kept.
* **Verifying a profile link.** When somebody adds a link to their own profile and asks
  for it to be verified, the page at that address is fetched once and checked for a
  `rel="me"` link back to their profile. Only that page is read, and only when asked.

Every one of these requests goes through `wp_safe_remote_get()`, is limited in size, and
is refused for any address inside your own network. Which sites are contacted therefore
depends entirely on which remote identities are looked up at your site; this plugin has
no service of its own, and sends nothing to its author. Each server contacted is somebody
else's, run under its own terms of service and privacy policy.

Sending anything outward -- inboxes, outboxes, activity delivery, HTTP signatures -- is
not done here. Where another plugin performs that federation, it discloses it itself; this
section covers only what this plugin's own code requests.

== Changelog ==

= 0.1.0 =
* First release.
* Identity records and profile hubs (`/@handle/`) for local people, built from the
  WordPress user account without becoming it, plus an immutable identity URI at
  `/actors/{uuid}` that survives a username change.
* Managed actors -- a Group, Organization or Service belonging to no single user and
  administered by the people listed as its managers.
* An acting Actor switch in the admin bar, checked again on every read so that a
  revoked manager stops publishing as that identity immediately.
* WebFinger and NodeInfo endpoints, and a JSContact Card at `/@handle.jscontact` for a
  local public Actor.
* Bounded discovery and caching of remote actors, with local copies of their avatar and
  header images and their published follower and following counts.
* Profile fields with `rel="me"` link verification.
* Deleting a WordPress user tombstones its identity rather than removing it, so an
  address that has been published answers as gone instead of as never having existed.
