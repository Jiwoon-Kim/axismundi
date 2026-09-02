=== Axismundi Emoji ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Custom emoji for Axismundi: registry, admission review, per-authority binary cache, local registration, and a block-editor picker.

== Description ==

Custom emoji are instance-specific images addressed by a `:shortcode:` inside otherwise plain text, declared in the `tag` array of federated Objects and Actors (FEP-9098).

Receiving: declared remote emoji are recorded, held in an authority-gated review queue,
verified against their own authority, cached as bytes once approved, and substituted into
Object bodies, Actor names, and Actor biographies. A shortcode nobody approved stays text.

Sending: emoji are uploaded through WordPress's own upload validation, or copied from an
already-cached remote one at no extra storage cost, and the ones a Note, Article, or Actor
actually uses are declared in its outbound `tag` array. Emoji marked local-only travel as
their shortcode and never as an image.

The design is recorded in `docs/AXISMUNDI-EMOJI-ARCHITECTURE.md` at the repository root.

Unicode emoji and their Twemoji-style image substitutions remain WordPress core's
domain. Emoji reactions (FEP-c0e0) belong to Axismundi Activities, while this plugin
observes their declared custom emoji so the existing review and cache pipeline can
render approved reaction chips.

== Changelog ==

= 0.2.0 =
* Moves onto the canonical repository names.

Earlier releases are listed in changelog.txt.

== Bundled emoji ==

The plugin ships one emoji of its own, `:axismundi:` at `emoji/axismundi.webp` — a
200x200 WebP derived from the project's brand symbol, 5.6 KB, transparency intact.
Its project-owned brand-asset provenance and GPL-3.0-or-later release are recorded beside
the file in `emoji/LICENSE.txt`.

One size, because FEP-9098 gives an Emoji exactly one `icon.url`: there is no srcset
in the emoji contract, so a second file would federate nothing. 200px is generous
enough for a picker tile and scales down cleanly to the ~1.2em inline case.

It also serves as the outbound conformance fixture, so the test suite never depends
on another instance's restricted asset.
