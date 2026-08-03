=== Axismundi Forum ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.9.27
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: forum, community, activitypub, group, federation

Federated communities for Axismundi, owned directly by managed Group Actors.

== Description ==

Axismundi Forum adds discussion capability to a local **managed Group Actor**. The
Group is the community: its Actor profile is the one public page, and Axismundi
Actors remains the owner of its identity, profile, handle, lifecycle, and manager
delegation. Forum owns only the Group-keyed settings and projections needed for
discussion.

Every public managed Group is a community. Managers choose its Topic posting and
membership policy on the Group's Managed Groups administration screen. An admitted `ax_topic`
projects as an ActivityStreams `Article` addressed to the Group; the Forum entry
table indexes Topics without duplicating their bodies. Topics use a resolvable
per-thread `context`; replies are Notes with `inReplyTo` and inherit that context.

There is no `ax_forum` post type, `/forum/` page, or Group-to-Forum binding. A
Group's default Forum policy is available without a settings row; a row is created
only when a manager changes that policy. Forum never changes or deletes its Group Actor.

Not in this plugin: identity, handles, Group lifecycle, or manager delegation
(Axismundi Actors); the activity ledger and social relations (Axismundi Activities);
object rendering (Axismundi Object Projections); Note replies (Axismundi Note).

== Changelog ==

= 0.9.25 =
* Refreshing a cached remote reply now replays its original verified inbound
  Create through the Group admission gate, so replies cached before version
  0.9.24 can be accepted without inventing a second submission.

= 0.9.24 =
* A verified public remote `Note` reply from an accepted community member is
  now accepted through the local Group Actor's Announce ledger and appears in
  the community Comments collection. Addressing alone remains insufficient:
  replies from non-members are cached but never republished by the Group.

= 0.9.23 =
* Show the community Group card beside a cached Object document that belongs to
  that Group, even though the document has no Actor-profile route context.

= 0.9.22 =
* Adds a moderator roster to the Group profile: the complete, unpaginated set of
  people who run a community. It is its own surface rather than a section of the
  subscriber list because a Group's manager has no reason to have followed the
  community they run -- sorting moderators to the top of that list would have
  silently omitted exactly the people a reader was looking for, and a paginated
  list only shows them on page one anyway.
* Marks moderators in a community's subscriber list. The role is answered by
  this plugin because it owns the permission; the list never infers it from the
  Follow relation, and ordinary subscribers are not labelled members.

= 0.9.21 =
* The community surface on a Person profile is now a browsable archive rather
  than a feed: numbered pages, a list of titles with a short preview, and links
  that keep working when someone returns to them. A cursor cannot express
  "page 3", and an archive is a thing readers come back to.
* Caches the selection for each page briefly, never the rendered rows. What a
  reader may see, and any control on a row, is theirs alone; only the list of
  object URIs is shared. A generation counter per Actor invalidates every page
  at once when that person posts, edits, deletes, or undoes.
* Remote Group submissions keep including Public. A remote server's policy is
  the authoritative one, there is no trustworthy visibility field to read, and
  guessing would have peers reject the submission outright; submitting to a
  remote community is treated as accepting public distribution.

= 0.9.20 =
* A Topic in a member-distributed community is now withheld from readers who
  are not entitled to it, on the HTML permalink as well as in the
  ActivityStreams projection. Previously only the federated representation was
  protected, so anyone with the URL could read the post in a browser.
* The refusal is a 404 rather than a notice: telling a stranger that a post
  exists but is for members still discloses that it exists, who wrote it, and
  what it is about, which is the disclosure a closed community is avoiding.
* Restricted Topics are also kept out of search results and archives for those
  readers, since a title in a listing discloses the same thing.
* The author of a Topic can always read it. Membership can be revoked and a
  community can change its distribution scope afterwards, and neither should
  take someone's own post away from them.

= 0.9.19 =
* The community vote control renders as presentation on a surface that owns its
  own clicks, such as a profile timeline whose cards are appended after load.
  On an Object's own page it stays an interactive block. The feed variant omits
  the interactive directives rather than guarding them at runtime: markup that
  is not there cannot fire twice.

= 0.9.18 =
* Adds a community surface to a Person profile. Topics and replies submitted to
  a community were hidden from the personal timeline, which was right, but left
  the contribution history with nowhere to be found; the same two predicates
  that hide them are now read in the opposite direction to fill
  `?view=community`, with Overview, Topics, and Replies.
* A Group profile is given no community tab, because a Group profile already is
  its community.

= 0.9.17 =
* Keeps locally authored Topics submitted to a remote Group in that Group's
  immutable context, so Like and Dislike activities are addressed to the
  community inbox instead of incorrectly falling back to the author's followers.

= 0.9.16 =
* Redistributes locally cast votes through their local community Group, just as
  inbound votes are redistributed. A local vote no longer changes only this
  site's score while leaving community followers stale.
* Keeps a submitted local reply in its original community context by reading
  immutable lifecycle evidence, rather than changing its template or vote
  recipient when the author's later membership changes.
* Counts one deterministic current vote per Actor in the Forum score when a
  peer has recorded both Like and Dislike for the same object.

= 0.9.15 =
* Routes an Object that belongs to a community through a community template,
  with its community card and vote. A reply submitted to a community used to
  render as a plain Note with no sign of where it was posted.
* Recognises our own reply into a *remote* community as a community post. It
  was previously treated as an ordinary Note, which also meant a vote on it was
  addressed only to the author -- which a threadiverse peer does not count.

= 0.9.14 =
* Adds exclusive community voting: an Actor holds at most one vote per object,
  switching sides withdraws the previous vote before recording the new one, and
  pressing the held side again clears it. Votes are addressed to the community
  Group rather than only the author, which is where a threadiverse peer keeps
  the score.
* Adds the Community Vote block and its `axismundi/v1/community-votes`
  endpoint. The control sends the direction a reader asked for rather than a
  verb, so a stale page cannot contradict the server, and it is rendered into
  the Topic template above the replies.

= 0.9.13 =
* Redistributes inbound `Undo(Like)` and `Undo(Dislike)` as Group Announce
  activities, so a community vote withdrawal reaches its followers.

= 0.9.12 =
* Redistributes inbound Like and Dislike activities for Objects whose direct
  Group submission is already proven in the local ledger. The Group, not the
  remote voter, distributes those community interactions to its followers.

= 0.9.11 =
* Places the existing nested reply tree and the collection-backed reply list on
  Topic pages together for comparison. Both read the same public reply graph.

= 0.9.10 =
* Keeps every Group-directed Reply addressed primarily to that Group, carrying
  `Public` in `cc` for threadiverse validation. The Group Announce, not the
  author Reply, is the public community representation.

= 0.9.9 =
* Treat a direct Topic submission as Group-addressed when the Group is in
  either `to` or `cc`, matching Reply handling and keeping future address
  changes out of the author's profile feed and public outbox.

= 0.9.8 =
* Makes a public local-community Reply's embedded Create Lemmy-valid before the
  Group Announce redistributes it: `Public` is primary, with the Group and
  parent author in `cc`. Member-only communities remain Group-addressed.

= 0.9.7 =
* Sends a Reply to a remote Lemmy community with `Public` in `to` and the
  community plus parent author in `cc`, while preserving the Group `audience`
  and keeping the Reply out of the author's profile and public outbox.

= 0.9.6 =
* Recognizes a remote Group named in a parent Lemmy comment's `cc` as the reply
  destination, so a local reply is addressed back to that community instead of
  falling back to an unrelated public reply.

= 0.9.5 =
* Sends a local Note reply to a cached remote Group directly to that Group, with
  the Group as `audience` and public routing required by Lemmy. The remote Group
  remains responsible for its own community Announce; the direct submission does
  not leak into the author's profile feed or public outbox.

= 0.9.4 =
* Makes public local Group Topic submissions compatible with Lemmy's public-object
  validation without placing the direct Person Create or Update in the author's
  profile feed, public outbox, or follower delivery. The Group Announce remains
  the community's public representation.

= 0.9.3 =
* Treats a managed-Group manager as an invariant derived moderator throughout
  the API as well as the Members screen; it cannot acquire or lose a redundant
  explicit moderator row.

= 0.9.2 =
* Labels local managed-Group delegates as `Moderator (manager)` in Members and
  does not offer a redundant explicit moderator Add/Remove transition for them.

= 0.9.1 =
* Addresses federated moderator `Add` and `Remove` activities to `Public` and
  the community, as required by Lemmy's collection-moderation protocol.

= 0.9.0 =
* Gives a Topic its own page. Forum's single-Topic template had lost its loader when the
  `ax_forum` post type was removed, so every Topic silently rendered through the theme's
  ordinary post template — Core comments instead of federated replies, and no sign of the
  community. The template is registered again and now lays out the Topic beside its community.
* Adds the Community Card block: the Group a Topic belongs to, with its identity and Follow
  control. Every value is read from Actors at render time; Forum stores the Group identity
  and nothing about the Group.
* Replies received from other servers now appear on the Topic they answer, through Object
  Projections' new replies block.

= 0.8.6 =
* Keeps discovery reasons in the Community result list rather than copying them
  into the selected Community field.

= 0.8.5 =
* Makes direct submissions to remote threadiverse Groups compatible with Lemmy's
  public-object validation while retaining Group-only transport delivery.

= 0.8.4 =
* Fixes the Community picker on a new Topic: it can search known Group Actors before
  WordPress has assigned the draft a post ID.

= 0.8.3 =
* Adds a per-entry Group distribution ledger, so every effective Topic lifecycle
  activity is retained for later withdrawal.
* Redistributes edits to visible Topics as Group `Announce(Update)` activities.
* Withdrawing a visible Topic undoes every active Group distribution before returning
  the Topic to pending submissions.
* Permanently deleting a previously distributed Topic records its direct Delete to
  the Group and the Group's matching `Announce(Delete)` to community followers.

= 0.7.0 =
* Makes the managed Group Actor the community directly. Removes the `ax_forum` CPT,
  `/forum/` templates, and the binding table; all Forum-owned records are keyed by
  `group_identity_id`.
* Moves Community enablement, posting policy, membership policy, and membership-request
  decisions into the Managed Group administration record. The Topic editor selects a
  manageable Community directly.
* Development schema reset is deliberate: this pre-release shape has no upgrade migration.
  Deleting the plugin runs its uninstall handler and removes only Forum-owned tables; it does
  not delete Group Actors, Topics, or the Activity ledger.

= 0.5.0 =
* Forum roots are ActivityStreams `Article` objects. The Group is the audience and a
  resolvable per-Topic URI is the thread context. Replies inherit that context.
