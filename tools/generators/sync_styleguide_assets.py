#!/usr/bin/env python3
"""
sync_styleguide_assets.py — bring the products' design system into the site.

The style guide does not keep its own copy of these files in git. They already
exist under products/wordpress/, and a committed third copy (after core/ and
the products' subsets) would go stale the moment a font is re-subset. So this
copies them into the style guide's assets at build time, and generates the
@font-face CSS from the same declarations the products ship.

That is deliberately narrower than the pilot asset bridge this repository used
to have. Fonts are binary inputs that nobody edits in place, so there is no
second source of truth to drift from; that bridge copied authored CSS into a
second implementation, which is why it drifted.

Two sources, because the products split them on purpose:

  theme    Roboto Flex / Mono — Latin, declared in theme.json
  plugin   Noto Sans KR       — Hangul, an optional regional provider

The theme's stacks read `var(--axismundi-cjk-sans, system-ui)` and each
regional plugin fills that slot under `:lang()`. Reproducing both here means
the style guide renders in the same fonts a site running these products does,
rather than a stack invented for the documentation.

Colour comes in the same way, but by copy rather than by generation. Unlike the
typeface layer - which the shipped theme does not carry at all - the theme's
colour token CSS is current, correct, and the actual palette these products
render in. A style guide that documented a separately transcribed palette would
be documenting something nobody ships. The four files are self-contained: no
WordPress selectors, no external references, and their dark blocks already
cover a root with no data-theme attribute, so they work here unchanged.

Output (all git-ignored, and the script refuses to finish if any of it is not):
  products/styleguide/assets/fonts/<family>/<file>.woff2
  products/styleguide/assets/css/fonts.css
  products/styleguide/assets/css/<token file>.css

Run before `jekyll build` or `jekyll serve`.
"""

from __future__ import annotations

import json
import re
import shutil
import subprocess
import sys
from pathlib import Path

UTF8 = "utf-8"
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding=UTF8)
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding=UTF8)

ROOT = Path(__file__).resolve().parent.parent.parent
THEME = ROOT / "products/wordpress/themes/axismundi"
KOREAN = ROOT / "products/wordpress/plugins/axismundi-korean-font-provider"
STYLEGUIDE = ROOT / "products/styleguide"

FONT_OUT = STYLEGUIDE / "assets/fonts"
CSS_OUT = STYLEGUIDE / "assets/css/fonts.css"
# The same depth the theme keeps them at: assets/styles/ there, assets/css/
# here. That is not only tidiness - it is why every file copies byte for byte.
# icons.css reaches its font with ../fonts/, which lands on assets/fonts/ from
# either location, so nothing has to be rewritten on the way in.
TOKEN_OUT = STYLEGUIDE / "assets/css"

# The theme's token layers, copied verbatim and in cascade order. tokens.ref.css
# holds the literal palette; the colour files map roles onto it; elevation
# carries the shadow formulas plus the two scheme-neutral colour roles (shadow,
# scrim) that deliberately do not live in the light/dark files. shape, motion
# and state are single :root blocks with nothing else in them.
#
# icons.css is here rather than with the fonts because it declares its own
# @font-face. Adding Material Symbols to THEME_FAMILIES as well would emit a
# second, competing declaration for the same family.
#
# These land beside the files this product authors, because at the point of use
# they are one ordered cascade and splitting them by where they came from would
# invent a category the consumer does not have. tokens.ref.css and
# tokens.ref.typeface.css are two halves of the same layer. Provenance is
# carried by .gitignore, by each file's own header, and by the check below.
PRODUCT_TOKENS = (
    "tokens.ref.css",
    "tokens.sys.color.light.css",
    "tokens.sys.color.dark.css",
    "tokens.sys.shape.css",
    "tokens.sys.motion.css",
    "tokens.sys.elevation.css",
    "tokens.sys.state.css",
    "icons.css",
)

# Fonts a copied stylesheet asks for, rather than one theme.json declares.
TOKEN_FONTS = (
    "assets/fonts/material-symbols-outlined/material-symbols-outlined.woff2",
)

# Which of the theme's families the style guide actually uses.
#
# roboto-serif is left out because no page sets a serif face yet, and
# material-symbols-outlined because it is 3.8 MB and nothing here documents
# icons. Adding either is one entry; both are shipped by the theme already.
THEME_FAMILIES = ("roboto-flex", "roboto-mono")

# theme.json fontFace key -> CSS descriptor.
FACE_DESCRIPTORS = {
    "fontFamily": "font-family",
    "fontStyle": "font-style",
    "fontWeight": "font-weight",
    "fontStretch": "font-stretch",
    "fontDisplay": "font-display",
    "fontVariationSettings": "font-variation-settings",
    "unicodeRange": "unicode-range",
}
DESCRIPTOR_ORDER = (
    "font-family",
    "font-style",
    "font-weight",
    "font-stretch",
    "font-display",
    "font-variation-settings",
    "unicode-range",
)


def quote_family(value: str) -> str:
    """theme.json stores the family bare; @font-face wants it quoted."""
    value = value.strip()
    if value.startswith(('"', "'")):
        return value
    return f'"{value}"'


def copy_font(src: Path, family_dir: str) -> str:
    """Copy one font file into the style guide and return its CSS-relative url."""
    dst_dir = FONT_OUT / family_dir
    dst_dir.mkdir(parents=True, exist_ok=True)
    dst = dst_dir / src.name
    shutil.copy2(src, dst)
    return f"../fonts/{family_dir}/{src.name}"


def theme_faces() -> tuple[list[str], int, int]:
    """Emit @font-face blocks for the selected theme families."""
    theme_json = json.loads((THEME / "theme.json").read_text(encoding=UTF8))
    families = theme_json["settings"]["typography"]["fontFamilies"]
    by_slug = {f.get("slug"): f for f in families}

    blocks: list[str] = []
    copied = 0
    total_bytes = 0

    for slug in THEME_FAMILIES:
        family = by_slug.get(slug)
        if family is None:
            raise SystemExit(f"theme.json has no fontFamily with slug {slug!r}")

        for face in family.get("fontFace") or []:
            srcs = face.get("src") or []
            if isinstance(srcs, str):
                srcs = [srcs]

            urls = []
            for entry in srcs:
                if not entry.startswith("file:./"):
                    raise SystemExit(f"{slug}: unexpected non-file src {entry!r}")
                rel = entry[len("file:./"):]
                path = THEME / rel
                if not path.is_file():
                    raise SystemExit(f"{slug}: theme.json points at a missing file: {rel}")
                total_bytes += path.stat().st_size
                copied += 1
                urls.append(copy_font(path, Path(rel).parent.name))

            declared = {}
            for key, descriptor in FACE_DESCRIPTORS.items():
                if key in face:
                    value = face[key]
                    declared[descriptor] = quote_family(value) if descriptor == "font-family" else value

            lines = ["@font-face {"]
            for descriptor in DESCRIPTOR_ORDER:
                if descriptor in declared:
                    lines.append(f"\t{descriptor}: {declared[descriptor]};")
            src_value = ", ".join(f'url("{u}") format("woff2")' for u in urls)
            lines.append(f"\tsrc: {src_value};")
            lines.append("}")
            blocks.append("\n".join(lines))

    return blocks, copied, total_bytes


def korean_provider() -> tuple[list[str], int, int]:
    """Reuse the provider's own stylesheet, rewritten for this asset layout.

    Only the Sans face is carried: no page here sets a serif face, and the
    Serif KR subset is another 2.1 MB. The `:lang(ko)` rule that fills the
    theme's CJK slot is kept verbatim, minus the serif variable it would
    otherwise point at a face that is not here.
    """
    css = (KOREAN / "assets/styles/fonts.css").read_text(encoding=UTF8)

    lang_match = re.search(r":lang\(ko\)\s*\{[^}]*\}", css)
    if lang_match is None:
        raise SystemExit("korean provider: could not find the :lang(ko) block")
    lang_block = re.sub(r"\n\s*--axismundi-cjk-serif:[^;]*;", "", lang_match.group(0))

    sans_match = re.search(
        r"@font-face\s*\{[^}]*?font-family:\s*\"Noto Sans KR\"[^}]*\}", css, re.S
    )
    if sans_match is None:
        raise SystemExit("korean provider: could not find the Noto Sans KR @font-face")

    src = KOREAN / "assets/fonts/noto-sans-kr/axismundi-noto-sans-kr.woff2"
    if not src.is_file():
        raise SystemExit(f"korean provider: missing font file {src}")
    url = copy_font(src, "noto-sans-kr")

    face = re.sub(r'src:\s*url\("[^"]*"\)', f'src: url("{url}")', sans_match.group(0))

    return [lang_block, face], 1, src.stat().st_size


def assert_ignored(paths: list[Path]) -> None:
    """Refuse to leave a synced copy where git would pick it up.

    These files sit beside the ones this product authors, so nothing about the
    directory says which are copies. That is fine while .gitignore knows, and a
    quiet disaster the day someone adds an entry to PRODUCT_TOKENS and forgets
    to. Checking here turns that into a failed build instead of a committed
    second copy that stops following the theme.
    """
    # NUL-separated in and out. Text mode would translate the separators to
    # \r\n on Windows, git would read the \r as part of the last path segment,
    # and every rule that names a file rather than a directory would miss - a
    # guard that fails builds it should pass.
    payload = b"\0".join(p.as_posix().encode(UTF8) for p in paths)
    try:
        result = subprocess.run(
            ["git", "check-ignore", "-z", "--stdin"],
            input=payload,
            capture_output=True,
            cwd=ROOT,
        )
    except FileNotFoundError:
        print("  (git not available - skipped the ignore check)")
        return

    ignored = {chunk.decode(UTF8) for chunk in result.stdout.split(b"\0") if chunk}
    missing = [p for p in paths if p.as_posix() not in ignored]
    if missing:
        listing = "\n".join(f"    {p.relative_to(ROOT).as_posix()}" for p in missing)
        raise SystemExit(
            "these synced copies are not git-ignored, and would be committed as a\n"
            "second source of truth:\n" + listing + "\n"
            "  Add them to .gitignore."
        )


def product_tokens() -> tuple[int, int, int, int]:
    """Copy the theme's token layers in, verbatim.

    Written beside this product's own stylesheets rather than into a
    subdirectory, so the cascade reads as one sequence and every file keeps the
    relative depth it has in the theme. Only the known copies are removed first;
    the directory also holds committed files.
    """
    TOKEN_OUT.mkdir(parents=True, exist_ok=True)
    for name in PRODUCT_TOKENS:
        stale = TOKEN_OUT / name
        if stale.exists():
            stale.unlink()

    copied = 0
    total_bytes = 0
    for name in PRODUCT_TOKENS:
        src = THEME / "assets/styles" / name
        if not src.is_file():
            raise SystemExit(f"theme is missing {name}; expected at {src.relative_to(ROOT).as_posix()}")
        shutil.copy2(src, TOKEN_OUT / name)
        copied += 1
        total_bytes += src.stat().st_size

    fonts = 0
    font_bytes = 0
    for rel in TOKEN_FONTS:
        src = THEME / rel
        if not src.is_file():
            raise SystemExit(f"theme is missing {rel}; a copied stylesheet references it")
        copy_font(src, Path(rel).parent.name)
        fonts += 1
        font_bytes += src.stat().st_size

    return copied, total_bytes, fonts, font_bytes


def main() -> int:
    for required in (THEME / "theme.json", KOREAN / "assets/styles/fonts.css"):
        if not required.is_file():
            print(f"missing source: {required.relative_to(ROOT).as_posix()}")
            return 1

    if FONT_OUT.exists():
        shutil.rmtree(FONT_OUT)

    theme_blocks, theme_count, theme_bytes = theme_faces()
    korean_blocks, korean_count, korean_bytes = korean_provider()

    header = (
        "/* ============================================================\n"
        " * GENERATED - do not edit.\n"
        " *\n"
        " * Written by tools/generators/sync_styleguide_assets.py from the\n"
        " * declarations the products ship:\n"
        " *\n"
        " *   products/wordpress/themes/axismundi/theme.json\n"
        " *   products/wordpress/plugins/axismundi-korean-font-provider/\n"
        " *     assets/styles/fonts.css\n"
        " *\n"
        " * The font files beside it are copies, not sources. Neither this file\n"
        " * nor they are committed; run the generator before building.\n"
        " * ============================================================ */\n"
    )

    CSS_OUT.parent.mkdir(parents=True, exist_ok=True)
    CSS_OUT.write_text(
        header + "\n" + "\n\n".join(theme_blocks + korean_blocks) + "\n",
        encoding=UTF8,
        newline="\n",
    )

    token_count, token_bytes, icon_fonts, icon_bytes = product_tokens()

    assert_ignored(
        [CSS_OUT]
        + [TOKEN_OUT / name for name in PRODUCT_TOKENS]
        + sorted(FONT_OUT.rglob("*.woff2"))
    )

    total = (theme_bytes + korean_bytes + icon_bytes) / 1024 / 1024
    print(f"  fonts    theme {theme_count} face file(s) {theme_bytes / 1024 / 1024:.1f} MB, "
          f"korean {korean_count} ({korean_bytes / 1024 / 1024:.1f} MB), "
          f"icons {icon_fonts} ({icon_bytes / 1024 / 1024:.1f} MB)")
    print(f"           -> {FONT_OUT.relative_to(ROOT).as_posix()} ({total:.1f} MB)")
    print(f"           -> {CSS_OUT.relative_to(ROOT).as_posix()}")
    print(f"  tokens   {token_count} file(s) from the theme, {token_bytes / 1024:.1f} KB")
    print(f"           -> {TOKEN_OUT.relative_to(ROOT).as_posix()}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
