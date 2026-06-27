# Axismundi Geodata Map Strategy

This document records the current map/UI boundary so future work does not
accidentally turn the plugin into a large, ambiguous map product too early.

## Current Scope

Axismundi Geodata owns the data model and admin tools:

- geo_area and geotag terms, including place facts and provider identity.
- Attachment GPS coordinates and manual EXIF import.
- Uploaded PMTiles map packs as WordPress attachments.
- Admin preview maps for coordinate picking and map-pack validation.

The plugin does not bundle map data. PMTiles files are user-provided content.

## PMTiles Hosting Boundary

For uploaded PMTiles map packs, the tile data is self-hosted from the WordPress
uploads directory. This avoids depending on public OpenStreetMap tile servers for
production map tiles and keeps traffic under the site operator's control.

The current Protomaps admin preview may still load label glyphs and sprites from
the public Protomaps assets host. That is an intentional v1 tradeoff:

- The heavy tile data is local.
- The glyph/sprite assets are small rendering assets.
- Full offline/PWA map support is not a current requirement.

If full offline support becomes a product requirement, add self-hosted glyph PBF
and sprite assets as a separate task. Theme web fonts are not a direct substitute
for MapLibre glyph PBF endpoints.

## PMTiles Schema Boundary

PMTiles is a container, not a style contract. A map pack can contain Protomaps,
OpenMapTiles, custom vector tiles, or raster tiles. The plugin stores:

- ax_map_schema
- ax_map_style
- ax_map_bounds
- ax_map_min_zoom
- ax_map_max_zoom
- ax_map_attribution

Admin preview v1 renders only the Protomaps schema with the bundled Protomaps
light style. Other schemas are valid map packs but need their own uploaded style
or renderer support before preview/front-end rendering.

## Front-end Map UI Boundary

A generic front-end Map block is intentionally not locked yet. The use cases are
different enough that one premature block would likely be over-generalized:

- Single post map: usually one or more places, or possibly a GPX/KML/GeoJSON
  route/track attachment. For simple one-place posts, an embed may be enough.
- Archive/search map: a Query Map View over query results, with marker limits,
  filtering, privacy-aware public coordinates, and possibly clustering.

For now, the stable foundation is:

- place identity and geotag/geo_area data,
- attachment coordinates and route-like attachment metadata in the future,
- map packs and admin preview rendering.

Build the front-end UI only after the data shape is clear:

1. Geotag/geo_area template and chip/card presentation.
2. Optional GPX/GeoJSON/track attachment recognition.
3. Query-to-bbox REST/search endpoint for archive/search map views.
4. A dedicated single-post map/track block or archive Query Map View, as needed.

## Plugin Boundary

Axismundi Geo Data stays the **data + admin layer**, and also acts as the shared
**map asset provider**. Front-end rendering and AR are separate plugins that
depend on it, so they don't re-bundle libraries or duplicate the data model:

- **axismundi-geodata** (this plugin) — geo_area / geotag, coordinate + place
  meta, place identity + lookup providers, attachment metadata for PMTiles / GPX /
  KML, admin preview maps, REST. It owns the vendored map libraries and exposes
  them as stable script handles and helpers.
- **axismundi-map** (future) — front-end Map block, Query Map View, single-post
  place/track map, GPX/KML route rendering, marker clustering. Depends on
  axismundi-geodata and reuses its map assets rather than bundling its own.
- **axismundi-ar** (future) — camera / orientation / WebXR, POI overlay, nearby /
  route AR. Depends on axismundi-geodata (and optionally axismundi-map).

### Map asset provider contract

A dependent plugin reuses these instead of re-bundling:

- Script handles: `axismundi-leaflet`, `axismundi-maplibre`, `axismundi-pmtiles`,
  `axismundi-protomaps-basemaps`, `axismundi-geodata-map-field`.
- Helpers: `axismundi_geodata_enqueue_map_field()`, `axismundi_geodata_resolve_tiles()`,
  `axismundi_geodata_map_pack()`.

Geo Data owns the vendor assets and admin map utilities; the Map plugin owns
front-end rendering semantics. The actual plugin split happens when the front-end
map is built — the admin preview can stay in Geo Data.

## Geo Area Boundary Model

A geo_area has two distinct spatial facts that must not be conflated:

- `geo_latitude` / `geo_longitude` are its representative centre point.
- The authoritative outline is GeoJSON geometry referenced through a WordPress
  attachment. Large polygon data must not be stored directly in term meta.

(A generic `ax_geo_radius` circle was removed — it had no consumer and conflated a
search/uncertainty radius with an administrative boundary. A purpose-built radius
can return if a real "nearby" feature needs one.) Boundary upload is itself
deferred: the basemap already conveys boundaries, so it earns its place only with a
layout that needs the outline (e.g. a clickable district-map archive).

Manual GeoJSON upload is the baseline workflow. OpenStreetMap/Nominatim may later
offer an explicit "Import boundary" action after an OSM place is bound. That action
must persist the returned geometry as an attachment and reuse it thereafter; it
must not fetch topology on every editor or front-end render. Google Places
viewports are display hints and must not be treated as physical boundaries.

### Geo-area archive viewport policy

The front-end Query Map View does not infer scale from an administrative type or
from a generic radius. Its viewport priority is:

1. When the current query page has geotag markers, fit all marker coordinates.
   Enhanced pagination refits the persistent map instance to the replacement
   marker set.
2. With no markers, use the geo_area centre plus an explicit `ax_geo_zoom` when
   an editor supplied one.
3. Otherwise fit `ax_geo_bounds`, a W,S,E,N display viewport supplied by a lookup
   or entered manually.
4. If none exists, use the centre with the renderer's conservative default zoom.

`ax_geo_bounds` is a camera hint, not authoritative administrative geometry.
