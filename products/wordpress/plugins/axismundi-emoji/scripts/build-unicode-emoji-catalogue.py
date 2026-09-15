#!/usr/bin/env python3
"""Write the Unicode RGI emoji catalogue from Unicode's emoji-test.txt.

Outputs, under assets/unicode/catalogue/:

  rgi-17.0.json           every fully-qualified RGI sequence: the search index
  rgi-17.0/<group>.json   the same entries split by Unicode group: the browse path,
                          so opening one category never downloads the other eight

Generated, never hand-edited; takes --check (AGENTS.md "Generated files").

The input is the same emoji-test.txt the flags font subset is built from
(scripts/build-noto-color-emoji-flags.py), so the catalogue and the font agree on what
Emoji 17.0 is. Its SHA-256 is pinned below: a different file is refused rather than
silently producing a different catalogue.

  python scripts/build-unicode-emoji-catalogue.py D:/axismundi-assets/noto-emoji/sources/emoji-test-17.0.txt
  python scripts/build-unicode-emoji-catalogue.py D:/.../emoji-test-17.0.txt --check

Moved from axismundi-activities (build-unicode-emoji-catalogue.ps1) when Emoji became the
owner of Unicode emoji data. Entry shape is unchanged.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from pathlib import Path

PLUGIN = Path(__file__).resolve().parent.parent
OUT_DIR = PLUGIN / "assets" / "unicode" / "catalogue"
VERSION = "17.0"
SOURCE_URL = "https://www.unicode.org/Public/17.0.0/emoji/emoji-test.txt"
SOURCE_SHA256 = "1d8a944f88d7952f7ef7c5167fef3c67995bcae24543949710231b03a201acda"

LINE = re.compile(r"^([0-9A-F ]+)\s*;\s*fully-qualified\s*#\s*(\S+)\s+E([0-9.]+)\s+(.+)$")


def slug(group: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", group.lower().replace("&", "and")).strip("-")


def dump(value) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"))


def build(emoji_test: Path) -> dict[Path, str]:
    raw = emoji_test.read_bytes()
    digest = hashlib.sha256(raw).hexdigest()
    if digest != SOURCE_SHA256:
        raise SystemExit(f"{emoji_test} is not the pinned emoji-test.txt {VERSION} (sha256 {digest})")

    group = subgroup = ""
    items = []
    for line in raw.decode("utf-8").splitlines():
        if line.startswith("# group: "):
            group = line[len("# group: "):]
            continue
        if line.startswith("# subgroup: "):
            subgroup = line[len("# subgroup: "):]
            continue
        match = LINE.match(line)
        if not match:
            continue
        points = match.group(1).split()
        # The reaction key drops presentation selectors so a heart with and without U+FE0F is one chip.
        keypoints = [p for p in points if p not in ("FE0E", "FE0F")]
        name = match.group(4)
        items.append({
            "emoji": match.group(2),
            "key": "unicode:U+" + "-U+".join(keypoints),
            "group": group,
            "subgroup": subgroup,
            "name": name,
            "keywords": name.lower().split(),
            "emojiVersion": match.group(3),
        })

    outputs = {
        OUT_DIR / f"rgi-{VERSION}.json": dump({
            "schema": 1,
            "unicodeVersion": VERSION,
            "source": SOURCE_URL,
            "sourceSha256": digest,
            "items": items,
        }),
    }
    groups: dict[str, list] = {}
    for item in items:
        groups.setdefault(item["group"], []).append(item)
    for name, entries in groups.items():
        outputs[OUT_DIR / f"rgi-{VERSION}" / f"{slug(name)}.json"] = dump({
            "schema": 1,
            "unicodeVersion": VERSION,
            "group": name,
            "items": entries,
        })
    return outputs


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("emoji_test", type=Path, help=f"Unicode emoji-test.txt {VERSION}")
    parser.add_argument("--check", action="store_true", help="fail if the committed catalogue differs from what this script writes")
    args = parser.parse_args()

    outputs = build(args.emoji_test)
    existing = set((OUT_DIR / f"rgi-{VERSION}").glob("*.json")) | ({OUT_DIR / f"rgi-{VERSION}.json"} if (OUT_DIR / f"rgi-{VERSION}.json").exists() else set())

    if args.check:
        stale = [p for p, text in outputs.items() if not p.exists() or p.read_text(encoding="utf-8") != text]
        extra = sorted(existing - set(outputs))
        for path in stale:
            print(f"stale: {path.relative_to(PLUGIN)}")
        for path in extra:
            print(f"not generated: {path.relative_to(PLUGIN)}")
        if stale or extra:
            return 1
        print(f"catalogue is current ({len(outputs) - 1} groups)")
        return 0

    for path in existing - set(outputs):
        path.unlink()
    for path, text in outputs.items():
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text, encoding="utf-8", newline="\n")
    count = len(json.loads(outputs[OUT_DIR / f"rgi-{VERSION}.json"])["items"])
    print(f"Wrote {count} RGI entries and {len(outputs) - 1} group files to {OUT_DIR.relative_to(PLUGIN)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
