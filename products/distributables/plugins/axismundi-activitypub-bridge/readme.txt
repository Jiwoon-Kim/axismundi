=== Axismundi ActivityPub Bridge ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: activitypub
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, federation, compatibility, adapter

Connects Axismundi's domain stores to the official ActivityPub plugin's server-to-server transport.

== Description ==

This is the seam, and the only one. Axismundi Actors, Object Projections and Activities each
work without it and none of them talks to another server; this package is what puts them on
the network, by composing with the **official ActivityPub plugin** rather than by
reimplementing what that plugin already does well.

The division is worth stating precisely, because "which plugin sends it" and "which plugin
signs it" have different answers.

= Receiving =

The inbox address advertised for an Axismundi Actor is the **official plugin's own REST
route**. This plugin registers no inbox of its own, and no other public route either.

That matters. The official plugin receives the request, and **the official plugin verifies the
HTTP Signature**, before anything here runs. Only afterwards, on the actions it fires for an
already-verified delivery, does this plugin take over: it unhooks the official domain handlers
so the same Activity is not also stored that plugin's way, and records it in the Axismundi
ledger instead. There is no path by which an unverified request reaches that ledger, because
there is no route here for one to arrive on.

An Activity that arrives twice is acknowledged twice and recorded once. The ledger is keyed by
the Activity's own URI.

= Sending =

Here it is the other way round. **This plugin opens the connection.** When Axismundi
Activities commits an outbound Activity, one row is written to a transport queue owned by this
plugin, and a background worker POSTs the JSON-LD to each recipient inbox. **The official
plugin signs it**: the signing key reference and the key itself go out with the request, and
that plugin's request-signing filter turns them into an HTTP Signature header.

The keys are the official plugin's too. Nothing here generates, stores or copies private key
material; it is read from that plugin's key store into memory for the length of one request,
and the queue holds the payload and the recipients and never a key.

Recipients come from the Activity itself: the actors it is addressed to, the actor a Follow or
Block is about, and -- only when it addresses the public collection or the sender's own
followers -- that sender's accepted followers. Each address is resolved to the inbox of an
Actor this site has already cached, and duplicates collapse, so one Activity reaches one inbox
once.

= What is not guaranteed =

Sending a `Delete` or an `Undo` tells other servers what happened. It cannot make them act on
it. A server holding your post may keep it, may have passed it on already, and may be
unreachable when the Delete goes out. Delivery is attempted on a bounded retry schedule and
then given up on. Federation is a request made to other people's computers, not control over
them.

= What this is not =

Not a client, not an inbox implementation, and not a signature implementation -- those belong
to the official plugin. Not the ledger either: Axismundi Activities stays authoritative, and a
delivery succeeding or failing changes the queue row rather than the record of what happened.

== Installation ==

1. Install and activate the **ActivityPub** plugin, then **Axismundi Actors**, **Axismundi
   Object Projections** and **Axismundi Activities**.
2. Upload this plugin folder to `/wp-content/plugins/`, or install it through
   **Plugins > Add New**, then activate it.
3. Give your Actor a public status in **Users > Actor Profile**. An Actor whose signing key
   cannot yet be published advertises no inbox and sends nothing, deliberately: a remote server
   that fetched it during that window would cache a keyless document and reject every signature
   afterwards.
4. The transport queue is readable, read-only, from this plugin's administration screen.

Activation creates this plugin's own delivery table. Nothing is contacted on the internet
during installation.

== External services ==

This plugin sends your site's public content to other servers on the Fediverse. That is what it
is for, so it is worth being exact about what leaves and when.

* **What is sent.** The ActivityStreams JSON-LD of an Activity your site produced -- publishing,
  editing, deleting, following, liking, announcing, replying -- as an HTTP `POST` to each
  recipient's inbox, signed with your Actor's key so the receiving server can tell it really
  came from you. As with any HTTP request, the receiving server also learns your site's IP
  address.
* **Who receives it.** The servers of the actors the Activity is addressed to, and, for a public
  post, the servers of your accepted followers. Nobody else is contacted. A non-public Activity
  is kept out of delivery by the ledger's own public projection rules.
* **When.** On a background task shortly after the Activity is recorded, never while somebody is
  waiting for a page. Failures are retried on a bounded, widening schedule and then left in a
  dead-letter state an administrator can inspect and retry once.
* **What is stored here.** The queue row keeps the payload that was sent, the inboxes still to
  try, the attempt count, and a short sanitized description of the last failure. It never keeps
  key material.

**There is no Axismundi service.** Nothing is sent to this plugin's author and no central server
is involved. Every server your site talks to is somebody else's, reached because somebody there
follows you or because you addressed them, and each is run under its own terms of service and
privacy policy. What they do with what you send -- how long they keep it, who they show it to,
whether they honour a later Delete -- is theirs to decide, not yours and not this plugin's.

Receiving is described above: the official ActivityPub plugin owns the inbox and the signature
check, and this plugin only handles what that plugin has already verified.

== Changelog ==

= 0.1.0 =
* First release.
* Composes with the official ActivityPub plugin instead of reimplementing it: that plugin owns
  the inbox route and HTTP Signature verification, and this one takes over afterwards to record
  the verified Activity in the Axismundi ledger rather than letting it be stored twice.
* A transport queue of its own for outbound delivery, with an atomic single-worker claim, a
  bounded widening retry schedule, a dead-letter state, and a one-shot administrator retry.
* Outbound requests are signed by the official plugin's filter using keys read from its key
  store in memory; the queue stores payloads and recipients and never key material.
* Recipients are resolved from the Activity's own audience, expanding the public and followers
  collections to accepted follower inboxes only at the transport boundary, deduplicated so one
  Activity reaches one inbox once.
* An Actor whose public key cannot yet be projected advertises no inbox, endpoints, publicKey or
  WebFinger self link, and sends nothing, so that no remote server caches a keyless document.
* Migration of provisional and legacy outbox jobs into the transport queue without deleting
  their source rows.
* Read-only administrator inspection of the transport queue.
