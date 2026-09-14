"""Build the Live Preview blueprint from src/.

    python build.py                 write ../blueprint.json
    python build.py --check         exit 1 if ../blueprint.json is not what this writes
    python build.py --dev OUT.json  write a Playground CLI variant: the plugin comes
                                    from a mounted checkout instead of WordPress.org,
                                    and a last step writes a report to /vqa-report

A WordPress.org Live Preview reads one assets/blueprints/blueprint.json and
installs from wordpress.org resources, and a runPHP step takes inline code only.
So the page markup (vqa-page.html) and the setup code (setup.php) are kept as
files here and embedded at build time.
"""

import argparse
import json
import sys
from pathlib import Path

SRC = Path(__file__).resolve().parent
OUT = SRC.parent / "blueprint.json"
MARKER = "/* vqa-page.html */"
PLUGIN = "axismundi-dialogs"


def setup_code() -> str:
    page = (SRC / "vqa-page.html").read_text(encoding="utf-8").rstrip("\n")
    # The page is embedded in a nowdoc that ends at a line starting with HTML.
    if any(line.lstrip().startswith("HTML") for line in page.splitlines()):
        sys.exit("vqa-page.html has a line starting with HTML, which would end the nowdoc early")
    php = (SRC / "setup.php").read_text(encoding="utf-8")
    if php.count(MARKER) != 1:
        sys.exit(f"setup.php must contain {MARKER!r} exactly once")
    return php.replace(MARKER, page)


def install_plugin(slug: str, optional: bool = False) -> dict:
    options = {"activate": True}
    if optional:
        options["onError"] = "skip-plugin"
    return {
        "step": "installPlugin",
        "pluginData": {"resource": "wordpress.org/plugins", "slug": slug},
        "options": options,
    }


def blueprint(dev: bool = False) -> dict:
    steps = [
        {"step": "login", "username": "admin", "password": "password"},
        {
            "step": "installTheme",
            "themeData": {"resource": "wordpress.org/themes", "slug": "axismundi"},
            "options": {"activate": True},
        },
    ]
    if dev:
        steps.append({"step": "activatePlugin", "pluginPath": f"{PLUGIN}/{PLUGIN}.php"})
    else:
        steps.append(install_plugin(PLUGIN))
    # Only the Colour scheme section needs it; the rest of the page stands without it.
    steps.append(install_plugin("axismundi-theme-switcher", optional=True))
    steps.append({"step": "runPHP", "code": setup_code()})
    if dev:
        steps.append({"step": "runPHP", "code": (SRC / "report.php").read_text(encoding="utf-8")})
    return {
        "$schema": "https://playground.wordpress.net/blueprint-schema.json",
        "landingPage": "/dialogs-vqa/",
        "preferredVersions": {"php": "8.3", "wp": "latest"},
        "steps": steps,
    }


def render(data: dict) -> str:
    return json.dumps(data, indent=4, ensure_ascii=False) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--dev", metavar="OUT")
    args = parser.parse_args()

    if args.dev:
        path = Path(args.dev)
        path.write_text(render(blueprint(dev=True)), encoding="utf-8", newline="\n")
        print(f"wrote {path}")
        return 0

    expected = render(blueprint())
    if args.check:
        actual = OUT.read_text(encoding="utf-8") if OUT.exists() else ""
        if actual != expected:
            print(f"{OUT} is out of date; run build.py", file=sys.stderr)
            return 1
        print(f"{OUT} is up to date")
        return 0

    OUT.write_text(expected, encoding="utf-8", newline="\n")
    print(f"wrote {OUT} ({len(expected.encode())} bytes)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
