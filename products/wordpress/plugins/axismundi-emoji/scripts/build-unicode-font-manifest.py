#!/usr/bin/env python3
"""Write assets/fonts/noto-color-emoji/manifest.json from the font files beside it.

The manifest is the only thing includes/unicode.php reads about emoji fonts: which file
covers which capability profile, and the unicode-range its @font-face gets. It is generated,
never hand-edited, and this script takes --check (AGENTS.md "Generated files").

Profile *rules* (what a flag is, which sequences are probed) live in PHP,
axismundi_emoji_unicode_profiles(). This script decides only which file serves a profile.

--verify answers a different question from --check: does each file actually shape every
sequence the profile promises into a single glyph? It needs fontTools (with brotli) and
uharfbuzz, and the profile definitions exported from WordPress:

  npx wp-env run cli wp eval "echo wp_json_encode( axismundi_emoji_unicode_profiles() );" > profiles.json
  PYTHONPATH=D:/axismundi-assets/noto-emoji/tools/font-inspect \
    python scripts/build-unicode-font-manifest.py --verify profiles.json

A file that is smaller but merges a flag back into its letters fails --verify; size is not
the acceptance condition.
"""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import re
import sys
from pathlib import Path

PLUGIN = Path(__file__).resolve().parent.parent
FONT_DIR = PLUGIN / "assets" / "fonts" / "noto-color-emoji"
MANIFEST = FONT_DIR / "manifest.json"
REL_DIR = "assets/fonts/noto-color-emoji"

FAMILY = "Noto Color Emoji"

# Build decision: which file serves which profile, and the unicode-range of that face.
# The flags subset retains every RGI country and subdivision flag verified by
# build-noto-color-emoji-flags.py.
PROFILES = {
    "flags-country": {
        "file": "axismundi-noto-colrv1-flags.woff2",
        "unicodeRange": "U+1F1E6-1F1FF",
    },
    "flags-subdivision": {
        "file": "axismundi-noto-colrv1-flags.woff2",
        "unicodeRange": "U+1F3F4, U+E0020-E007F",
    },
    # `font` mode: every RGI emoji from the complete font. No unicode-range: the face is only
    # ever named by wrappers the adapter placed, so there is nothing else it could be chosen for.
    "emoji": {
        "file": "axismundi-noto-colrv1.woff2",
        "unicodeRange": "",
    },
}

# The CSS alias each file loads under. The complete font and a flags subset overlap, so they
# cannot share one @font-face family without the browser choosing, or fetching, both.
ALIASES = {
    "axismundi-noto-colrv1.woff2": "Axismundi Emoji Full",
    "axismundi-noto-colrv1-flags.woff2": "Axismundi Emoji Flags",
}

# The complete font offered in Font Library for installation. It is shipped and verified like
# every other file, but no runtime profile loads it in `auto`: the runtime fallback and the
# font a user installs are separate decisions.
COLLECTION_FILE = "axismundi-noto-colrv1.woff2"

REQUIRED_TABLES = ("COLR", "CPAL", "GSUB", "cmap")


def shipped_files() -> list[str]:
    return sorted({p["file"] for p in PROFILES.values()} | {COLLECTION_FILE})


def read_source() -> dict:
    text = (FONT_DIR / "source.txt").read_text(encoding="utf-8")
    fields = dict(re.findall(r"^([A-Za-z-]+):\s*(.+)$", text, flags=re.M))
    return {
        "project": fields.get("Source", ""),
        "version": fields.get("Version", ""),
        "commit": fields.get("Commit", ""),
        "license": "OFL-1.1",
    }


def build() -> str:
    files = {}
    for name in shipped_files():
        data = (FONT_DIR / name).read_bytes()
        files[f"{REL_DIR}/{name}"] = {
            "alias": ALIASES[name],
            "bytes": len(data),
            "sha256": hashlib.sha256(data).hexdigest(),
        }
    manifest = {
        "schema": 1,
        "generatedBy": "scripts/build-unicode-font-manifest.py",
        "family": FAMILY,
        "source": read_source(),
        "files": files,
        "collectionFile": f"{REL_DIR}/{COLLECTION_FILE}",
        "profiles": {
            pid: {"file": f"{REL_DIR}/{p['file']}", "unicodeRange": p["unicodeRange"]}
            for pid, p in PROFILES.items()
        },
    }
    return json.dumps(manifest, indent="\t", ensure_ascii=True) + "\n"


def verify(profiles_path: Path) -> int:
    try:
        from fontTools.ttLib import TTFont
        import uharfbuzz as hb
    except ImportError as error:
        print(f"--verify needs fontTools and uharfbuzz on PYTHONPATH: {error}")
        return 2

    rules = json.loads(profiles_path.read_text(encoding="utf-8-sig"))
    failures = 0
    for name in shipped_files():
        font = TTFont(FONT_DIR / name)
        missing = [t for t in REQUIRED_TABLES if t not in font]
        family = font["name"].getDebugName(1)
        print(f"{name}: family={family!r} tables-missing={missing or 'none'}")
        failures += len(missing) + (family != FAMILY)
        buffer = io.BytesIO()
        font.flavor = None  # HarfBuzz reads sfnt, not WOFF2.
        font.save(buffer)
        hb_font = hb.Font(hb.Face(hb.Blob(buffer.getvalue())))

        for pid, p in PROFILES.items():
            if p["file"] != name:
                continue
            rule = rules.get(pid, {})
            sequences = rule.get("probe", []) + rule.get("sequences", [])
            if rule.get("match") == "regional-indicator-pair":
                sequences = sequences + [[0x1F1F0, 0x1F1F7]]
            seen = set()
            for seq in sequences:
                key = tuple(seq)
                if key in seen:
                    continue
                seen.add(key)
                buf = hb.Buffer()
                buf.add_codepoints(list(seq))
                buf.guess_segment_properties()
                hb.shape(hb_font, buf)
                gids = [info.codepoint for info in buf.glyph_infos]
                ok = len(gids) == 1 and gids[0] != 0
                failures += not ok
                label = "-".join(f"{cp:X}" for cp in seq)
                print(f"  [{'PASS' if ok else 'FAIL'}] {pid} {label} -> glyphs {gids}")
    print(f"\n== verify: {failures} failure(s) ==")
    return 1 if failures else 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--check", action="store_true", help="fail if the committed manifest is not what this script writes")
    parser.add_argument("--verify", type=Path, metavar="PROFILES_JSON", help="shape every profile sequence with HarfBuzz")
    args = parser.parse_args()

    if args.verify:
        return verify(args.verify)

    output = build()
    if args.check:
        current = MANIFEST.read_text(encoding="utf-8") if MANIFEST.exists() else ""
        if current != output:
            print(f"{MANIFEST} is stale; run scripts/build-unicode-font-manifest.py")
            return 1
        print("manifest is current")
        return 0
    MANIFEST.write_text(output, encoding="utf-8", newline="\n")
    print(f"Wrote {MANIFEST}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
