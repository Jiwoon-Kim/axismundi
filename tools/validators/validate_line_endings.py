#!/usr/bin/env python3
"""Fail when a tracked text file has mixed line endings in the working tree.

The repository already declares one policy: `.gitattributes` sets `* text=auto eol=lf`, and every
tracked file is LF in the index. What that setting does is normalize on the way *in* — it says
nothing about the file sitting on disk, so an editor that appends a CRLF block to an LF file, or
writes an LF block into a CRLF one, leaves a file that commits cleanly and reads inconsistently.

That is not a cosmetic problem. A mixed file breaks exact-match editing: an anchor copied from one
part of the file fails to match another part for no visible reason, and the failure looks like a
missing string rather than a line-ending difference. It cost real time here before it was named.

So the policy gets a check. `git ls-files --eol` reports what is in the index (`i/`) and what is on
disk (`w/`); this fails on `w/mixed`, which is the state no file should ever be in.

`w/crlf` is deliberately not failed. A tracked file that is wholly CRLF on disk still normalizes to
LF on commit, and on Windows that is a legitimate checkout. It is the mixture that has no honest
reading.

Usage:
    python tools/validators/validate_line_endings.py [--fix]

`--fix` rewrites the offending files to LF. Because the index is already LF, that rewrite normally
produces no diff at all — it is repairing the working tree, not changing the repository.
"""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path


def tracked_eol() -> list[tuple[str, str]]:
    """Return (working-tree eol, path) for every tracked file git reports on."""
    out = subprocess.run(
        ["git", "ls-files", "--eol"],
        capture_output=True,
        text=True,
        check=True,
    ).stdout
    rows: list[tuple[str, str]] = []
    for line in out.splitlines():
        if not line.strip():
            continue
        # Format: "i/lf    w/mixed attr/text=auto eol=lf \tpath"
        fields, _, path = line.partition("\t")
        parts = fields.split()
        working = next((p[2:] for p in parts if p.startswith("w/")), "")
        rows.append((working, path.strip()))
    return rows


def fix(path: str) -> None:
    """Rewrite one file to LF, leaving its bytes otherwise untouched."""
    data = Path(path).read_bytes()
    Path(path).write_bytes(data.replace(b"\r\n", b"\n"))


def main() -> int:
    should_fix = "--fix" in sys.argv[1:]
    mixed = [path for working, path in tracked_eol() if working == "mixed"]

    if not mixed:
        print("line endings: no tracked file is mixed")
        return 0

    if should_fix:
        for path in mixed:
            fix(path)
            print(f"normalized {path}")
        remaining = [path for working, path in tracked_eol() if working == "mixed"]
        if remaining:
            print("still mixed after fixing:", ", ".join(remaining), file=sys.stderr)
            return 1
        print(f"line endings: normalized {len(mixed)} file(s) to LF")
        return 0

    print(
        f"line endings: {len(mixed)} tracked file(s) mix CRLF and LF in the working tree.",
        file=sys.stderr,
    )
    for path in mixed:
        print(f"  {path}", file=sys.stderr)
    print(
        "\nThese commit cleanly — the index is already LF — but they break exact-match editing,\n"
        "where an anchor matches in one part of the file and not another.\n"
        "Repair with: python tools/validators/validate_line_endings.py --fix",
        file=sys.stderr,
    )
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
