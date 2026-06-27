=== Axismundi Map ===
Contributors: kimjiwoon
Tags: map, geo, geotag, track, pmtiles
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.2
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
* shows a marker / line per feature, with optional click popups. Public GPS photo
  popups can include the attachment thumbnail;
* can show an opt-in visitor location control on the front end. The browser
  location prompt is only triggered when the visitor presses the map control;
* supports geo taxonomy archives: a geotag archive focuses on that place, while
  a geo-area archive maps the deduplicated geotags attached to posts on the
  current query page. Enhanced pagination replaces only the GeoJSON overlay and
  refits the persistent map instance so every marker remains visible.

Block attributes: source (none / current archive / geotags / selected media /
track), bbox, media ids, track id, height, zoom (0 = auto-fit), show popups, and show
visitor location control.

This is v0.1: a thin GeoJSON map block plus a native Query Map View for geo
archives. Google / Naver renderers, clustering, and elevation charts are later
work.

== Installation ==

1. Install and activate Axismundi Geo Data, then set a front-end map provider
   (custom raster tiles or an uploaded PMTiles map pack) under Settings > Geodata.
2. Upload and activate this plugin.
3. Add the Axismundi Map block to a post or page and choose a source.

== Changelog ==

= 0.1.2 =
* Fit geo-area archive maps to all geotag markers on the current query page;
  when there are no markers, fall back to the term's manual zoom or provider
  viewport.
* Keep point overlays above the PMTiles raster fallback on wide-area views.

= 0.1.1 =
* Add the geo-area Query Map View: derive markers from geotags on the current
  inherited-query page and update them through the Interactivity API during
  enhanced pagination without remounting the map.
* Centre empty geo-area maps on the term coordinate.

= 0.1.0 =
* Initial release: the axismundi/map block — basemap (Leaflet raster / MapLibre
  PMTiles) plus geotags / selected media / track GeoJSON overlay, reusing
  Axismundi Geo Data's map assets and REST export.
