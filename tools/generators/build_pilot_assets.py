#!/usr/bin/env python3
"""
build_pilot_assets.py — Build the Axismundi Pilot asset bridge.

Copies the validated Axismundi lab CSS plus Material 3 font/icon assets into
the v3.6.0 WordPress Pilot theme so wp-env and future distribution checks can
run without repository-relative asset URLs.
"""

from __future__ import annotations

import shutil
import sys
from pathlib import Path

UTF8 = "utf-8"
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding=UTF8)
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding=UTF8)

ROOT = Path(__file__).resolve().parent.parent.parent
LAB = ROOT / "products/reference-implementations/axismundi-lab"
PILOT = ROOT / "products/reference-implementations/axismundi-pilot"
M3_ASSETS = ROOT / "core/design-systems/material3/assets"

SOURCE_STYLES = LAB / "stylesheets"
PILOT_ASSETS = PILOT / "assets"
PILOT_BRIDGE = PILOT / "bridge"
PILOT_STYLES = PILOT_ASSETS / "styles"
PILOT_SCRIPTS = PILOT_ASSETS / "scripts"
PILOT_FONTS = PILOT_ASSETS / "fonts"
PILOT_ICONS = PILOT_ASSETS / "icons"
PILOT_MEDIA = PILOT_ASSETS / "media"

STYLE_ORDER = (
    "fonts.css",
    "tokens.ref.css",
    "tokens.sys.light.css",
    "tokens.sys.core.css",
    "tokens.comp.css",
    "tokens.sys.dark.css",
    "wp-preset.bridge.css",
    "wp-custom.bridge.css",
    "tokens.css",
    "base.css",
    "icons.css",
    "components.css",
    "blocks.css",
    "prose.css",
)

BRIDGE_STYLES = (
    "pilot-block-bridge.css",
)

BRIDGE_SCRIPTS = (
    "pilot-block-bridge.js",
)

FONT_PATH_FROM_LAB = "../../../../core/design-systems/material3/assets/fonts/"
ICON_PATH_FROM_LAB = "../../../../core/design-systems/material3/assets/icons/"
FONT_PATH_FROM_PILOT = "../fonts/"
ICON_PATH_FROM_PILOT = "../icons/"


def reset_dir(path: Path) -> None:
    if path.exists():
        shutil.rmtree(path)
    path.mkdir(parents=True, exist_ok=True)


def copy_styles() -> int:
    reset_dir(PILOT_STYLES)

    copied = 0
    for name in STYLE_ORDER:
        source = SOURCE_STYLES / name
        target = PILOT_STYLES / name

        if not source.exists():
            raise FileNotFoundError(f"Required style missing: {source}")

        text = source.read_text(encoding=UTF8)
        if name == "fonts.css":
            text = text.replace(FONT_PATH_FROM_LAB, FONT_PATH_FROM_PILOT)
            text = text.replace(ICON_PATH_FROM_LAB, ICON_PATH_FROM_PILOT)

        target.write_text(text, encoding=UTF8)
        copied += 1

    for name in BRIDGE_STYLES:
        source = PILOT_BRIDGE / name
        target = PILOT_STYLES / name

        if not source.exists():
            raise FileNotFoundError(f"Required Pilot bridge style missing: {source}")

        target.write_text(source.read_text(encoding=UTF8), encoding=UTF8)
        copied += 1

    return copied


def copy_scripts() -> int:
    reset_dir(PILOT_SCRIPTS)

    copied = 0
    for name in BRIDGE_SCRIPTS:
        source = PILOT_BRIDGE / name
        target = PILOT_SCRIPTS / name

        if not source.exists():
            raise FileNotFoundError(f"Required Pilot bridge script missing: {source}")

        target.write_text(source.read_text(encoding=UTF8), encoding=UTF8)
        copied += 1

    return copied


def copy_asset_tree(source: Path, target: Path) -> int:
    if not source.exists():
        raise FileNotFoundError(f"Required asset directory missing: {source}")

    reset_dir(target)
    copied = 0
    for file_path in sorted(source.rglob("*")):
        if not file_path.is_file():
            continue
        relative = file_path.relative_to(source)
        destination = target / relative
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(file_path, destination)
        copied += 1
    return copied


def assert_rewrites() -> None:
    fonts_css = PILOT_STYLES / "fonts.css"
    text = fonts_css.read_text(encoding=UTF8)

    disallowed = (
        "../../../../core/design-systems/material3/assets/",
        "../../../../../core/design-systems/material3/assets/",
        "core/design-systems/material3/assets/",
    )
    for needle in disallowed:
        if needle in text:
            raise RuntimeError(f"Unrewritten asset path remains in fonts.css: {needle}")

    required = (
        "../fonts/roboto-flex/axismundi-roboto-flex.woff2",
        "../fonts/noto-sans-kr/axismundi-noto-sans-kr.woff2",
        "../fonts/roboto-mono/axismundi-roboto-mono.woff2",
        "../icons/material-symbols-rounded/material-symbols-rounded.woff2",
    )
    for needle in required:
        if needle not in text:
            raise RuntimeError(f"Expected Pilot-local asset path missing: {needle}")


def main() -> int:
    print("=== Building Axismundi Pilot assets ===\n")
    print(f"  Source styles: {SOURCE_STYLES.relative_to(ROOT)}")
    print(f"  Source assets: {M3_ASSETS.relative_to(ROOT)}")
    print(f"  Target theme:  {PILOT.relative_to(ROOT)}\n")

    if not PILOT.exists():
        print(f"  ✗ Pilot theme not found: {PILOT}", file=sys.stderr)
        return 1

    style_count = copy_styles()
    script_count = copy_scripts()
    font_count = copy_asset_tree(M3_ASSETS / "fonts", PILOT_FONTS)
    icon_count = copy_asset_tree(M3_ASSETS / "icons", PILOT_ICONS)
    media_count = copy_asset_tree(ROOT / "assets/media", PILOT_MEDIA)
    assert_rewrites()

    print(f"  ✓ assets/styles/ ({style_count} files)")
    print(f"  ✓ assets/scripts/ ({script_count} files)")
    print(f"  ✓ assets/fonts/  ({font_count} files)")
    print(f"  ✓ assets/icons/  ({icon_count} files)")
    print(f"  ✓ assets/media/  ({media_count} files)")
    print("  ✓ fonts.css paths rewritten to Pilot-local assets")
    print("\nDone.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
