"""Create or update the Axismundi Pilot core block specimen wall page.

The fixture is committed in the Pilot theme and imported through WP-CLI so the
audit surface is both reproducible and rendered by WordPress.
"""

from __future__ import annotations

import os
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
FIXTURE = ROOT / "products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html"
WP_FIXTURE = "/var/www/html/wp-content/themes/axismundi-pilot/fixtures/core-block-specimen-wall.html"
SLUG = "axismundi-core-block-specimen-wall"
TITLE = "Axismundi Core Block Specimen Wall"
URL = f"http://localhost:8888/?pagename={SLUG}"


def npx_command() -> str:
    return "npx.cmd" if os.name == "nt" else "npx"


def wp(args: list[str]) -> str:
    command = [npx_command(), "wp-env", "run", "cli", "wp", *args]
    result = subprocess.run(
        command,
        cwd=ROOT,
        check=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )
    return result.stdout


def leading_id(output: str) -> str | None:
    line_match = re.search(r"(?m)^\s*(\d+)\s*$", output)
    if line_match:
        return line_match.group(1)
    match = re.match(r"\s*(\d+)", output)
    return match.group(1) if match else None


def find_existing_page_id() -> str | None:
    output = wp(
        [
            "post",
            "list",
            "--post_type=page",
            "--fields=ID,post_name",
            "--format=csv",
        ]
    )
    for line in output.splitlines():
        parts = [part.strip() for part in line.split(",", 1)]
        if len(parts) == 2 and parts[1] == SLUG and parts[0].isdigit():
            return parts[0]
    return None


def main() -> None:
    if not FIXTURE.exists():
        raise SystemExit(f"Missing fixture: {FIXTURE}")

    existing_id = find_existing_page_id()
    if existing_id:
        page_id = existing_id
        wp(
            [
                "post",
                "update",
                page_id,
                WP_FIXTURE,
                f"--post_title={TITLE}",
                f"--post_name={SLUG}",
                "--post_status=publish",
            ]
        )
        print(f"Updated specimen wall page {page_id}: {URL}")
        return

    created_output = wp(
        [
            "post",
            "create",
            WP_FIXTURE,
            "--post_type=page",
            f"--post_title={TITLE}",
            f"--post_name={SLUG}",
            "--post_status=publish",
            "--porcelain",
        ]
    )
    page_id = leading_id(created_output)
    if not page_id:
        raise SystemExit(f"Could not parse created post ID from wp-env output:\n{created_output}")
    print(f"Created specimen wall page {page_id}: {URL}")


if __name__ == "__main__":
    main()
