#!/usr/bin/env python3
"""Fail when a tracked text file is not LF in the working tree.

The repository already declares one policy: `.gitattributes` sets `* text=auto eol=lf`, and every
tracked file is LF in the index. What that setting does is normalize on the way *in* — it says
nothing about the file sitting on disk, so an editor that appends a CRLF block to an LF file, or
writes an LF block into a CRLF one, leaves a file that commits cleanly and reads inconsistently.

That is not a cosmetic problem. A mixed file breaks exact-match editing: an anchor copied from one
part of the file fails to match another part for no visible reason, and the failure looks like a
missing string rather than a line-ending difference. It cost real time here before it was named.

So the policy gets a check. `git ls-files --eol` reports what is in the index (`i/`) and what is on
disk (`w/`); this fails on `w/mixed` and on `w/crlf`.

`w/crlf` used to be tolerated here, on the reasoning that a wholly-CRLF file still normalizes to LF
on commit and is a legitimate Windows checkout. That is true of the commit and false of the work:
these files are read and edited on disk, not in the index, and a CRLF file is where a mixed file
comes from — append one LF block to it and the state this check exists to catch has been created.
Tolerating the whole-file case meant the check passed while twelve tracked files sat CRLF on disk,
which is not the guarantee its name implies.

No tracked text file is exempt: the repository policy is LF everywhere.

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
    """Return (working-tree eol, path) for every tracked file."""
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


def offenders() -> list[str]:
    """Tracked paths whose working-tree endings disagree with the repository's policy."""
    found: list[str] = []
    for working, path in tracked_eol():
        if working in ("mixed", "crlf"):
            found.append(path)
    return found


def fix(path: str) -> None:
    """Rewrite one file to LF, leaving its bytes otherwise untouched."""
    data = Path(path).read_bytes()
    Path(path).write_bytes(data.replace(b"\r\n", b"\n"))


def main() -> int:
    should_fix = "--fix" in sys.argv[1:]
    found = offenders()

    if not found:
        print("line endings: every tracked file is LF in the working tree")
        return 0

    if should_fix:
        for path in found:
            fix(path)
            print(f"normalized {path}")
        remaining = offenders()
        if remaining:
            print("still not LF after fixing:", ", ".join(remaining), file=sys.stderr)
            return 1
        print(f"line endings: normalized {len(found)} file(s) to LF")
        return 0

    print(
        f"line endings: {len(found)} tracked file(s) are CRLF or mixed in the working tree.",
        file=sys.stderr,
    )
    for path in found:
        print(f"  {path}", file=sys.stderr)
    print(
        "\nThese commit cleanly — the index is already LF — but the working tree is what gets read\n"
        "and edited, and a CRLF file breaks exact-match editing the moment anything appends LF to it.\n"
        "Repair with: python tools/validators/validate_line_endings.py --fix",
        file=sys.stderr,
    )
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
