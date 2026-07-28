=== Axismundi Emoji ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.3
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

Deliberately out of scope: Unicode emoji and their Twemoji-style image substitutions, which WordPress core, Mastodon, and Misskey each already handle separately; and emoji reactions (FEP-c0e0), which belong to Axismundi Activities.

== Changelog ==

= 0.1.3 =
* Provision newly bundled emoji after the schema check, so the upgrade path also runs when no database migration is needed.

= 0.1.2 =
* Register newly added bundled emoji on existing installations without restoring an emoji an operator deliberately removed.

= 0.1.1 =
* Render a local Note or Actor's emoji only from its own current outbound `tag` array,
  so its home rendering and federated declaration stay in lockstep even on an HTTP local
  development site.

= 0.1.0 =
* E1 complete: binary caching of approved emoji with a reference-counted, content-addressed
  store, and HTML substitution scoped to what each Object or Actor declared. Same-named
  emoji from two servers stay distinct.
* E2 complete: local emoji upload through `wp_handle_upload()` without attachment posts,
  a searchable catalogue with a REST endpoint, copying a cached remote emoji into the local
  registry with provenance recorded, and outbound `tag` publication for Notes, Articles,
  and Actors.
* Per-authority review defaults, a reversible bulk approval, and an opt-in fallback source
  for a shortcode the declaring server has not supplied.
* Local emoji are dereferenceable at `/emojis/{shortcode}` as ActivityStreams `Emoji`.

= 0.0.2 =
* E0 scaffold plus E1a-E1c: registry schema, parser, authority-gated observation,
  review queue, and queued authority-side metadata verification. No binary caching or
  rendering yet.

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
