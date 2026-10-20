=== Axismundi Object Projections ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.4
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

**This plugin needs Axismundi Actors.** It resolves the identities an object names -- its
author, the accounts it mentions, the Actor documents it serves -- and without that registry
there is nobody for any of them to refer to.

**Axismundi Activities is optional, and adds four things.** Without it a published post is
public and is projected as such, which is this plugin's own default. With it, the four
authored visibility levels decide who a post is addressed to, an Actor's outbox and follow
collections have contents rather than being empty, an object states who may quote it, and an
object says who published it. Nothing here breaks when it is absent; less is said.

That last one is worth stating plainly: who an object is attributed to is decided by whatever
recorded its publication, not derived here from the WordPress author. An object nobody has
published has no `attributedTo` -- it is readable, it simply was not published by anyone.

= What this version does =

* **Content negotiation on the URL a post already has.** Ask for
  `application/activity+json` and the same address answers with JSON-LD instead of HTML.
* A **Core Post to Article transformer**, and an optional adapter for Axismundi Media
  Library attachments.
* **A post's own media**, listed as attachments so a reader can fetch what the body shows:
  the media blocks the author placed. Featured media is represented separately as `image`,
  and is an attachment only when it also appears in the body. Only media this site holds --
  a hotlinked image is left in the text, because its type and size cannot be stated.
* **Collections**: an Actor's outbox, followers and following, a shared media folder at a
  stable UUID route, and a replies collection. An Actor document always names them; without
  an Activity ledger they answer empty rather than 404.
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

Remote media is **never downloaded by this plugin**. A cached object's attachments are
described -- type, size, the text the author wrote about them -- and not fetched by the server.
Public media can be hotlinked when a visitor opens a rendered object, so the remote media
host receives that visitor's network request; the renderer omits the referrer. Where a
cached object is shown locally at all, it is shown `noindex`, only for objects that were
addressed publicly, and only as a courtesy view beside the remote original.

== Installation ==

1. Install and activate **Axismundi Actors** first. This plugin projects identities from
   that registry and does very little without it.
2. Upload this plugin folder to `/wp-content/plugins/`, or install it through
   **Plugins > Add New**, then activate it.
3. Nothing else is required. A published post is projected straight away; ask its URL for
   `application/activity+json` to see the result. It carries no `attributedTo` until
   something records who published it. Add **Axismundi Activities** for that, and for the
   four authored visibility levels and an Actor outbox with activities in it, and the
   official **ActivityPub** plugin with **Axismundi ActivityPub Bridge** to send and
   receive.
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

* **Only if you switch it on.** Under **Tools > Remote Objects** there is a setting called
  *Background acquisition*, and it is **off** unless an administrator turns it on. While it
  is off this site contacts a remote server only when you ask it to on that screen.
* With it on: when a publicly addressed `Announce` reaches this site's inbox carrying only
  the URI of the object it announces, that one address is queued for a single background
  fetch, so that the announcement can be shown as something rather than as a link. The
  object's author is looked up the same way if this site does not already know them.
* Either way the request tells the remote host that this site exists, including its address
  and its IP, which is why the decision is the site owner's and not a stranger's.
* This is deliberately bounded. It never runs while a page is being rendered, it does not
  fan out to the mentions or the audience named in the document, an address already known
  is not fetched again, and only `https` addresses are considered. Turning the setting off
  also clears anything already queued.

Which servers are contacted therefore depends on which addresses your administrators enter
and which sites send things to your inbox. This plugin has no service of its own and sends
nothing to its author. Each server contacted is somebody else's, run under its own terms of
service and privacy policy.

== Changelog ==

= 0.1.4 =
* Include the current full attachment file alongside derivative Links on standalone
  Image objects, with source width and height on the Image itself.
* Keep embedded Article and Note attachment limits unchanged. Do not expose the
  separate unscaled original_image backup or fetch binaries during projection.
* Deduplicate media representations by URL and dimensions, preserving visibility gates.

Earlier releases are recorded in changelog.txt.
