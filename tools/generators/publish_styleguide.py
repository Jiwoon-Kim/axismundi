#!/usr/bin/env python3
"""
publish_styleguide.py — Publish-mirror generator.

Copies the style guide HTML + dependencies from the source-of-truth location
(axismundi-lab/) to the publish surface (/styleguide/), rewriting
relative paths as needed.

Source authority:  products/reference-implementations/axismundi-lab/
Publish surface:   styleguide/

Module-aware (v3.4.0+): in addition to lab/stylesheets/*.css (the design
system files), the publisher also discovers lab/modules/*/lab-*.css and
flattens those into the publish surface's stylesheets/ directory. The
lab-<name> prefix on each module's CSS preserves identifiability after
flattening (see lab/modules/README.md).

Module JS, pattern HTML, and per-module audit docs are intentionally NOT
copied into the /styleguide/ mirror. On repository-root GitHub Pages they
remain browsable from their source-tree paths as validation specimens, not as
canonical public API. The flattened module CSS exists so the styleguide mirror
can display validated module styling while preserving source authority.

Per Constitution Article 12: publishing surfaces are mirrors, not authorities.
The /styleguide/ folder is a derived artifact. Edit the Axismundi lab source,
then re-run this generator.

This is also how GitHub Pages renders the showcase.
"""

import shutil
import sys
from pathlib import Path
import re

UTF8 = "utf-8"
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding=UTF8)
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding=UTF8)

ROOT = Path(__file__).resolve().parent.parent.parent
SOURCE = ROOT / "products/reference-implementations/axismundi-lab"
PUBLISH = ROOT / "styleguide"

# Mapping of source style guide files to publish surface filenames
STYLE_GUIDES = [
    ("style-guide.html", "index.html"),                  # canonical entry
    ("style-guide-blocks.html", "blocks.html"),
    ("style-guide-prose.html", "prose.html"),
    ("style-guide-patterns.html", "patterns.html"),
]


def main():
    print(f"=== Publishing style guides ===\n")
    print(f"  Source: {SOURCE.relative_to(ROOT)}")
    print(f"  Target: {PUBLISH.relative_to(ROOT)}\n")

    if not SOURCE.exists():
        print(f"  ✗ Source not found: {SOURCE}")
        return

    # Clean publish surface
    if PUBLISH.exists():
        shutil.rmtree(PUBLISH)
    PUBLISH.mkdir(parents=True)

    # 1. Copy stylesheets (mirror) — and rewrite paths.
    #    lab/stylesheets/fonts.css uses paths like
    #      ../../../../core/design-systems/material3/assets/...
    #    (lab → up 4 → repo root → core)
    #    But publish surface /styleguide/stylesheets/fonts.css needs
    #      ../../core/design-systems/material3/assets/...
    #    (styleguide → up 2 → repo root → core)
    #
    #    1a. Design-system stylesheets at lab/stylesheets/*.css (depth 4)
    #    1b. Module stylesheets at lab/modules/*/lab-*.css (depth 5)
    #    Both are flattened into publish/stylesheets/. Module names use
    #    the lab-* prefix so they remain identifiable on the flat
    #    publish surface (see modules/README.md for rationale).
    src_css = SOURCE / "stylesheets"
    dst_css = PUBLISH / "stylesheets"
    dst_css.mkdir(parents=True, exist_ok=True)

    css_count = 0

    # 1a. Design-system stylesheets
    if src_css.exists():
        for src_file in src_css.glob("*.css"):
            text = src_file.read_text(encoding=UTF8)
            # Rewrite asset paths: lab depth → publish depth
            text = text.replace(
                "../../../../core/design-systems/material3/assets/",
                "../../core/design-systems/material3/assets/"
            )
            (dst_css / src_file.name).write_text(text, encoding=UTF8)
            css_count += 1

    # 1b. Module stylesheets — flatten lab/modules/<name>/lab-*.css into
    #     publish/stylesheets/. Module CSS files do not currently
    #     reference core/design-systems/material3/assets/ paths, but the
    #     depth-5 rewrite is applied defensively in case a future module
    #     does.
    src_modules = SOURCE / "modules"
    if src_modules.exists():
        for module_dir in sorted(src_modules.iterdir()):
            if not module_dir.is_dir():
                continue
            for src_file in module_dir.glob("lab-*.css"):
                text = src_file.read_text(encoding=UTF8)
                # Defensive depth-5 rewrite (modules are one level deeper
                # than lab/stylesheets/)
                text = text.replace(
                    "../../../../../core/design-systems/material3/assets/",
                    "../../core/design-systems/material3/assets/"
                )
                # Also handle depth-4 references in case a module CSS was
                # ever authored to look like a design-system file
                text = text.replace(
                    "../../../../core/design-systems/material3/assets/",
                    "../../core/design-systems/material3/assets/"
                )
                (dst_css / src_file.name).write_text(text, encoding=UTF8)
                css_count += 1

    print(f"  ✓ stylesheets/ ({css_count} files, paths rewritten)")

    # 2. Copy scripts (mirror) — style-guide.js plus theme.js when present.
    #    style-guide.html, blocks.html, and prose.html still reference
    #    scripts/theme.js. Copying it prevents a publish-surface 404 while
    #    keeping /styleguide/ a mirror rather than a source authority.
    src_js = SOURCE / "scripts/style-guide.js"
    dst_js = PUBLISH / "scripts/style-guide.js"
    if src_js.exists():
        dst_js.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src_js, dst_js)
        print(f"  ✓ scripts/style-guide.js")
    src_theme_js = SOURCE / "scripts/theme.js"
    dst_theme_js = PUBLISH / "scripts/theme.js"
    if src_theme_js.exists():
        dst_theme_js.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src_theme_js, dst_theme_js)
        print(f"  ✓ scripts/theme.js")

    # 3. Copy + rename style guide HTML files
    for src_name, dst_name in STYLE_GUIDES:
        src = SOURCE / src_name
        dst = PUBLISH / dst_name
        if src.exists():
            html = src.read_text(encoding=UTF8)
            # Rewrite cross-styleguide links (style-guide-blocks.html → blocks.html, etc.)
            for from_name, to_name in [
                ("style-guide.html", "index.html"),
                ("style-guide-blocks.html", "blocks.html"),
                ("style-guide-prose.html", "prose.html"),
                ("style-guide-patterns.html", "patterns.html"),
            ]:
                html = html.replace(f'href="{from_name}"', f'href="{to_name}"')
                # Also `./` prefix variant
                html = html.replace(f'href="./{from_name}"', f'href="{to_name}"')
            # Lab module links are authored relative to axismundi-lab/.
            # In the generated /styleguide/ mirror they need to point back to
            # the repository-root source tree, where GitHub Pages serves the
            # validation specimen files.
            html = html.replace(
                'href="modules/',
                'href="../products/reference-implementations/axismundi-lab/modules/'
            )
            # Foundation adjuncts are not copied into /styleguide/. They remain
            # source-tree validation/specimen pages served from the repository
            # root, like module pattern pages.
            html = html.replace(
                'href="typography-axis.html"',
                'href="../products/reference-implementations/axismundi-lab/typography-axis.html"'
            )
            # Shared demo assets live at repository-root /assets. Lab HTML is
            # three levels below the root; the publish mirror is one level below.
            html = html.replace('../../../assets/', '../assets/')
            # Add publish-mirror banner (HTML comment, no visible change)
            banner = (
                "<!-- ============================================================\n"
                "     Publishing surface — DO NOT EDIT DIRECTLY.\n"
                "     Source of truth:\n"
                f"       products/reference-implementations/axismundi-lab/{src_name}\n"
                "     Regenerate with: python3 tools/generators/publish_styleguide.py\n"
                "     ============================================================ -->\n"
            )
            # Insert banner right after <!DOCTYPE html>
            html = html.replace("<!DOCTYPE html>", "<!DOCTYPE html>\n" + banner, 1)
            dst.write_text(html, encoding=UTF8)
            print(f"  ✓ {src_name} → {dst_name}")

    # 4. Add publish surface README
    readme = """# Style guide — publish mirror

> **DO NOT EDIT FILES HERE.** This directory is a publishing surface, not a source authority.

Source of truth:
- `products/reference-implementations/axismundi-lab/style-guide*.html`
- `products/reference-implementations/axismundi-lab/stylesheets/*.css`

To update: edit the source files in the Axismundi lab, then run:

```bash
python3 tools/generators/publish_styleguide.py
```

Per Constitution Article 12: publishing surfaces are mirrors, not authorities.
"""
    (PUBLISH / "README.md").write_text(readme, encoding=UTF8)
    print(f"  ✓ README.md (publish-mirror notice)")

    print(f"\n  Total in publish surface: {len(list(PUBLISH.rglob('*')))} files")


if __name__ == "__main__":
    main()
