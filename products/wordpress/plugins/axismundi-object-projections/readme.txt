=== Axismundi Object Projections ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, activitystreams, jsonld, federation

Projects WordPress objects into ActivityStreams JSON-LD through a transformer registry and one renderer.

== Description ==

Axismundi Object Projections turns a WordPress object -- a post, an attachment, an
archive, a folder -- or an Axismundi Actor into an ActivityStreams 2.0 object or
collection, so that the URL it already has can answer with JSON-LD when something asks
for it.

It owns **representation** and nothing else: a transformer registry, the URIs objects and
collections are named by, and the single renderer that writes the JSON. What a thing *is*
stays with the plugin that stores it; this decides how it is described to a reader that
speaks ActivityStreams.

**This plugin needs Axismundi Actors.** Every projected object is attributed to an Actor,
and without that registry there is nobody to attribute anything to.

= What this version does =

* **Content negotiation on the URL a post already has.** Ask for
  `application/activity+json` and the same address answers with JSON-LD instead of HTML.
* A **Core Post to Article transformer**, and an optional adapter for Axismundi Media
  Library attachments.
* **Collections**: a shared media folder at a stable UUID route, a replies collection, and
  the representation of an Actor's outbox where Axismundi Activities supplies one.
* The **relations that make a document readable in context** -- hashtags, mentions, thread
  edges, reply context, and quote context -- kept as their own records rather than parsed
  out of content each time.
* A **remote object repository**: URI-keyed, rebuildable observations of objects this site
  has been told about, for administrators to inspect under **Tools > Remote Objects**.
* An administrator can **probe a remote collection** and its first page without storing the
  collection, following its item URLs, or downloading anything binary.

= What this version does not do =

There is no Activity ledger, no inbox write handling, no Follow/Like/Announce state, no
HTTP signatures, and no delivery. Those belong to Axismundi Activities and to the
ActivityPub transport boundary. Nothing here signs or sends an Activity.

= Alongside the official ActivityPub plugin =

Both plugins negotiate the same canonical URLs, and two answers for one address is worse
than either answer alone. So when the official ActivityPub plugin is active, this plugin's
standalone negotiator **turns itself off** and leaves those URLs to it, while the registry
and renderer stay available. Nothing here overrides or replaces that plugin's object ids.

= What is stored about other people's documents =

An observation of a remote object is a **cache, not a record**: it is keyed by the remote
URI, which stays canonical, and it can be rebuilt by fetching again. Observations expire,
and a scheduled daily task deletes the expired ones along with anything left pointing at
them.

Remote media is **never downloaded and never hotlinked**. A cached object's attachments are
described -- type, size, the text the author wrote about them -- and not fetched. Where a
cached object is shown locally at all, it is shown `noindex`, only for objects that were
addressed publicly, and only as a courtesy view beside the remote original.

== Installation ==

1. Install and activate **Axismundi Actors** first. This plugin projects identities from
   that registry and does very little without it.
2. Upload this plugin folder to `/wp-content/plugins/`, or install it through
   **Plugins > Add New**, then activate it.
3. Nothing else is required. A published post is projected as soon as its author has a
   public Actor; ask its URL for `application/activity+json` to see the result.
4. Remote observations, where there are any, are listed under **Tools > Remote Objects**.

Activation creates this plugin's own database tables. Nothing is contacted on the internet
during installation, and deactivation removes the scheduled tasks it added.

== External services ==

This plugin reads documents from other websites. Every request is a `GET` made through
`wp_safe_remote_get()`: redirects are not followed automatically, the response size is
capped, and any address inside your own network is refused. Nothing about your site's
content or your users is sent as data. As with any outgoing HTTP request the server being
contacted receives **your site's IP address**, and a `User-Agent` header naming this
plugin, its version, and **your site's home URL**.

Requests happen for two reasons, and the second is worth being clear about.

**Because an administrator asked.**

* **Fetching one remote object.** Under **Tools > Remote Objects**, an administrator can
  fetch the address of an ActivityStreams object to inspect it. What is read is that one
  JSON document; what is stored is its metadata -- tags, mentions, audience, attachment
  descriptors, extension properties, and the payload itself, escaped.
* **Probing a remote collection.** An administrator can read a collection and its first
  page from the same host to see what it contains. The collection is not stored, its item
  URLs are not followed, and nothing binary is downloaded.

**Because something arrived here naming an address.**

* When a publicly addressed `Announce` reaches this site's inbox carrying only the URI of
  the object it announces, that one address is queued for a single background fetch, so
  that the announcement can be shown as something rather than as a link. The object's
  author is looked up the same way if this site does not already know them.
* This is deliberately bounded. It never runs while a page is being rendered, it does not
  fan out to the mentions or the audience named in the document, an address already known
  is not fetched again, and only `https` addresses are considered.

Which servers are contacted therefore depends on which addresses your administrators enter
and which sites send things to your inbox. This plugin has no service of its own and sends
nothing to its author. Each server contacted is somebody else's, run under its own terms of
service and privacy policy.

== Changelog ==

= 0.1.0 =
* First release.
* Content negotiation on existing WordPress object URLs, so a post's own address answers
  with ActivityStreams JSON-LD when asked for it, and a transformer registry with a single
  renderer behind it.
* A Core Post to Article transformer, and an optional adapter for Axismundi Media Library
  attachments.
* Collections: shared media folders on a stable UUID route, a replies collection, and the
  representation of an Actor outbox where Axismundi Activities supplies one.
* Hashtags, mentions, thread edges, reply context and quote context stored as their own
  records rather than re-derived from content.
* A URI-keyed repository of remote object observations, with administrator inspection under
  Tools > Remote Objects and a remote collection probe that stores nothing.
* Cached remote media is described and never downloaded or hotlinked; observations expire
  and are purged daily.
* The standalone negotiator disables itself when the official ActivityPub plugin is active,
  so that one URL keeps one answer.
