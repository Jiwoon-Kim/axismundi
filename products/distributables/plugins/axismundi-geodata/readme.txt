=== Axismundi Geodata ===
Contributors: kimjiwoon
Tags: geo, geotag, location, taxonomy, rest-api
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Canonical geo store for Axismundi: place taxonomies and privacy-aware coordinate metadata, exposed over REST.

== Description ==

Axismundi Geodata is the data layer that lets posts and attachments carry a
location, and lets the editor, map blocks, and federation serializers read it
back through one shared model. It registers:

* **geo_area** — a hierarchical taxonomy for address / administrative
  containment (대한민국 > 부산광역시 > 수영구 > 광안동). Answers "where is it?".
* **geotag** — a flat taxonomy for place / content geo tags (광안리해수욕장,
  광안대교). Answers "what place is it about?".
* **Coordinate meta** on posts, pages, and attachments — the observation /
  capture point, using the WordPress-convention `geo_latitude`, `geo_longitude`,
  `geo_public` keys plus `ax_geo_*` extensions.
* **Place-fact term meta** — centroid, radius, bounds, and external place id on
  each geo_area / geotag term.

Privacy is built into the model. The raw exact coordinate is stored, but
exposure is gated twice: `geo_public` decides whether any coordinate leaves the
site, and `ax_geo_public_precision` (hidden / city / coarse / neighborhood /
exact) decides how precise the exposed value is. Callers ask the plugin for the
public coordinate rather than reading the raw meta.

This release is the data foundation only. Editor UI, current-location capture,
EXIF import, geocoding, map blocks, the `taxonomy-geotag.html` / `geolocation-chip`
theme presentation, and the ActivityStreams `Place` / GeoRSS serializers build on
top of it in later versions.

Attachments carry coordinate meta only — they are never auto-tagged with place
terms. A future ax_note post type can opt into the geo taxonomies through the
`axismundi_geodata_object_types` filter.

== Installation ==

1. Install and activate the Axismundi theme (for the dormant geo presentation
   templates and styles, when those ship).
2. Upload and activate this plugin.
3. Posts, pages, and attachments gain geo coordinate fields; posts and pages gain
   the Geo Area and Geotag taxonomies. All are available over the REST API.

== Changelog ==

= 0.1.0 =
* Initial foundation: geo_area + geotag taxonomies, post/attachment coordinate
  meta, term place-fact meta, public-precision privacy model, REST exposure.
