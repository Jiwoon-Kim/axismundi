=== Axismundi Geodata ===
Contributors: kimjiwoon
Tags: geo, geotag, location, taxonomy, rest-api
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.3
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Canonical geo store for Axismundi: place taxonomies and privacy-aware coordinate metadata, exposed over REST.

== Description ==

Axismundi Geodata is the data layer that lets posts and attachments carry a
location, and lets the editor, map blocks, and federation serializers read it
back through one shared model. It registers:

* **`axismundi_geo_area`** — a hierarchical taxonomy for address / administrative
  containment (대한민국 > 부산광역시 > 수영구 > 광안동). Answers "where is it?".
* **`axismundi_geotag`** — a flat taxonomy for place / content geo tags (광안리해수욕장,
  광안대교). Answers "what place is it about?".
* **Coordinate meta** on posts, pages, and attachments — the observation /
  capture point, using the WordPress-convention `geo_latitude`, `geo_longitude`,
  `geo_public` keys plus `ax_geo_*` extensions.
* **Place-fact term meta** — centroid, display bounds / zoom, and external place id on
  each geo taxonomy term.

The taxonomy identifiers are plugin-prefixed to avoid conflicts with other
plugins. Their public permalink bases remain `/geo-area/` and `/geotag/`.

Privacy is built into the model. The raw exact coordinate is stored, while
`geo_public` decides whether an attachment coordinate may leave the site.
Callers ask the plugin for the public coordinate rather than reading raw meta.

Attachments get a **Location (GPS)** editor on the Edit Media screen: latitude,
longitude, altitude, and a public toggle, plus an on-demand **Import from EXIF**
button that reads the original file's GPS, and a Leaflet mini map with a draggable
marker (configure a tile provider under Settings → Geodata). Nothing is
auto-imported or auto-published — the public toggle defaults off.

Posts and pages express location through the Geo Area / Geotag taxonomies. The
separate Axismundi Map plugin consumes this data for front-end place, media,
track, and geo-archive maps. Attachments carry coordinate meta only — they are
never auto-tagged with place terms. A future ax_note post type can opt into the
geo taxonomies through the `axismundi_geodata_object_types` filter.

Both public taxonomies include plugin-owned block templates for their archive
pages. They retain the tonal archive header, current-term map, inherited card
feed, and geo navigation sidebar previously supplied by the Axismundi theme.
Themes and site owners can still override them through the normal template
hierarchy. The map remains optional; without Axismundi Map the archive feed and
navigation continue to render.

The theme does not hard-code Geo taxonomy blocks. Insert the plugin-provided
**Post geo terms** pattern where a post template should expose its areas and
places.

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

For self-hosted maps, upload a `.pmtiles` map pack under Media. The plugin reads
its tile format, schema, bounds, zoom, and attribution into editable attachment
fields, and a Protomaps map pack can be chosen as the admin preview basemap under
Settings → Geodata. Front-end provider settings are enabled when Axismundi Map is
active. The plugin never bundles or generates tile data.

GPX and KML uploads are recognised as GPS tracks and exposed as GeoJSON through
the REST API. Geotags and public GPS media are also available as GeoJSON for map
clients. The controlled geotag place-type vocabulary uses Google Places tokens as
its baseline plus reviewed local extensions with explicit Google fallbacks.

WordPress RSS 2.0 and Atom feeds are extended with GeoRSS Simple when location
data is available. A post with one coordinate-bearing geotag emits a point;
multiple geotags emit their bounding box; posts without geotags remain ordinary
feed items. Geo taxonomy feeds also describe the current term at feed level. The
serializer uses public geotag facts only and never exposes post or attachment GPS
meta. A server-side adapter uses WordPress `fetch_feed()` / SimplePie to convert
external GeoRSS and W3C Geo feeds into GeoJSON for map clients.

== External services ==

All external services are optional. Choosing **None** for preview maps and not
configuring a lookup provider makes no external request.

* **Google Maps Platform** is used only when an administrator configures a Google
  API key and explicitly searches for or reverse-geocodes a place in the term
  editor. The server sends the search text or clicked latitude/longitude, selected
  language/region, and the configured API key to Google. Google Maps Platform
  Terms: https://cloud.google.com/maps-platform/terms/ Privacy Policy:
  https://policies.google.com/privacy
* **OpenStreetMap Nominatim** is used only when an administrator selects the
  public Nominatim provider or configures a custom Nominatim-compatible endpoint,
  then performs a place lookup. The server sends the search text or clicked
  latitude/longitude to that endpoint. Public Nominatim usage policy:
  https://operations.osmfoundation.org/policies/nominatim/ OpenStreetMap privacy
  policy: https://wiki.osmfoundation.org/wiki/Privacy_Policy
* **Protomaps assets** supply glyph and sprite files only when an administrator
  selects a Protomaps PMTiles preview style. The administrator's browser requests
  the selected glyph/sprite URLs, which disclose the browser IP address and the
  requested map assets to Protomaps' GitHub Pages host. Protomaps legal terms:
  https://protomaps.com/legal GitHub privacy statement:
  https://docs.github.com/site-policy/privacy-policies/github-privacy-statement
* **MapTiler** and **Thunderforest** are optional raster-tile providers selected
  by the site administrator. When selected, the viewer's browser requests map
  tile coordinates and sends its IP address, referrer, and the provider key in
  the configured tile URL. MapTiler Terms: https://www.maptiler.com/terms/
  Privacy Policy: https://www.maptiler.com/privacy-policy/ Thunderforest Terms:
  https://www.thunderforest.com/terms/ Privacy Policy:
  https://www.thunderforest.com/privacy/
* **External GeoRSS/W3C Geo feeds** are fetched only when a site administrator
  supplies a feed URL to the server-side GeoRSS importer. The server sends the
  configured feed URL request to that feed's host. Its terms and privacy policy
  are determined by the feed provider selected by the site administrator.

== Installation ==

1. Upload and activate this plugin under any block theme.
2. Posts and pages gain the Geo Area and Geotag taxonomies; attachments gain GPS
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

Uploaded PMTiles map packs self-host the tile data, but the Protomaps renderer may
load small glyph/sprite assets from Protomaps' public host. Axismundi Map is a
separate dependent plugin: Geo Data owns the facts, REST exports, providers, and
shared renderer assets; Map owns the public block and Query Map View. See
docs/map-strategy.md for the boundary.

== Changelog ==

= 0.2.3 =
* Use unique `axismundi_geo_area` and `axismundi_geotag` taxonomy identifiers while preserving the established public archive URLs.
* Migrate pre-release term assignments to the prefixed identifiers on upgrade.
* Prefix the place-lookup transient key with the full plugin namespace.

= 0.2.2 =
* Document every optional external service, the information it receives, and its terms and privacy links.
* Restrict anonymous attachment and GPS-track GeoJSON output to opted-in media whose actual parent post is published and publicly viewable.

= 0.2.1 =
* Move the complete geo_area and geotag archive templates from the Axismundi theme to their Geodata domain owner.
* Preserve the current-term map, inherited card feed, and geographic navigation while allowing normal theme and user overrides.

= 0.2.0 =
* Extend both RSS 2.0 and Atom feeds with conditional GeoRSS Simple geometry for
  geo taxonomy feeds and posts carrying public geotag terms.
* Add a `fetch_feed()` / SimplePie adapter that normalizes external GeoRSS and
  W3C Geo feeds into bounded GeoJSON FeatureCollections for map clients.
* Preserve structured feed properties for map popups, including title, image,
  author, date, and excerpt, while sanitizing the only retained HTML fragment.

= 0.1.0 =
* Initial foundation: geo_area + geotag taxonomies, post/attachment coordinate
  meta, term place-fact meta, public-coordinate gating, and REST exposure.
* Attachment Location (GPS) editor: lat/lng/altitude/public fields, on-demand
  EXIF GPS import, and a Leaflet mini map with a draggable marker.
* Admin preview map settings (Settings → Geodata): none, admin-only OpenStreetMap
  preview, custom raster (XYZ) tiles, or a self-hosted uploaded PMTiles map pack
  rendered with MapLibre + the Protomaps light theme.
* Optional place lookup for geo_area / geotag terms through a provider registry —
  Google Places (server-side key) and OpenStreetMap / Nominatim (public opt-in or
  custom endpoint) — with explicit candidate binding to a namespaced identity.
* Controlled 508-type geotag vocabulary: Nature, Facilities, and Business,
  combining Google Places tokens with reviewed local extensions and fallbacks.
* PMTiles map-pack metadata and preview support, GPX/KML track recognition, and
  GeoJSON REST exports for geotags, public GPS media, and tracks.
