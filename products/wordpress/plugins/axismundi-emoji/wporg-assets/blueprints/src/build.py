"""Build the Playground blueprints from src/.

    python build.py          write ../blueprint.json and ../release.json
    python build.py --check  exit 1 if either file is not what this writes

blueprint.json is the WordPress.org Live Preview: it installs the plugin from the
directory, so it works only once the plugin is approved. release.json installs the
GitHub release ZIP instead, for showing the plugin before that. Only blueprint.json
belongs in the directory's assets/blueprints/.

A runPHP step takes inline code only, so the page markup (demo-page.html) and the
setup code (setup.php) are kept as files here and embedded at build time.
"""

import argparse
import json
import sys
from pathlib import Path

SRC = Path(__file__).resolve().parent
OUT = SRC.parent
MARKER = "/* demo-page.html */"
PLUGIN = "axismundi-emoji"
RELEASE_TAG = "emoji-v0.3.0"
RELEASE_ZIP = f"https://github.com/Jiwoon-Kim/axismundi/releases/download/{RELEASE_TAG}/{PLUGIN}.zip"


def setup_code() -> str:
    page = (SRC / "demo-page.html").read_text(encoding="utf-8").rstrip("\n")
    # The page is embedded in a nowdoc that ends at a line starting with HTML.
    if any(line.lstrip().startswith("HTML") for line in page.splitlines()):
        sys.exit("demo-page.html has a line starting with HTML, which would end the nowdoc early")
    php = (SRC / "setup.php").read_text(encoding="utf-8")
    if php.count(MARKER) != 1:
        sys.exit(f"setup.php must contain {MARKER!r} exactly once")
    return php.replace(MARKER, page)


def blueprint(plugin_data: dict) -> dict:
    return {
        "$schema": "https://playground.wordpress.net/blueprint-schema.json",
        "landingPage": "/emoji-demo/",
        "preferredVersions": {"php": "8.3", "wp": "latest"},
        "steps": [
            {"step": "login", "username": "admin", "password": "password"},
            {"step": "installPlugin", "pluginData": plugin_data, "options": {"activate": True}},
            {"step": "runPHP", "code": setup_code()},
        ],
    }


def outputs() -> dict:
    return {
        OUT / "blueprint.json": blueprint({"resource": "wordpress.org/plugins", "slug": PLUGIN}),
        OUT / "release.json": blueprint({"resource": "url", "url": RELEASE_ZIP}),
    }


def render(data: dict) -> str:
    return json.dumps(data, indent=4, ensure_ascii=False) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()

    stale = []
    for path, data in outputs().items():
        expected = render(data)
        if args.check:
            actual = path.read_text(encoding="utf-8") if path.exists() else ""
            if actual != expected:
                stale.append(path.name)
            continue
        path.write_text(expected, encoding="utf-8", newline="\n")
        print(f"wrote {path} ({len(expected.encode())} bytes)")

    if args.check:
        if stale:
            print(f"out of date: {', '.join(stale)}; run build.py", file=sys.stderr)
            return 1
        print("blueprints are up to date")
    return 0


if __name__ == "__main__":
    sys.exit(main())
