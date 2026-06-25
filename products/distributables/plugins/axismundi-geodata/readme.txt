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

Attachments get a **Location (GPS)** editor on the Edit Media screen: latitude,
longitude, altitude, and a public toggle, plus an on-demand **Import from EXIF**
button that reads the original file's GPS, and a Leaflet mini map with a draggable
marker (configure a tile provider under Settings → Geodata). Nothing is
auto-imported or auto-published — the public toggle defaults off.

Posts and pages express location through the geo_area / geotag taxonomies. A Map
block, geocoding, the `taxonomy-geotag.html` / `geolocation-chip` theme
presentation, and the ActivityStreams `Place` / GeoRSS serializers build on this
model in later versions. Attachments carry coordinate meta only — they are never
auto-tagged with place terms. A future ax_note post type can opt into the geo
taxonomies through the `axismundi_geodata_object_types` filter.

A demo place hierarchy (대한민국 > 부산광역시 > 수영구 > 광안동, with the
광안리해수욕장 geotag) ships with the plugin but is created only on request — it
is never seeded automatically. Run `wp axismundi-geodata seed-demo` to add it and
`wp axismundi-geodata seed-demo --remove` to drop it.

Term editors can optionally look up a place from an external provider and bind it.
Providers are configured under Settings → Geodata and are off by default: Google
Places needs a server-side API key, and OpenStreetMap (Nominatim) is enabled by
choosing the public service (low-volume admin lookup only) or a custom endpoint.
Keys and endpoints are used only from admin AJAX and are never sent to the
browser. Selecting a candidate binds the term to a namespaced id —
`google:<place_id>` or `osm:node/<id>` / `osm:way/<id>` — and stores the returned
coordinates, address, and place type.

== Installation ==

1. Install and activate the Axismundi theme (for the dormant geo presentation
   templates and styles, when those ship).
2. Upload and activate this plugin.
3. Posts and pages gain the Geo Area and Geotag taxonomies; attachments gain GPS
   coordinate fields. All are available over the REST API.

== Third-party libraries ==

Bundled under assets/vendor/, each loaded only when the matching admin preview
provider is active:

* Leaflet 1.9.4 (https://leafletjs.com/, BSD-2-Clause) — raster tile previews.
* MapLibre GL JS 5.24.0 (https://maplibre.org/, BSD-3-Clause), PMTiles 4.4.1
  (https://github.com/protomaps/PMTiles, BSD-3-Clause), and @protomaps/basemaps
  5.7.2 (https://github.com/protomaps/basemaps, BSD-3-Clause) — PMTiles map-pack
  previews. Label fonts load from the public Protomaps assets host.

== Map strategy ==

Uploaded PMTiles map packs self-host the tile data, but the current Protomaps
preview may load small glyph/sprite rendering assets from Protomaps' public asset
host. A front-end Map block is intentionally not locked yet: single-post maps,
track/route maps, and archive/search query maps have different data contracts.
See docs/map-strategy.md for the current boundary.

== Changelog ==

= 0.1.0 =
* Initial foundation: geo_area + geotag taxonomies, post/attachment coordinate
  meta, term place-fact meta, public-precision privacy model, REST exposure.
* Attachment Location (GPS) editor: lat/lng/altitude/public fields, on-demand
  EXIF GPS import, and a Leaflet mini map with a draggable marker.
* Admin preview map settings (Settings → Geodata): none, admin-only OpenStreetMap
  preview, custom raster (XYZ) tiles, or a self-hosted uploaded PMTiles map pack
  rendered with MapLibre + the Protomaps light theme.
* Optional place lookup for geo_area / geotag terms through a provider registry —
  Google Places (server-side key) and OpenStreetMap / Nominatim (public opt-in or
  custom endpoint) — with explicit candidate binding to a namespaced identity.
