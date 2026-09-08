#!/usr/bin/env python3
"""
build.py — everything that has to happen before Jekyll runs.

    python bin/build.py            prepare, then build the site
    python bin/build.py --serve    prepare, then serve it
    python bin/build.py --prepare  prepare only, no Jekyll
    python bin/build.py --verify   prepare and check, but write nothing new

The order matters and is the same locally and in CI:

    1. sync assets      copy the products' fonts and colour tokens in
    2. generate         build the typography and layout CSS from _data/
    3. check            the CSS equals what the generator produces
    4. validate         its values equal the published spec, and where a value
                        is also the theme's, that the two still agree
    5. jekyll           build or serve

Step 1 exists because several of this site's inputs are deliberately not
committed: the font files, fonts.css, and the theme's colour token CSS are
copies of what the products ship, and would go stale as a second copy in git. A
clean checkout therefore needs it before Jekyll, or the page renders in a
system font with no palette at all. That is the whole reason this script exists
rather than a line in the README that someone forgets.

Steps 3 and 4 answer different questions. The first asks whether the committed
CSS is what the generator produces, catching a hand-edit or a data change that
was never rebuilt. The second asks whether its values match the spec, catching
a bug in the generator itself, which the first would happily approve.
"""

from __future__ import annotations

import argparse
import shutil
import subprocess
import sys
from pathlib import Path

UTF8 = "utf-8"
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding=UTF8)
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding=UTF8)

STYLEGUIDE = Path(__file__).resolve().parent.parent
ROOT = STYLEGUIDE.parent.parent


def run(label: str, argv: list[str], cwd: Path) -> bool:
    print(f"[{label}]", flush=True)
    # On Windows `bundle` is a .cmd shim, which subprocess will not find from a
    # bare name. Resolve it the way the shell would.
    resolved = shutil.which(argv[0])
    if resolved is None:
        print(f"\n{label} failed: {argv[0]} is not on PATH", flush=True)
        if argv[0] == "bundle":
            print("  Install Ruby with DevKit, then `gem install bundler`.", flush=True)
        return False
    result = subprocess.run([resolved, *argv[1:]], cwd=cwd)
    if result.returncode != 0:
        print(f"\n{label} failed (exit {result.returncode})")
        return False
    return True


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument("--serve", action="store_true", help="jekyll serve instead of build")
    group.add_argument("--prepare", action="store_true", help="stop before Jekyll")
    group.add_argument(
        "--verify",
        action="store_true",
        help="prepare and check without regenerating the committed CSS",
    )
    args = parser.parse_args()

    py = sys.executable
    steps: list[tuple[str, list[str], Path]] = [
        ("sync assets", [py, "tools/generators/sync_styleguide_assets.py"], ROOT),
    ]

    generators = [
        ("typography", "generate_styleguide_typography.py"),
        ("layout", "generate_styleguide_layout.py"),
    ]
    for label, script in generators:
        if args.verify:
            steps.append((f"check generated {label} CSS",
                          [py, f"tools/generators/{script}", "--check"], ROOT))
        else:
            steps.append((f"generate {label}", [py, f"tools/generators/{script}"], ROOT))

    for label, script in (("typography", "validate_styleguide_typography.py"),
                          ("layout", "validate_styleguide_layout.py"),
                          ("button", "validate_styleguide_button.py")):
        steps.append((f"validate {label}", [py, f"tools/validators/{script}"], ROOT))

    for label, argv, cwd in steps:
        if not run(label, argv, cwd):
            return 1

    if args.prepare or args.verify:
        print("\nprepared" + (" and verified" if args.verify else ""))
        return 0

    jekyll = ["bundle", "exec", "jekyll", "serve" if args.serve else "build"]
    if not run("jekyll " + jekyll[-1], jekyll, STYLEGUIDE):
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
