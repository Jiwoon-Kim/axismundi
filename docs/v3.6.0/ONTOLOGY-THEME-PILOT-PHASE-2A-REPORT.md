# Ontology Theme Pilot Phase 2A Report

Status: Phase 2A runtime scaffold complete
Cycle: v3.6.0 Ontology Theme Pilot
Date: 2026-05-18

## User Request Log Coverage

- New Pilot directory created at `products/reference-implementations/axismundi-pilot/`.
- WordPress theme slug is `axismundi-pilot`.
- Display name is `Axismundi Pilot`.
- Root `.wp-env.json` mounts `./products/reference-implementations/axismundi-pilot`.
- Existing `products/reference-implementations/ontology-theme-pilot/` was not modified.
- Carousel remains excluded from the Pilot and routed to plugin extraction.

## Deliverables

Created:

- `.wp-env.json`
- `products/reference-implementations/axismundi-pilot/style.css`
- `products/reference-implementations/axismundi-pilot/theme.json`
- `products/reference-implementations/axismundi-pilot/functions.php`
- `products/reference-implementations/axismundi-pilot/README.md`
- `products/reference-implementations/axismundi-pilot/readme.txt`
- `products/reference-implementations/axismundi-pilot/screenshot.png`
- `products/reference-implementations/axismundi-pilot/templates/index.html`

Updated:

- `docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md`

## Runtime Scaffold Finding

Phase 2A originally treated templates as Phase 2C work. WordPress requires a
minimum block theme template before it will list and activate the theme, so
`templates/index.html` was added as a runtime activation stub.

This file is intentionally minimal. Full template architecture remains Phase 2C
scope.

## wp-env Runtime Notes

`@wordpress/env` was updated to 11.6.0 in the user environment.

The local Node/DNS environment caused `wp-env` to mis-detect WordPress.org
availability during latest-version resolution. Phase 2A uses an explicit
WordPress core pin in `.wp-env.json`:

```json
"core": "WordPress/WordPress#6.9.4"
```

`testsEnvironment` is disabled because Phase 2A only needs development runtime
activation, not PHPUnit test runtime.

## Verification

```txt
wp-env version:       11.6.0
WordPress core:       6.9.4
Site URL:             http://localhost:8888
Theme status:         axismundi-pilot active
Validator:            1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:             PASS
functions.php lint:   PASS
Browser smoke:        PASS, no console errors
```

Browser smoke target:

```txt
http://localhost:8888/
```

Observed:

- Page title: `Axismundi Pilot`
- Heading visible: `Axismundi Pilot`
- Scaffold copy visible: `v3.6.0 Phase 2A runtime scaffold`
- Console errors: 0

## Phase 2B Entry

Phase 2B may proceed after user approval.

Phase 2B focus:

- Generate Pilot asset bridge script.
- Copy lab CSS into Pilot-local `assets/styles/`.
- Copy Material fonts/icons into Pilot-local `assets/fonts/` and `assets/icons/`.
- Rewrite `fonts.css` repository-relative URLs to Pilot-local asset URLs.
- Keep the Pilot self-contained for wp-env and future distribution checks.

