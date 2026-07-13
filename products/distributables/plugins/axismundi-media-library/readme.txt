=== Axismundi Media Library ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.24
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: media, attachments

Promote WordPress attachments to independent, visibility-controlled media objects.

== Description ==

Axismundi Media Library promotes the WordPress attachment into an independent,
publishable media object with its own owner and visibility, organised into virtual
folders, with structured rights (license, attribution, copyright, source) and a
sensitive-content workflow. Saved references (collections) and federation are
planned for later phases.

Activating the plugin changes no existing media. **Core mode** leaves WordPress
attachments untouched. **Independent mode** detaches new uploads (post_parent 0)
and enforces per-item visibility — public, unlisted, or private — across the
attachment page, the REST API (single and collection), and the media picker.
Ownership is the WordPress author (post_author); permission reuses core
capabilities.

The full specification lives in the plugin's docs/ directory (SPEC, SECURITY,
ROUTING, COMPATIBILITY, DATA-MODEL, PHASES). Independent mode provides media
archives at /media/ and /media/author/{nicename}/. Attachment single pages continue to use
the active theme's normal attachment template hierarchy; Axismundi includes a
dedicated attachment.html template.

== Installation ==

1. Upload and activate Axismundi Media Library.
2. Open Settings > Media Library (Axismundi). Activation changes no existing media.
3. Choose Independent mode to enable independent-attachment visibility.
4. Optional: deactivate FileBird, then use Media > Import Folders to analyze and
   import a FileBird CSV export without overwriting existing Axismundi assignments.

== Changelog ==

= 0.0.24 =
* Scope Unfiled to the current user's uploads while keeping All media available
  as the complete capability-filtered library view.
* Add an Upload to folder selector to Media > Add New for both Plupload and the
  browser uploader, using the same ownership checks as modal uploads and moves.
* Cover Attachment Details Location saves with a regression fixture.

= 0.0.23 =
* Enforce that an Attachment can only be assigned to a virtual folder owned by
  its WordPress author, including administrator, upload, edit, and FileBird
  import paths.
* Repair legacy cross-owner, hidden-root, and multi-folder relationships to
  Unfiled in bounded admin batches so affected media reappears in its author
  archive and Unfiled view.

= 0.0.22 =
* Add a FileBird CSV compatibility importer at Media > Import Folders for hosts
  without WP-CLI access. It recreates nested folders in the current user's
  namespace and assigns media through the existing per-attachment permission
  service.
* Parse the CSV once without retaining the uploaded file, then process folders and
  attachment assignments in resumable AJAX batches suitable for constrained
  hosting. Missing or unauthorized media is reported without stopping the job.
* Preserve existing Axismundi assignments, reject malformed/cyclic hierarchies,
  report cross-folder duplicates, and tag imported folders by FileBird source ID
  so rerunning the same export reuses folders instead of duplicating them.

= 0.0.21 =
* Phase 4c (output integration) — sensitive media now gets a front-end
  click-to-reveal blur overlay on the visual core blocks that render an
  attachment: core/image, core/video, and core/post-featured-image. The overlay
  shows the content warning and a Show button. This is a viewer content warning,
  not access control: the file is never altered or withheld and the blur applies
  to everyone including the owner. Assets load only on the front end in
  Independent mode; the editor is untouched. Audio (no visual surface), Open
  Graph preview exclusion, and post-level flagging are intentionally out of scope.

= 0.0.20 =
* Define Location visibility as an output policy for plugin-rendered metadata only.
  Axismundi never rewrites original or derivative files to remove EXIF/GPS data.
* Drop the planned destructive GPS/EXIF stripping phase and clarify the file-level
  privacy boundary in the editor and specification.

= 0.0.19 =
* Add the plugin-owned Media Rights dynamic block with license conditions, creator,
  copyright holder, source, and rich-text/Markdown attribution copying inspired by
  Openverse and the WordPress Photo Directory.
* Append Media Rights once to the main Attachment content without adding a custom
  block dependency to the active theme. Manual placement suppresses the automatic
  copy.

= 0.0.18 =
* Phase 4b (folder default license) — each folder can set a default license
  (Media > Folders). A new upload into that folder is stamped with the default as
  a one-time snapshot: it resolves the nearest ancestor folder that declares one,
  falls back to all-rights-reserved, and NEVER overwrites a license the upload
  already carries. This is not dynamic inheritance — moving an existing item
  between folders never changes its license. Every upload path (media modal, REST,
  direct) shares the one stamp service on `add_attachment`.

= 0.0.17 =
* Phase 4b (resolver) — a single license/rights API. axismundi_media_license_record()
  returns the code, display name, canonical CC/PDM URL, and derived reuse conditions
  (attribution / commercial / derivatives / share-alike / known). The canonical URL
  wins for standard codes so the code and URL can never disagree; all-rights-reserved
  and unknown grant nothing. axismundi_media_attribution_text() prefers the authored
  attribution and otherwise composes a display-only title + creator + license string.
* Clean break: the plugin has no external installs yet, so there is no legacy license
  alias, no `custom` value, and no reuse/download-policy migration — the resolver
  treats any dropped/legacy code as all-rights-reserved. This is the last point to
  finalize the schema cleanly.

= 0.0.16 =
* Drop the duplicate Reuse policy field. Creative Commons permissions and conditions
  derive from License, avoiding two mutable rights values that can drift apart.
* Clarify that Collection Save is a bookmark/reference gated by visibility, not a
  licensed reuse. License checks apply to later Import/Copy, redistribution, and
  transformation operations.
* Align the License field with the Openverse vocabulary — Public Domain Mark, CC0,
  CC BY, CC BY-SA, CC BY-ND, CC BY-NC, CC BY-NC-SA, CC BY-NC-ND, Unknown — plus an
  All-rights-reserved default for a creator's own copyrighted work. The render field
  and its save whitelist now share one source (axismundi_media_license_options()).

= 0.0.15 =
* Drop the never-enforced Download policy field. Core does not restrict downloads and
  this plugin cannot either without Phase 6 controlled delivery, so a delivery-
  affordance hint that claimed to be a control has been removed rather than shipped.

= 0.0.14 =
* Phase 4a — sensitive authority. `_ax_media_sensitive` becomes a derived, read-only
  effective boolean; the authority lives in `_ax_media_sensitive_state` (none /
  self_marked / automated_flagged / moderator_marked / confirmed) with set-by, set-at,
  and a locked flag.
* An owner may self-mark and clear their own mark, but cannot clear a moderator or
  confirmed lock, and can only appeal (not self-clear) an automated flag — closing the
  Phase 2c audit gap. Capabilities moderate_media_sensitivity / override_media_sensitivity
  (editors) and mark_own_media_sensitive (uploaders) map from existing roles.
* Attachment Details shows the state: moderators get a state selector, owners a
  self-mark checkbox while unlocked, and a read-only note when locked. Feeds and Media
  Collections keep reading the effective boolean, so nothing downstream changes.

= 0.0.13 =
* Add the dual-key Used-in relation index (`wp_ax_media_relations`) with local-ID
  source keys, optional canonical URIs, deduplication, occurrence counts, atomic
  per-provider replacement, schema upgrades, and read-filtered reverse lookup.
* Incrementally index featured images, core media blocks, galleries, and ID-based
  gallery/playlist shortcodes while preserving prior data when an integration
  provider reports an error. URL-string reverse matching remains intentionally out
  of scope.
* Add `wp axismundi media relations reindex` for exact dry-runs, single-post
  rebuilds, and confirmed full rebuilds.
* Rename the Attachment Details folder field to Location and add a role-labelled,
  read-filtered Used in list. Saved in remains reserved for Phase 5 collections.
* Add a guarded legacy-parent migration: immutable snapshot, read-only preview,
  explicit detach, and ownership-safe rollback commands. Detach refuses missing or
  conflicting snapshots; rollback never overwrites a newer nonzero parent.
* Show an in-use deletion warning in Attachment Details without exposing source
  titles or URLs the viewer cannot read.
* Add dev-only permission, relation-store, provider, and reindex regression fixtures;
  the distributable ZIP excludes tests and build scripts.

= 0.0.12 =
* Add Atom feeds for three scopes: Home (/?ax_media_feed=home), Author
  (&ax_media_owner=), and Folder (&ax_media_folder=), plus pretty /media/…/feed/atom/
  aliases. One shared, strict-public query service backs all three.
* Feeds emit only public + listed + ungated media; unlisted, private, and
  password-gated items/folders never appear, and a non-public folder feed 404s.
* Entry identity is the stable /?attachment_id={id} across scopes; folder feeds order
  and date entries by when the item was added to the folder, Home/Author by publish
  date. Each archive advertises its feed via a <head> discovery link.
* Folder feeds are ON by default (like a category feed). A public folder can opt out
  via a "Feed" checkbox on Media > Folders (_ax_media_folder_feed_enabled = 0);
  non-public or opted-out folders 404 and drop their discovery link.
* Add a Media RSS (media:content / media:thumbnail) sibling feed at the same scopes.
  Feeds serve an intermediate derivative (medium_large), never the original, and add
  the item's folder as a category (Atom <category> / media:category). Sensitive items
  stay in the feed but are marked (Atom sensitivity category + content-warning
  summary; media:rating + media:description) and are NOT given an auto-rendering
  enclosure or real thumbnail — an attachment-page link carries them instead.

= 0.0.11 =
* Rebuild the Media Library folder sidebar on FileBird's proven layout: it is now a
  flex sibling of #wpbody-content (sticky) instead of an absolutely positioned
  element inside the attachments browser. This removes the content-overlap bug and
  makes the sidebar work in BOTH grid and list view — list mode had no folder tree
  before.
* List view filters via ?ax_media_folder= links + a server-side query filter; grid
  view drives the existing wp.media dropdown; the media modal keeps its dropdown.
* Add a folder-path breadcrumb above the Media Library content (both views), synced
  with the sidebar selection; grid crumbs re-query in place, list crumbs are links.
* Show the folder tree inside the media-picker modal (Select or Upload Media) too,
  so choosing a folder narrows the picker when inserting images/galleries in a post.
* Cache-bust the folder stylesheet/script by filemtime so layout fixes reach
  browsers instead of serving a stale cached copy.

= 0.0.10 =
* Add a FileBird-informed folder tree to the desktop Media Library and media
  modal while retaining the existing folder dropdown on narrow screens.
* Keep the tree synchronized with the core attachment query so folder selection,
  permissions, and upload destination reuse the existing Axismundi services.
* Use the theme's folder, folder_open, and lock icon-font glyphs in public Media
  Collections; default desktop collections to four consistent columns.

= 0.0.9 =
* Separate child folders from the paginated media grid and move optional parent
  navigation into the folder-region header.
* Default folder counts and parent navigation to off, add sensitive-media
  overlays, and share the core Query Pagination styling contract.
* Add the core Breadcrumbs block to media home, author, and folder templates.
* Add Query Loop-style Media Folders, Media Post Template, Media No Results, and
  Media Pagination inner blocks. Existing self-closing collections retain the
  same default template as a server-rendered compatibility fallback.
* Save Attachment Details folder changes from partial media-modal payloads, and
  keep password-protected child folders discoverable without exposing counts.

= 0.0.8 =
* Add the Media Collection block with current archive, user root, and specific
  folder sources; responsive columns; attachment pagination; and display controls.
* Render parent navigation, visible child folders, and direct Attachments in one
  collection without leaking hidden descendant counts.
* Adopt Media Collection in media home, author, and folder templates.

= 0.0.7 =
* Add folder password gates with WordPress password hashes, signed HttpOnly
  folder cookies, nested gate inheritance, and owner/editor bypass.
* Add protected-media challenges for folder and Attachment pages; gated media is
  excluded from public collections and REST singles require authentication.
* Extend Media > Folders and the folder REST API with open/password access.

= 0.0.6 =
* Add Media > Folders for creating, nesting, renaming, deleting, and setting the
  visibility policy of the current user's virtual folders.
* Add Media profile links to the Users screen.
* Add parent and child navigation to folder archives, and keep valid empty media
  profiles and folders as 200 archives instead of generic 404 pages.

= 0.0.5 =
* Phase 2a groundwork: the ax_media_folder virtual-folder taxonomy (hierarchical,
  per-user hidden root so two users can both have a "Travel" folder), single-folder
  enforcement (an attachment is in 0 or 1 folder), and direct/recursive counts.
* Folder service + REST (axismundi-media/v1): create, rename, delete (contents move
  to the root — never deleted), and move. Moving an attachment requires edit_post on
  THAT attachment, not just upload_files.
* Attachment edit panel: assign the item's single folder.
* Folder-aware visibility resolver: explicit inherit support, narrow-only tier
  resolution across the parent chain, and derived effective-rank caches for
  archive, REST collection, and media-modal queries.
* Object/Collection pretty routing: `/media/{type}/{id}/`,
  `/media/author/{owner}/`, and owner-scoped folder archives; plain query
  endpoints remain the always-working base.
* Add the editable media-folder block template and dynamic archive-title block.
* Add the Media Library folder filter (All media, Unfiled, and the current
  user's folder tree). New uploads inherit the folder selected in the picker.

= 0.0.4 =
* Add public media archives with scoped visibility filtering. The base is a plain
  query endpoint that works without pretty permalinks (?ax_media_archive=landing /
  owner&ax_media_owner={USER_ID}); /media/ and /media/{nicename}/ are pretty
  aliases. Owner is queried by user ID (nicename accepted on the alias; the
  Phase 2 pretty route is `/media/author/{nicename}/`).
* Add an optional Media page (Settings > Reading) to use an editable Page as a
  stable media-hub landing.
* Editable plugin block templates: media-home (landing) and media-author (one
  user's media; owner = post_author).
* Add a dynamic media-preview block for attachment Query Loops.
* Add stored-only creator, copyright, license, reuse, download, sensitivity,
  content-warning, and location-visibility metadata controls.
* Keep attachment single-page presentation in the active theme's standard
  attachment template hierarchy instead of duplicating it in the plugin.

= 0.0.3 =
* Ownership is now the WordPress author (post_author) as the single source of
  truth; removed the separate owner meta. Permission reuses core edit_post /
  edit_others_posts.
* Fix: partial attachment saves no longer reset listed/searchable (hidden field
  sentinel).
* Fix: reactivating while Independent mode is on re-acquires the attachment-pages
  option.
* New uploads set post_parent 0 before the INSERT (atomic), via
  wp_insert_attachment_data.
* Media modal now hides other users' unlisted media (matches SECURITY.md).
* Refresh admin and readme copy to reflect that Independent mode changes behaviour.

= 0.0.2 =
* Phase 1a: Independent-mode media visibility (public / unlisted / private) with
  legacy-public fallback, and per-surface access guards — HTML single, REST
  single (404), REST collection, and the media modal. New uploads become
  independent (post_parent 0, owner, defaults). Canonical single page
  /?attachment_id={id}. Attachment edit fields for visibility/listed/searchable.
  Media archives (/media/) arrive in Phase 1b.

= 0.0.1 =
* Phase 0 scaffold: non-destructive activation, a Core / Independent
  relationship-mode setting (recorded, no effect until Phase 1), and a read-only
  attachment parent-relationship scan.

== Copyright ==

Axismundi Media Library, Copyright 2026 KIM JIWOON.
Distributed under the terms of the GNU General Public License, version 3 or later.
