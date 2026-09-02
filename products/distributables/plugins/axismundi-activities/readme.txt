=== Axismundi Activities ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, activitystreams, federation, social

Records ActivityStreams activities and derives social relationship state, without owning network transport or delivery.

== Description ==

Axismundi Activities is the **ledger**: an append-only, URI-keyed record of things that
happened -- somebody followed somebody, liked something, announced something, replied,
withdrew one of those -- and the relationship state derived from reading it back.

It is deliberately not the thing that talks to other servers. There is no HTTP inbox here,
no outbox transport, no signatures, and no delivery queue. Those are somebody else's job,
which is what lets this one be a plain, auditable record: an Activity is written once and
never edited, and undoing something is a new Activity rather than a deletion.

= What it needs =

* **Axismundi Actors** is required. Every Activity names an actor by URI, and that registry
  is the authority for those URIs.
* **Axismundi Object Projections** is required in practice. Anything an Activity is *about*
  -- a post, an object, a cached remote document -- is identified and represented by that
  plugin, so without it a Follow between two actors would still be recorded and almost
  nothing else would.
* Axismundi Emoji and Axismundi Dialogs are optional. Emoji reactions and the anonymous
  remote-follow dialog use them where they are present.

= What this version does =

* An **immutable, URI-keyed Activity ledger** with a read-only administrator log.
* **Follow and Block** relation state, for local actors and for cached remote ones, with
  the accepted inverse edge tracked separately from the outgoing one.
* **Like, Dislike, emoji reactions, Announce, Reply and votes**, each with its Undo, and
  each keyed by the URI of the thing it is about rather than by a local row id.
* **FEP-044f QuoteRequest** decisions, so a quote of somebody's post is something they
  agreed to rather than something that happened to them.
* **Object lifecycle recording**: one Create when a projectable post is first published,
  and a Delete from its stable identity when it goes.
* **Feed blocks** -- feed, loop, item template, tabs, filters, pagination, a density
  switch -- plus follow, interaction and reaction-bar blocks.
* A **public-safe outbox query** for representation plugins: the stored payload stays
  lossless while blind recipients and non-public Activities are kept out of anything
  public.

= What this version does not do =

No inbox, no outbox transport, no HTTP signatures, no delivery queue, and no scheduled
tasks of any kind. It also does not own notifications or read state -- who gets told about
an Activity, and whether they have seen it, belongs elsewhere. Uploading media stays
deliberately silent: putting a file in the library is not an announcement.

= What is kept, and for how long =

The ledger is append-only. An Undo is recorded as its own Activity pointing at the one it
withdraws; nothing is rewritten, because a record that can be edited afterwards is not
evidence of anything. State like "does A follow B" is read back from that history rather
than stored as a fact that could drift from it.

An Activity therefore outlives the actor it names. When an Actor is tombstoned, it stops
being something anybody can follow or react to, and the record that somebody once did stays
where it is -- deleting an identity does not un-happen what it did.

== Installation ==

1. Install and activate **Axismundi Actors**, then **Axismundi Object Projections**. This
   plugin records activity about the objects that one projects.
2. Upload this plugin folder to `/wp-content/plugins/`, or install it through
   **Plugins > Add New**, then activate it.
3. Place the follow, interaction, reaction or feed blocks where you want them. There is
   nothing to configure first.
4. The ledger is readable, read-only, from the administrator log.

Activation creates this plugin's own database tables. Nothing is contacted on the internet
during installation, and no scheduled task is added.

== External services ==

**This plugin makes no network request of its own.** There is no HTTP client in it at all:
no inbox to receive from, no outbox to send from, nothing signed, nothing delivered. Where
Axismundi federates, it is the ActivityPub transport plugin that does it, and that plugin
discloses what it sends.

There is exactly one place where using this plugin causes a request to leave your site, and
it is worth naming because a visitor rather than an administrator sets it off:

* **Following from another server.** Somebody who is not a member of your site can press
  Follow on a profile here and type their own Fediverse handle (`@you@example.com`). To
  send them back to their own server to finish the follow, this asks **Axismundi Actors** to
  look that handle up -- a WebFinger request to the host in the handle they typed, made by
  that plugin under its own disclosure. What comes back is the address of their server's
  follow page, which they are then redirected to. The handle is not stored and no account is
  created here. To keep the endpoint from being used to make your site probe arbitrary
  hosts, attempts are limited to ten a minute per visitor address; that address is used only
  as the key of a one-minute counter and is not otherwise recorded.

Nothing else here reaches outside this site.

== Changelog ==

= 0.1.0 =
* First release.
* An immutable, URI-keyed Activity ledger with a read-only administrator log, and social
  relationship state derived by reading it rather than stored alongside it.
* Follow and Block for local and cached remote actors, tracking the accepted inverse edge
  apart from the outgoing one.
* Like, Dislike, emoji reactions, Announce, Reply and votes, each with its Undo recorded as
  a further Activity rather than as a deletion.
* FEP-044f QuoteRequest decisions.
* One Create recorded when a projectable post is first published, and a Delete from its
  stable identity when it goes.
* Feed blocks -- feed, loop, item template, tabs, filters, pagination, density switch --
  and follow, interaction and reaction-bar blocks.
* A public-safe outbox query for representation plugins: the stored payload stays lossless
  while blind recipients and non-public Activities stay out of public projections.
* Anonymous remote follow, which sends a visitor to their own server to finish rather than
  asking them for an account here.
