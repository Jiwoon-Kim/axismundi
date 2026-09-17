#!/usr/bin/env python3
"""Build this provider's Noto CJK subsets and their manifest from pinned upstream files.

    python scripts/build-noto-cjk.py                  subset the sources, write fonts + manifest
    python scripts/build-noto-cjk.py --check          manifest, source.txt and CSS match; no sources needed
    python scripts/build-noto-cjk.py --verify         compare each subset with its source (needs sources)
    python scripts/build-noto-cjk.py --rebuild-check  rebuild in a temp dir and compare bytes (needs sources)

Input, written by hand: scripts/noto-cjk.json. It pins the upstream repository commit, each
source file with its size and SHA-256, and the unicode-range the provider applies.
Generated, never hand-edited: assets/fonts/manifest.json, each family's source.txt, and the
`?ver=` of each font URL in assets/styles/fonts.css (AGENTS.md "Generated files"). The
stylesheet is otherwise written by hand; the query makes browsers fetch a rebuilt file
instead of keeping the one they cached under the same URL.

The unicode-range is the provider's policy, not the file's coverage. The subset keeps exactly
the codepoints of that range the source has, so a face never carries glyphs its @font-face
would not use, and never anything the source does not have. The range is written in whole
Unicode blocks, while Noto's regional fonts cover only part of some blocks (JP and TC lack
ideographs outside their regional sets); --verify counts those absent codepoints for
information only. A codepoint the source has inside the range but the subset lost is an error.

Sources live outside the repository, by default in the directory named in noto-cjk.json:
download each file from the pinned commit and check its SHA-256 before building.

This file is identical in the Korean, Japanese and Traditional Chinese providers; --check
fails when a sibling copy differs. Needs fontTools with brotli.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import tempfile
from pathlib import Path

PLUGIN = Path(__file__).resolve().parent.parent
CONFIG = PLUGIN / "scripts" / "noto-cjk.json"
FONT_DIR = PLUGIN / "assets" / "fonts"
MANIFEST = FONT_DIR / "manifest.json"
CSS = PLUGIN / "assets" / "styles" / "fonts.css"
SIBLINGS = (
    "axismundi-korean-font-provider",
    "axismundi-japanese-font-provider",
    "axismundi-traditional-chinese-font-provider",
)
NON_CHARACTERS = ("Cn", "Cs", "Co")


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def parse_range(text: str) -> set[int]:
    codepoints: set[int] = set()
    for part in text.split(","):
        part = part.strip()
        if not part.upper().startswith("U+"):
            raise SystemExit(f"not a unicode-range item: {part!r}")
        start, _, end = part[2:].partition("-")
        codepoints.update(range(int(start, 16), int(end or start, 16) + 1))
    return codepoints


def css_range(css: str, family: str) -> str:
    match = re.search(r'font-family:\s*"' + re.escape(family) + r'";[^}]*?unicode-range:\s*([^;]+);', css, re.S)
    return match.group(1).strip() if match else ""


def stamp_css(css: str, manifest: dict) -> str:
    """Set each family's font URL query to the first 12 hex digits of its SHA-256."""
    for slug, entry in manifest["families"].items():
        name = f"axismundi-{slug}.woff2"
        pattern = r'(url\("\.\./fonts/' + re.escape(slug) + "/" + re.escape(name) + r')(\?ver=[0-9a-f]+)?("\))'
        css, count = re.subn(pattern, lambda m: f"{m.group(1)}?ver={entry['sha256'][:12]}{m.group(3)}", css)
        if count != 1:
            raise SystemExit(f"fonts.css: expected one url() for {name}, found {count}")
    return css


def output_path(slug: str) -> Path:
    return FONT_DIR / slug / f"axismundi-{slug}.woff2"


def source_path(config: dict, family: dict, source_dir: str | None) -> Path:
    base = Path(source_dir or config["source"]["directory"])
    return base / Path(family["source"]).name


def load_config() -> dict:
    return json.loads(CONFIG.read_text(encoding="utf-8"))


def describe(path: Path) -> dict:
    from fontTools.ttLib import TTFont

    font = TTFont(path, lazy=True)
    axes = [
        {"tag": axis.axisTag, "min": axis.minValue, "default": axis.defaultValue, "max": axis.maxValue}
        for axis in font["fvar"].axes
    ] if "fvar" in font else []
    return {
        "fontVersion": font["name"].getDebugName(5),
        "axes": axes,
        "codepoints": len(font.getBestCmap()),
    }


def manifest_for(config: dict, built_with: dict) -> dict:
    families = {}
    for family in config["families"]:
        out = output_path(family["slug"])
        info = describe(out)
        families[family["slug"]] = {
            "name": family["name"],
            "file": out.relative_to(PLUGIN).as_posix(),
            "bytes": out.stat().st_size,
            "sha256": sha256(out),
            "codepoints": info["codepoints"],
            "fontVersion": info["fontVersion"],
            "axes": info["axes"],
            "source": {
                "path": family["source"],
                "bytes": family["sourceBytes"],
                "sha256": family["sourceSha256"],
            },
        }
    return {
        "schema": 1,
        "generatedBy": "scripts/build-noto-cjk.py",
        "source": {
            "repository": config["source"]["repository"],
            "commit": config["source"]["commit"],
            "license": config["source"]["license"],
        },
        "unicodeRange": config["unicodeRange"],
        "builtWith": built_with,
        "families": families,
    }


def source_txt(config: dict, family: dict, entry: dict) -> str:
    commit = config["source"]["commit"]
    url_path = family["source"].replace("[", "%5B").replace("]", "%5D")
    tool = ", ".join(f"{k} {v}" for k, v in sorted(config.get("_built_with", {}).items()))
    return (
        f"Source: {config['source']['repository']}/blob/{commit}/{url_path}\n"
        f"Upstream commit: {commit}\n"
        f"Input: {Path(family['source']).name} ({family['sourceBytes']} bytes, SHA-256 {family['sourceSha256']})\n"
        f"Font version: {entry['fontVersion']}\n"
        f"Processing: scripts/build-noto-cjk.py ({tool}) - subset to the unicode-range below, "
        "all layout features and names kept, variation axes kept, WOFF2.\n"
        f"unicode-range: {config['unicodeRange']}\n"
        f"Output: {entry['file']} ({entry['bytes']} bytes, SHA-256 {entry['sha256']}, {entry['codepoints']} codepoints)\n"
        "License: SIL Open Font License 1.1; see OFL.txt.\n"
    )


def render_json(data: dict) -> str:
    return json.dumps(data, indent="\t", ensure_ascii=False) + "\n"


def tool_versions() -> dict:
    import brotli
    import fontTools

    return {"fontTools": fontTools.version, "brotli": getattr(brotli, "__version__", "unknown")}


def subset(src: Path, out: Path, codepoints: set[int]) -> None:
    from fontTools import subset as ft_subset
    from fontTools.ttLib import TTFont

    options = ft_subset.Options()
    options.flavor = "woff2"
    options.layout_features = ["*"]
    options.name_IDs = ["*"]
    options.name_legacy = True
    options.name_languages = ["*"]
    options.notdef_outline = True
    # Keep the source's own timestamps so the same input gives the same bytes.
    font = TTFont(src, recalcTimestamp=False)
    subsetter = ft_subset.Subsetter(options)
    subsetter.populate(unicodes=codepoints)
    subsetter.subset(font)
    out.parent.mkdir(parents=True, exist_ok=True)
    ft_subset.save_font(font, str(out), options)


def check_source(config: dict, family: dict, source_dir: str | None) -> Path:
    src = source_path(config, family, source_dir)
    if not src.is_file():
        raise SystemExit(f"missing source {src}; download {family['source']} at {config['source']['commit']}")
    if sha256(src) != family["sourceSha256"] or src.stat().st_size != family["sourceBytes"]:
        raise SystemExit(f"source {src} does not match noto-cjk.json")
    return src


def write_generated(config: dict, manifest: dict) -> None:
    MANIFEST.write_text(render_json(manifest), encoding="utf-8", newline="\n")
    CSS.write_text(stamp_css(CSS.read_text(encoding="utf-8"), manifest), encoding="utf-8", newline="\n")
    config = dict(config, _built_with=manifest["builtWith"])
    for family in config["families"]:
        entry = manifest["families"][family["slug"]]
        (FONT_DIR / family["slug"] / "source.txt").write_text(source_txt(config, family, entry), encoding="utf-8", newline="\n")


def build(source_dir: str | None) -> int:
    config = load_config()
    codepoints = parse_range(config["unicodeRange"])
    for family in config["families"]:
        src = check_source(config, family, source_dir)
        subset(src, output_path(family["slug"]), codepoints)
        print(f"built {output_path(family['slug']).relative_to(PLUGIN)} ({output_path(family['slug']).stat().st_size} bytes)")
    write_generated(config, manifest_for(config, tool_versions()))
    print(f"wrote {MANIFEST.relative_to(PLUGIN)}")
    return 0


def check() -> int:
    config = load_config()
    problems = []
    if not MANIFEST.is_file():
        return print("manifest.json missing; run build", file=sys.stderr) or 1
    current = json.loads(MANIFEST.read_text(encoding="utf-8"))
    expected = manifest_for(config, current.get("builtWith", {}))
    if render_json(expected) != MANIFEST.read_text(encoding="utf-8"):
        problems.append("manifest.json does not describe the font files and noto-cjk.json")
    cfg = dict(config, _built_with=current.get("builtWith", {}))
    css = CSS.read_text(encoding="utf-8")
    if stamp_css(css, expected) != css:
        problems.append("fonts.css font URLs do not carry the current file hashes")
    for family in config["families"]:
        entry = expected["families"][family["slug"]]
        txt = FONT_DIR / family["slug"] / "source.txt"
        if not txt.is_file() or txt.read_text(encoding="utf-8") != source_txt(cfg, family, entry):
            problems.append(f"{txt.relative_to(PLUGIN)} is out of date")
        if css_range(css, family["name"]) != config["unicodeRange"]:
            problems.append(f"fonts.css unicode-range for {family['name']} differs from noto-cjk.json")
    me = Path(__file__).read_bytes()
    for sibling in SIBLINGS:
        other = PLUGIN.parent / sibling / "scripts" / "build-noto-cjk.py"
        if other.is_file() and other.read_bytes() != me:
            problems.append(f"{sibling}/scripts/build-noto-cjk.py differs from this copy")
    for problem in problems:
        print(problem, file=sys.stderr)
    if not problems:
        print("fonts, manifest and source notes are up to date")
    return 1 if problems else 0


def verify(source_dir: str | None) -> int:
    import unicodedata

    from fontTools.ttLib import TTFont

    config = load_config()
    declared = parse_range(config["unicodeRange"])
    failed = False
    for family in config["families"]:
        src = check_source(config, family, source_dir)
        source_font = TTFont(src, lazy=True)
        subset_font = TTFont(output_path(family["slug"]), lazy=True)
        in_source = set(source_font.getBestCmap()) & declared
        in_subset = set(subset_font.getBestCmap())
        lost = sorted(in_source - in_subset)
        extra = sorted(in_subset - declared)
        lacking = [c for c in declared - in_source if unicodedata.category(chr(c)) not in NON_CHARACTERS]
        same_axes = describe(src)["axes"] == describe(output_path(family["slug"]))["axes"]
        same_version = source_font["name"].getDebugName(5) == subset_font["name"].getDebugName(5)
        ok = not lost and not extra and same_axes and same_version
        failed = failed or not ok
        print(
            f"{'PASS' if ok else 'FAIL'} {family['name']}: {len(in_subset)} codepoints, "
            f"lost from source {len(lost)}, outside range {len(extra)}, axes kept {same_axes}, version kept {same_version}; "
            f"range codepoints absent from the source itself: {len(lacking)} (the unicode-range is block-wide and Noto's regional font does not cover every character in those blocks; not in the subset, not an error)"
        )
        if lost:
            print("  lost:", " ".join(f"U+{c:04X}" for c in lost[:20]))
        if extra:
            print("  outside range:", " ".join(f"U+{c:04X}" for c in extra[:20]))
    return 1 if failed else 0


def rebuild_check(source_dir: str | None) -> int:
    config = load_config()
    codepoints = parse_range(config["unicodeRange"])
    failed = False
    with tempfile.TemporaryDirectory() as tmp:
        for family in config["families"]:
            src = check_source(config, family, source_dir)
            out = Path(tmp) / f"{family['slug']}.woff2"
            subset(src, out, codepoints)
            same = sha256(out) == sha256(output_path(family["slug"]))
            failed = failed or not same
            print(f"{'PASS' if same else 'FAIL'} {family['name']}: rebuild {'matches' if same else 'differs from'} the committed file")
    return 1 if failed else 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--check", action="store_true")
    mode.add_argument("--verify", action="store_true")
    mode.add_argument("--rebuild-check", action="store_true")
    parser.add_argument("--source-dir", help="directory holding the pinned source files")
    args = parser.parse_args()
    if args.check:
        return check()
    if args.verify:
        return verify(args.source_dir)
    if args.rebuild_check:
        return rebuild_check(args.source_dir)
    return build(args.source_dir)


if __name__ == "__main__":
    sys.exit(main())
