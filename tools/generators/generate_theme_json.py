#!/usr/bin/env python3
"""
v2_1_build_theme_json_v4.py — Token system as Single Source of Truth.

Decision: tokens.css is the design authority. theme.json is a minimal
WP integration layer (slug registry + lock-down policy). Inspector GUI
limitations (color swatch #000, typography input default) are intentional
and will be replaced by an Axismundi custom color/HCT panel plugin.

What this enables:
  - tokens.css ref→sys 2-tier preserved
  - modern CSS (color-mix, light-dark, oklch, color-contrast) usable in block-styles.css
  - Dark mode via [data-theme="dark"] selector in tokens.css (no style variation needed)
  - block-styles.css uses var(--md-sys-*) directly — full M3 token chain

What this gives up:
  - WP inspector color swatch shows #000 (no var() reverse-resolve)
  - WP typography inspector inputs empty (same reason)
  - Decision: don't care — custom UI will replace inspector for these

Sources:
  core/design-systems/material3/runtime/tokens.css (resolve typescale references for fontFamilies only)

Output:
  products/reference-implementations/ontology-theme-pilot/theme.json
"""

import json
import re
from pathlib import Path

TOKENS_CSS = Path("core/design-systems/material3/runtime/tokens.css")
PILOT = Path("products/reference-implementations/ontology-theme-pilot")


M3_COLOR_ROLES = [
    "primary", "on-primary", "primary-container", "on-primary-container",
    "secondary", "on-secondary", "secondary-container", "on-secondary-container",
    "tertiary", "on-tertiary", "tertiary-container", "on-tertiary-container",
    "error", "on-error", "error-container", "on-error-container",
    "background", "on-background",
    "surface", "on-surface", "surface-variant", "on-surface-variant",
    "surface-bright", "surface-dim",
    "surface-container-lowest", "surface-container-low", "surface-container",
    "surface-container-high", "surface-container-highest",
    "inverse-surface", "inverse-on-surface", "inverse-primary",
    "outline", "outline-variant",
    "shadow", "scrim",
]

M3_TYPESCALE_ROLES = [
    "display-large", "display-medium", "display-small",
    "headline-large", "headline-medium", "headline-small",
    "title-large", "title-medium", "title-small",
    "body-large", "body-medium", "body-small",
    "label-large", "label-medium", "label-small",
]


def parse_tokens_css():
    """Resolve typeface refs for fontFamilies (only place we still need literals)."""
    text = TOKENS_CSS.read_text()
    raw = {}
    pattern = re.compile(r"(--md-[a-z0-9-]+):\s*([^;]+);")
    for line in text.split("\n"):
        m = pattern.search(line)
        if m and m.group(1) not in raw:
            raw[m.group(1)] = m.group(2).strip()
    return raw


def humanize(slug):
    return " ".join(w.capitalize() for w in slug.split("-"))


def main():
    tokens = parse_tokens_css()

    palette = [
        {
            "slug": role,
            "name": humanize(role),
            "color": f"var(--md-sys-color-{role})",
        }
        for role in M3_COLOR_ROLES
    ]

    fontsizes = [
        {
            "slug": role,
            "name": humanize(role),
            "size": f"var(--md-sys-typescale-{role}-size)",
        }
        for role in M3_TYPESCALE_ROLES
    ]

    shadow_presets = [
        {
            "slug": f"elevation-{i}",
            "name": f"Elevation level {i}",
            "shadow": f"var(--md-sys-elevation-shadow-level{i})",
        }
        for i in range(6)
    ]

    # Resolve typeface CSS values (these need literals — fontFamily string can't be a var() that points to a string with quotes)
    brand = tokens.get("--md-ref-typeface-brand", "system-ui, sans-serif")
    plain = tokens.get("--md-ref-typeface-plain", "system-ui, sans-serif")
    serif = tokens.get("--md-ref-typeface-serif", "Georgia, serif")
    mono = tokens.get("--md-ref-typeface-mono", "ui-monospace, monospace")

    theme_json = {
        "$schema": "https://schemas.wp.org/trunk/theme.json",
        "version": 3,
        "settings": {
            "_axismundi_provenance": (
                "v2.1a-P0.5-v4: tokens.css is SoT. theme.json is minimal WP integration layer "
                "(slug registry + lock-down). Inspector GUI limits are intentional — to be "
                "replaced by Axismundi custom color/HCT panel plugin. Modern CSS preserved in block-styles.css."
            ),
            "appearanceTools": True,
            "useRootPaddingAwareAlignments": True,
            "layout": {"contentSize": "640px", "wideSize": "1280px"},
            "color": {
                "background": True, "text": True, "link": True, "heading": True,
                "button": True, "caption": True,
                "palette": palette,
                "duotone": [],
                "custom": False,
                "customGradient": False,
                "defaultPalette": False,
                "defaultGradients": False,
                "defaultDuotone": False,
            },
            "typography": {
                "fontSizes": fontsizes,
                "fluid": False,
                "fontFamilies": [
                    {"slug": "brand", "name": "Brand (Roboto Flex + Noto Sans KR)", "fontFamily": brand},
                    {"slug": "plain", "name": "Plain", "fontFamily": plain},
                    {"slug": "serif", "name": "Serif", "fontFamily": serif},
                    {"slug": "mono", "name": "Monospace", "fontFamily": mono},
                ],
                "lineHeight": True,
                "fontStyle": True,
                "fontWeight": True,
                "letterSpacing": True,
                "textDecoration": True,
                "textTransform": True,
                "dropCap": False,
                "customFontSize": False,
            },
            "spacing": {
                "spacingScale": {
                    "operator": "*", "increment": 1.5, "steps": 7,
                    "mediumStep": 1.5, "unit": "rem",
                },
                "padding": True, "margin": True, "blockGap": True,
                "units": ["px", "em", "rem", "%", "vh", "vw"],
            },
            "shadow": {"presets": shadow_presets, "defaultPresets": False},
            "border": {"color": True, "radius": True, "style": True, "width": True},
            "dimensions": {"aspectRatio": True, "minHeight": True},
            "position": {"sticky": True},
        },
        "styles": {
            # Body baseline — points to M3 body-large via token chain
            "color": {
                "background": "var(--md-sys-color-background)",
                "text": "var(--md-sys-color-on-background)",
            },
            "typography": {
                "fontFamily": "var(--md-sys-typescale-body-large-font)",
                "fontSize": "var(--md-sys-typescale-body-large-size)",
                "lineHeight": "var(--md-sys-typescale-body-large-line-height)",
                "fontWeight": "var(--md-sys-typescale-body-large-weight)",
                "letterSpacing": "var(--md-sys-typescale-body-large-tracking)",
            },
            "spacing": {"blockGap": "var:preset|spacing|40"},
            # styles.elements deliberately minimal — base.css §3 handles heading mapping.
            # Only block-level concerns here.
            "elements": {
                "link": {"color": {"text": "var(--md-sys-color-primary)"}},
                "button": {
                    "color": {
                        "background": "var(--md-sys-color-primary)",
                        "text": "var(--md-sys-color-on-primary)",
                    },
                    "border": {"radius": "var(--md-sys-shape-corner-full)"},
                },
            },
        },
        "customTemplates": [],
        "templateParts": [
            {"name": "header", "title": "Header (App Bar)", "area": "header"},
            {"name": "footer", "title": "Footer", "area": "footer"},
            {"name": "sidebar", "title": "Sidebar (Navigation rail)", "area": "uncategorized"},
        ],
    }

    (PILOT / "theme.json").write_text(json.dumps(theme_json, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {PILOT}/theme.json ({(PILOT / 'theme.json').stat().st_size:,} bytes)")

    # Remove style variation — tokens.css [data-theme="dark"] handles dark mode
    dark = PILOT / "styles" / "m3-dark.json"
    if dark.exists():
        dark.unlink()
        print(f"Removed {dark}")
    # Keep styles/ dir but empty for now (other variations may go here)

    print(f"\n=== Summary ===")
    print(f"  palette:     {len(palette)} entries (var() chain to tokens.css)")
    print(f"  fontSizes:   {len(fontsizes)} entries (var() chain to tokens.css)")
    print(f"  fontFamilies: 4 entries (literal — Korean stack included)")
    print(f"  shadow:      {len(shadow_presets)} entries (var() chain)")
    print(f"  styles.elements: minimal (link + button only — base.css handles h1-h6)")
    print(f"  dark mode:   via tokens.css [data-theme='dark'] selector (no style variation)")


if __name__ == "__main__":
    main()
