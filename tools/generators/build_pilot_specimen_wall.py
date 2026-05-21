"""Create or update the Axismundi Pilot core block specimen pages.

The fixtures are committed in the Pilot theme and imported through WP-CLI so
the audit surfaces are reproducible and rendered by WordPress.
"""

from __future__ import annotations

import os
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
FIXTURE_ROOT = ROOT / "products/reference-implementations/axismundi-pilot/fixtures"
WP_FIXTURE_ROOT = "/var/www/html/wp-content/themes/axismundi-pilot/fixtures"

FIXTURES = [
    {
        "file": "core-block-specimen-wall.html",
        "slug": "axismundi-core-block-specimen-wall",
        "title": "Axismundi Core Block Specimen Wall",
    },
    {
        "file": "core-block-editor-smoke.html",
        "slug": "axismundi-core-block-editor-smoke",
        "title": "Axismundi Core Block Editor Smoke",
    },
]


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


def find_existing_page_id(slug: str) -> str | None:
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
        if len(parts) == 2 and parts[1] == slug and parts[0].isdigit():
            return parts[0]
    return None


def import_fixture(fixture: dict[str, str]) -> None:
    file_name = fixture["file"]
    slug = fixture["slug"]
    title = fixture["title"]
    fixture_path = FIXTURE_ROOT / file_name
    wp_fixture = f"{WP_FIXTURE_ROOT}/{file_name}"
    url = f"http://localhost:8888/?pagename={slug}"

    if not fixture_path.exists():
        raise SystemExit(f"Missing fixture: {fixture_path}")

    existing_id = find_existing_page_id(slug)
    if existing_id:
        page_id = existing_id
        wp(
            [
                "post",
                "update",
                page_id,
                wp_fixture,
                f"--post_title={title}",
                f"--post_name={slug}",
                "--post_status=publish",
            ]
        )
        print(f"Updated {title} page {page_id}: {url}")
        return

    created_output = wp(
        [
            "post",
            "create",
            wp_fixture,
            "--post_type=page",
            f"--post_title={title}",
            f"--post_name={slug}",
            "--post_status=publish",
            "--porcelain",
        ]
    )
    page_id = leading_id(created_output)
    if not page_id:
        raise SystemExit(f"Could not parse created post ID from wp-env output:\n{created_output}")
    print(f"Created {title} page {page_id}: {url}")


def main() -> None:
    for fixture in FIXTURES:
        import_fixture(fixture)


if __name__ == "__main__":
    main()
