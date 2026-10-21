=== Axismundi ===
Contributors: kimjiwoon
Tags: activitypub, activitystreams, social, federation
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0-alpha0.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

React applications and integration surfaces for Axismundi.

== Description ==

Axismundi is an early application shell. It provides a public social route at
`/social/` and a separate WordPress administration surface. Feed data, federation
interactions, and PWA behaviour are not part of this alpha release.

== Third-party assets ==

Material loading shape geometry derives from Android Open Source Project
MaterialShapes and AndroidX graphics-shapes, under Apache-2.0. Source versions,
processing notes, and the license text are preserved beside the assets in
`src/apps/frontend/components/loading-indicators/assets/`.
Progress geometry and animation timelines adapt AndroidX Material3 and Material
Components for Android under Apache-2.0. Source
commit, modifications, and license are in
`src/apps/frontend/components/progress-indicators/`.

== Installation ==

1. Upload and activate the plugin.
2. Visit `/social/` for the public social application shell.
3. Use **Axismundi** in the WordPress administration menu for the administration shell.
