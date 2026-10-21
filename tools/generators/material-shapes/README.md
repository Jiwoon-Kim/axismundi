# Loading geometry exporter

This build-time Kotlin tool uses AndroidX graphics-shapes 1.1.0 for rounding and
morph matching. `MaterialPolygons.kt` adapts the seven MDC factory definitions;
it is not a replacement geometry engine. It needs JDK 17+ and Maven, or Docker:

```powershell
docker run --rm -v "${PWD}:/workspace" -v axismundi-loading-maven:/root/.m2 -w /workspace/tools/generators/material-shapes maven:3.9.9-eclipse-temurin-21 mvn -B -q compile exec:java '-Dexec.mainClass=ExportKt'
```

Run at the repository root. Add `'-Dexec.args=--check'` to compare without writing.
Native Maven can set `-Daxismundi.root=<absolute-repository-path>`.
The Docker volume caches only build dependencies, not source or generated output.

Validation:

```text
python tools/validators/validate_loading_geometry.py
node --test products/wordpress/plugins/axismundi/src/apps/frontend/components/loading-indicators/__tests__/*.test.cjs
```

Outputs live beside the frontend LoadingIndicator, not in theme tokens:
`loading-geometry.json` plus seven static SVGs. The JSON records the pinned
inputs, precision, viewBox, original shapes, and matched endpoints for each of
the seven directed transitions, including the return to SoftBurst.

The exporter also compares interpolation at 0.25, 0.5, and 0.75 against AndroidX
Morph.asCubics, rather than assuming that standalone polygon paths match.
The independent validator checks schema, closure, endpoint contour agreement,
and radial bounds. Geometry generated from the same inputs must be byte stable.

Source attribution and Apache license are kept beside both the factory adapter
and the plugin assets. Refresh version/source/license records together when
updating inputs. The library is not a frontend runtime dependency.
