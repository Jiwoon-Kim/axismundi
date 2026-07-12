=== Axismundi Media Library ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.12
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: media, attachments

Promote WordPress attachments to independent, visibility-controlled media objects.

== Description ==

Axismundi Media Library promotes the WordPress attachment into an independent,
publishable media object with its own owner, visibility, and — in later phases —
virtual folders, rights, sensitivity, saved references, and federation.

Activating the plugin changes no existing media. **Core mode** leaves WordPress
attachments untouched. **Independent mode** detaches new uploads (post_parent 0)
and enforces per-item visibility — public, unlisted, or private — across the
attachment page, the REST API (single and collection), and the media picker.
Ownership is the WordPress author (post_author); permission reuses core
capabilities.

The full specification lives in the plugin's docs/ directory (SPEC, SECURITY,
ROUTING, COMPATIBILITY, DATA-MODEL, PHASES). Independent mode provides media
archives at /media/ and /media/{owner}/. Attachment single pages continue to use
the active theme's normal attachment template hierarchy; Axismundi includes a
dedicated attachment.html template.

== Installation ==

1. Upload and activate Axismundi Media Library.
2. Open Settings > Media Library (Axismundi). Activation changes no existing media.
3. Choose Independent mode to enable independent-attachment visibility.

== Changelog ==

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
