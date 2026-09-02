=== Axismundi Media Library ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.42
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

= 0.0.42 =
* Restrict federation rendition diagnostics to users who can edit the requested attachment.
* Require attachment visibility before Media Preview and Media Rights render, including editor-preview fallbacks.

Earlier releases are listed in changelog.txt.

== Copyright ==

Axismundi Media Library, Copyright 2026 KIM JIWOON.
Distributed under the terms of the GNU General Public License, version 3 or later.
