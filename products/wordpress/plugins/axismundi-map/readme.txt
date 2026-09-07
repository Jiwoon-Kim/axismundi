=== Axismundi Map ===
Contributors: kimjiwoon
Tags: map, geo, geotag, track, pmtiles
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: axismundi-geodata
Stable tag: 0.2.2
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Front-end map block that draws Axismundi Geo Data geotags and GPS tracks over a self-hosted basemap.

== Description ==

Axismundi Map adds one block, **Axismundi Map** (axismundi/map), that renders a
front-end map from the data and assets the Axismundi Geo Data plugin provides. It
does not bundle its own map libraries or tile data — it reuses Geo Data's basemap
provider and GeoJSON export.

The block:

* draws the basemap resolved by Geo Data's front-end provider — Leaflet for custom
  raster tiles, MapLibre + the Protomaps theme for an uploaded PMTiles map pack
  (the public OpenStreetMap tile server is never used on the front end);
* overlays a GeoJSON source — geotags (optionally within a bounding box),
  selected media attachments (public GPS photos plus GPX/KML tracks), or a
  single GPS track attachment — fetched from Geo Data's REST endpoints;
* can overlay an external GeoRSS Simple / W3C Geo feed (including federated
  feeds): Geo Data fetches and caches it server-side and converts it to GeoJSON,
  so no public URL proxy is exposed. Points, lines, and polygons / boxes render;
* shows a marker / line / polygon per feature, with optional click popups. Public
  GPS photo popups can include the attachment thumbnail;
* can show an opt-in visitor location control on the front end. The browser
  location prompt is only triggered when the visitor presses the map control;
* supports geo taxonomy archives: a geotag archive focuses on that place, while
  a geo-area archive maps the deduplicated geotags attached to posts on the
  current query page. Enhanced pagination replaces only the GeoJSON overlay and
  refits the persistent map instance so every marker remains visible.

Block attributes: source (none / current archive / geotags / selected media /
track / GeoRSS feed), bbox, feed url, media ids, track id, height, zoom (0 =
auto-fit), show popups, and show visitor location control.

This is v0.2: a GeoJSON and GeoRSS map block plus a native Query Map View for
geo archives. Google / Naver renderers, clustering, and elevation charts are
later work.

== Installation ==

1. Install and activate Axismundi Geo Data, then set a front-end map provider
   (custom raster tiles or an uploaded PMTiles map pack) under Settings > Geodata.
2. Upload and activate this plugin.
3. Add the Axismundi Map block to a post or page and choose a source.

== External services ==

When the Axismundi Geo Data provider is configured to use an uploaded PMTiles
map pack, this plugin configures MapLibre to fetch map font glyphs and sprite
images from the Protomaps Basemaps Assets project, hosted on GitHub Pages. The
visitor's browser makes these requests while rendering the map. The requests
include the visitor's IP address and standard HTTP request metadata, plus the
requested font range or sprite filename. No WordPress account data, post data,
or visitor location is sent by this plugin to that service.

* Service: Protomaps Basemaps Assets (GitHub Pages), used for PMTiles map labels
  and symbols. It is not contacted when the configured provider uses custom
  raster tiles.
* Terms: https://protomaps.com/legal
* Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement

Site administrators separately choose and host their raster tiles or PMTiles
pack through Axismundi Geo Data. Those configured providers may have their own
terms and privacy policies.

== Changelog ==

= 0.2.2 =
* Document the Protomaps Basemaps Assets external service used by the PMTiles
  provider.
* Escape the map Interactivity context through the block wrapper at output.

Earlier releases are listed in changelog.txt.

