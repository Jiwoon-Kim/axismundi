#!/usr/bin/env python3
"""Build the Noto Color Emoji flags-only COLRv1 WOFF2 asset.

The input is the checked-in full font. The Unicode Emoji test data chooses the
RGI country and subdivision sequences; FontTools then retains the OpenType
layout and COLRv1 closure needed to render those sequences as one glyph.

Example:
  PYTHONPATH=D:/axismundi-assets/noto-emoji/tools/font-inspect \\
    python scripts/build-noto-color-emoji-flags.py \\
      D:/axismundi-assets/noto-emoji/sources/emoji-test-17.0.txt
"""

from __future__ import annotations

import argparse
import io
import re
import sys
import tempfile
from pathlib import Path

from fontTools.subset import main as subset_main
from fontTools.ttLib import TTFont
import uharfbuzz as hb


PLUGIN = Path(__file__).resolve().parent.parent
INPUT = PLUGIN / "assets/fonts/noto-color-emoji/axismundi-noto-colrv1.woff2"
OUTPUT = PLUGIN / "assets/fonts/noto-color-emoji/axismundi-noto-colrv1-flags.woff2"
REGIONAL_INDICATOR_START = 0x1F1E6
REGIONAL_INDICATOR_END = 0x1F1FF
BLACK_FLAG = 0x1F3F4
TAG_CANCEL = 0xE007F


def rgi_flag_sequences(emoji_test: Path) -> list[str]:
	"""Return only fully-qualified country and subdivision flag sequences."""
	sequences = []
	for line in emoji_test.read_text(encoding="utf-8").splitlines():
		match = re.match(r"^([0-9A-F ]+)\s*;\s*fully-qualified\s+#", line)
		if not match:
			continue
		codepoints = [int(value, 16) for value in match.group(1).split()]
		country = len(codepoints) == 2 and all(
			REGIONAL_INDICATOR_START <= value <= REGIONAL_INDICATOR_END
			for value in codepoints
		)
		subdivision = (
			len(codepoints) > 2
			and codepoints[0] == BLACK_FLAG
			and codepoints[-1] == TAG_CANCEL
		)
		if country or subdivision:
			sequences.append("".join(chr(value) for value in codepoints))
	return sequences


def verify(output: Path, sequences: list[str]) -> None:
	"""Require every promised RGI flag to shape to one non-.notdef glyph."""
	font = TTFont(output)
	missing = [table for table in ("COLR", "CPAL", "GSUB", "cmap") if table not in font]
	if missing:
		raise RuntimeError(f"flags font is missing required tables: {', '.join(missing)}")

	font.flavor = None  # HarfBuzz reads the decompressed sfnt, not WOFF2.
	data = io.BytesIO()
	font.save(data)
	hb_font = hb.Font(hb.Face(hb.Blob(data.getvalue())))
	failures = []
	for sequence in sequences:
		buffer = hb.Buffer()
		buffer.add_codepoints([ord(character) for character in sequence])
		buffer.guess_segment_properties()
		hb.shape(hb_font, buffer)
		glyphs = [info.codepoint for info in buffer.glyph_infos]
		if len(glyphs) != 1 or glyphs[0] == 0:
			failures.append((sequence, glyphs))
	if failures:
		preview = ", ".join(
			f"{'-'.join(f'{ord(character):X}' for character in sequence)}={glyphs}"
			for sequence, glyphs in failures[:5]
		)
		raise RuntimeError(f"{len(failures)} RGI flags do not shape as one glyph: {preview}")


def main() -> int:
	parser = argparse.ArgumentParser(description=__doc__)
	parser.add_argument("emoji_test", type=Path, help="Unicode emoji-test.txt for the target Emoji version")
	args = parser.parse_args()

	if not INPUT.is_file():
		parser.error(f"missing full font input: {INPUT}")
	if not args.emoji_test.is_file():
		parser.error(f"missing emoji test data: {args.emoji_test}")

	sequences = rgi_flag_sequences(args.emoji_test)
	if len(sequences) < 250:
		parser.error(f"expected RGI flag data, found only {len(sequences)} sequences")

	with tempfile.NamedTemporaryFile("w", encoding="utf-8", suffix=".txt", delete=False) as source:
		source.write("\n".join(sequences))
		text_path = Path(source.name)

	try:
		subset_main(
			[
				str(INPUT),
				f"--text-file={text_path}",
				"--layout-features=*",
				"--name-IDs=*",
				"--name-legacy",
				"--notdef-glyph",
				"--notdef-outline",
				"--recommended-glyphs",
				"--flavor=woff2",
				f"--output-file={OUTPUT}",
			]
		)
	finally:
		text_path.unlink(missing_ok=True)

	verify(OUTPUT, sequences)
	print(f"Built and verified {OUTPUT} from {len(sequences)} RGI flag sequences.")
	return 0


if __name__ == "__main__":
	sys.exit(main())
