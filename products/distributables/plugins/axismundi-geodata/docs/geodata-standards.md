# Axismundi Geo Data — standards & classification

How the plugin's geography model relates to external standards, and the rules for
the administrative-type vocabulary. Companion to `map-strategy.md`.

## Guiding principles

1. **Provider types are a crosswalk, never canonical.** Google Places types
   (place-types table C), OSM `class`/`type`, VWorld, GeoNames, and Wikidata terms
   are imported and stored as `ax_geo_place_id` (`source:id`) + `provider_type` on
   the candidate — they are *mapped into* our model, not adopted as the model.
   Google Address Validation is an address-normalisation service, not an
   administrative-division ontology.
2. **`ax_geo_place_type` is an abstract, human-readable type.** It says *what kind*
   of place/area this is in broad, cross-country terms — not the exact national
   class. Keep it deliberately wide and dull so it survives many countries.
3. **Official codes carry the precision.** The exact subdivision is encoded by the
   term **name** plus `ax_geo_country_code` (ISO 3166-1 alpha-2 → schema.org
   `addressCountry`) and `ax_geo_iso_3166_2` (the ISO 3166-2 subdivision code where
   one exists — *not* only first-order; some are second-order or special, e.g.
   US-DC → schema.org `addressRegion`). ISO 3166 is a *code system*, not a global
   naming ontology, so we do not unify every country's administrative names into one
   slug set. The country code lives on the Country term and is inherited down the
   hierarchy. A generic `national_code` / `code_scheme` pair was dropped — it
   overlapped ISO 3166-2 and leaned Korea-only.
4. **Admin level = hierarchy depth, not a field.** `geo_area` is a hierarchical
   taxonomy; the tier (country → first-order → second-order → local) comes from the
   parent chain. Countries vary in depth, so any numeric `admin_level` is a
   derived/cache value at most, never the source of truth.
5. **Pre-release: hard changes allowed.** We do not implement ISO 19135 register
   governance yet. Before the first public release, slugs may be added/removed/
   renamed freely. *After* release, switch to deprecate + alias (keep retired slugs
   as aliases, never silently drop stored values).

## Administrative-type vocabulary (`geo_area`)

Global, slimmed set — abstract roles that most countries can map onto:

| slug | role | maps the likes of |
|---|---|---|
| `country` | nation | 대한민국, Japan, China |
| `province` | first-order area | 도 · 광역시 · 특별시 · 특별자치시/도 · 都/道/府/県 · 省/自治区/直辖市/特别行政区 |
| `state` | first-order area (federal) | US/DE/AU states — recognisable; may be hidden for KR |
| `city` | second-order area | 시 · 市 · 地级市 |
| `county` | second-order area | 군 · 郡 · 县 |
| `district` | second-order area | 구 · 区 |
| `town` | local | 읍 · 町 |
| `township` | local | 면 |
| `village` | local | 리 · 村 |
| `sublocality` | local | 동 (법정동/행정동 모두) |
| `neighborhood` | local (perceptual edge) | small named areas |

**Removed** (were ambiguous or country-specific labels masquerading as global types):

- `metropolitan_city` → `province`. 광역시/특별시 is a *first-order area*; the
  "광역시 vs 도 vs 都 vs 直辖市" distinction lives in the name + `ax_geo_iso_3166_2`
  (KR-26, JP-13, CN-BJ), not in a global slug.
- `ward` → `sublocality` (overlapped; 동 is a sublocality here, not an electoral ward).
- `borough` → `district` (re-introduce as a country profile if UK/NYC data needs it).
- `region`, `locality`, `municipality` → removed (too elastic across countries).
- `administrative_area` → removed from the picker entirely; it was a "don't know"
  fallback that degrades vocabulary quality when offered as a choice. An unmapped
  import stays unset (null) rather than this catch-all.

This trims ~18 → 11 types. Country-specific exact classes (광역시, 特别行政区, …) are
**not** added as global slugs; if precise local display is ever needed, add an
optional per-country `local_type` profile later (deferred — ISO 3166-2 already
encodes the subdivision today).

### Not administrative areas — separate concerns (future taxonomies)

Do **not** put these in `geo_area`:

- **Perceptual / travel areas** (광안리, 서면권, 부울경, 전포카페거리) → a future
  `travel_area` taxonomy. They are not official divisions.
- **Statistical areas** (UK OA/LSOA/MSOA, census tracts) → a `statistical_area`
  concern.
- **Transport / fare zones** (London Travelcard "Zone 1") → a `transport_zone`
  concern. A UK "zone" is a fare/transport band, not an administrative division.

### Addresses are not taxonomy

The geo_area hierarchy stops at the lowest official admin unit (e.g. 동). The
**road-name address** (도로명주소) belongs on the *place/post* as an address object,
not as more taxonomy depth:

```
geo_area:  대한민국 > 부산광역시 > 남구 > 용당동      (administrative hierarchy)
address:   street_address "동명로 26", postal_code "48500", road_name "동명로"
geo:       lat/lng (+ altitude, accuracy)
```

`geo_address` stays a single formatted display string (in the site locale); the
hierarchy is the canonical structure (aligns with ISO 19160 structured addressing).

### 법정동 / 행정동

Not distinguished — both are `sublocality`. The legal-dong / admin-dong codes
(법정동코드 / 행정동코드) are not stored; the hierarchy + term name are enough for a
publishing / travel context.

## Coordinate interoperability

Canonical CRS is **WGS 84 (EPSG:4326)** everywhere. Watch the coordinate **order** —
mixing it up is the classic "Busan ends up in the sea" bug:

| format | order | example | spec |
|---|---|---|---|
| GeoJSON position | **lon, lat** | `[129.1199, 35.1547]` | RFC 7946 (WGS84 only; no alternate CRS) |
| Geo URI | **lat, lon** | `geo:35.1547,129.1199;u=30` | RFC 5870 (`u` = uncertainty, metres) |
| ISO 6709 | **lat, lon** | `+35.1547+129.1199+30.000/` | ISO 6709 (sign-prefixed; trailing `/`) |

Our fields map 1:1, so these are pure formatters (`includes/coordinates.php`):

- `geo_latitude`, `geo_longitude` → required pair
- `geo_altitude` → 3rd coordinate (metres)
- `geo_accuracy` → Geo URI `u=` uncertainty (metres)

The helpers are named unambiguously to keep the order straight:
`axismundi_geodata_coords_to_geo_uri()` (lat,lng), `…_to_iso6709()` (lat,lng),
`…_to_geojson_position()` (lng,lat). The GeoJSON REST export now carries a `geo_uri`
property on each point feature (attachment points include altitude + `u=` accuracy);
the GeoJSON geometry is built through `…_to_geojson_position()` so the lon/lat order
is set in exactly one place. The Geo URI omits `crs` (RFC 5870's default is already
WGS 84) and the GeoJSON stays free of a `crs` member (RFC 7946 mandates WGS 84 and
removed alternate-CRS support).

## Standards summary

**Implemented / aligned**
- ISO 3166-1 alpha-2 — `ax_geo_country_code` (→ schema.org addressCountry)
- ISO 3166-2 — `ax_geo_iso_3166_2` (subdivision code, any level → schema.org addressRegion)
- GeoJSON / RFC 7946 — REST export ([lon,lat], WGS84)
- RFC 5870 Geo URI, ISO 6709 — coordinate formatters (`includes/coordinates.php`);
  `geo_uri` on GeoJSON point features
- ISO 8601 — timestamps (WordPress default; GPX/KML track times)

**Planned**
- ISO 19107 geometry (GeoJSON Polygon/MultiPolygon) — geo_area boundary upload
- ISO 19115 (lightweight) — map-pack / export dataset metadata (source, license,
  extent, CRS)

**Reference only (not strictly implemented)**
- ISO 19135 register governance — concept only; pre-release allows breaking
  changes, deprecate/alias after release
- ISO 19160 addressing — our hierarchy already provides structured addressing
- Google Places / Address Validation — provider crosswalk, never canonical

**Out of scope**
- ISO 19152 (LADM, land rights / cadastre) — not a publishing concern
- ISO 19103 (conceptual schema language) — meta-standard, nothing to implement
