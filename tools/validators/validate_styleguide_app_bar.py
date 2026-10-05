#!/usr/bin/env python3
"""Check the App Bar's Expressive contract against its committed M3 table."""

from __future__ import annotations

import json
import re
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent.parent.parent
DATA = ROOT / "products/styleguide/_data/app_bar.yml"
TABLE = ROOT / "products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-APP-BARS-RAW.md"
CSS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/app-bar.css"
JS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/components/app-bars/app-bar.js"

ROW = re.compile(r"^  (md\.[a-z0-9.\-]+)\s+[A-Z_]+\s+(.+?)\s*$")
COMMENT = re.compile(r"/\*.*?\*/|//[^\n]*", re.S)


def rows(text: str) -> dict[str, str]:
    return {
        match.group(1): match.group(2)
        for line in text.splitlines()
        if (match := ROW.match(line))
    }


def dips(value: str) -> int | None:
    if value.startswith("-> "):
        return None
    try:
        payload = json.loads(value)
    except json.JSONDecodeError:
        return None
    return int(payload["value"]) if payload.get("unit") == "DIPS" else None


def declarations(text: str) -> str:
    """Exclude commentary so a rationale cannot satisfy a CSS or JS contract."""
    return COMMENT.sub("", text)


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    source = TABLE.read_text(encoding="utf-8")
    token_rows = rows(source)
    css = declarations(CSS.read_text(encoding="utf-8"))
    js = declarations(JS.read_text(encoding="utf-8"))
    failures: list[str] = []
    checks = 0

    def check(condition: bool, failure: str) -> None:
        nonlocal checks
        checks += 1
        if not condition:
            failures.append(failure)

    def row_is(token: str, value: str) -> None:
        check(
            token_rows.get(token) == "-> " + value,
            f"{token} no longer resolves to {value}",
        )

    def css_has(value: str, failure: str) -> None:
        check(value in css, failure)

    check(
        data["meta"]["table_revision"] in source,
        "recorded table revision is absent from the source table",
    )

    container = data["container"]
    row_is(container["color_token"], "md.sys.color." + container["color_role"])
    row_is(
        container["on_scroll_color_token"],
        "md.sys.color." + container["on_scroll_color_role"],
    )
    row_is(
        container["on_scroll_elevation_token"],
        "md.sys.elevation." + container["on_scroll_elevation_role"].split(".")[-1],
    )
    row_is(container["shape_token"], "md.sys.shape." + container["shape_role"])
    css_has(
        "--md-comp-app-bar-container-color: var( --md-sys-color-surface )",
        "flat container does not consume surface",
    )
    css_has(
        ".ax-app-bar[data-scrolled]",
        "stylesheet no longer scopes the on-scroll surface to scrolled state",
    )
    css_has(
        "--md-comp-app-bar-container-color: var( --md-sys-color-surface-container )",
        "scrolled container does not consume surface-container",
    )
    check(
        "<Elevation level={ scrolled ? 2 : 0 } />" in js,
        "component no longer maps the published on-scroll elevation to level 2",
    )

    row_is("md.comp.app-bar.title.color", "md.sys.color.on-surface")
    row_is("md.comp.app-bar.subtitle.color", "md.sys.color.on-surface-variant")
    row_is("md.comp.app-bar.leading-icon.color", "md.sys.color.on-surface")
    row_is("md.comp.app-bar.trailing-icon.color", "md.sys.color.on-surface-variant")
    row_is("md.comp.app-bar.leading-space", "md.sys.measurement.space50")
    row_is("md.comp.app-bar.trailing-space", "md.sys.measurement.space50")
    check(
        dips(token_rows.get("md.comp.app-bar.icon.size", "")) == 24,
        "common icon size is no longer the expected 24dp",
    )
    css_has(
        "--md-comp-app-bar-leading-space: var( --md-sys-measurement-space50 )",
        "stylesheet does not consume leading space50",
    )
    css_has(
        "--md-comp-app-bar-trailing-space: var( --md-sys-measurement-space50 )",
        "stylesheet does not consume trailing space50",
    )

    for name, variant in data["variants"].items():
        prefix = "md.comp.app-bar." + ("small" if name == "small" else name + "-flexible")
        selector = '.ax-app-bar[data-variant="' + name + '"]'

        check(
            dips(token_rows.get(variant["height_token"], "")) == variant["height"],
            f"{name} height differs from its token row",
        )
        css_has(
            selector + " {\n		--md-comp-app-bar-container-height: " + str(variant["height"]) + "px",
            f"{name} stylesheet height does not match its token row",
        )
        row_is(prefix + ".title.font", "md.sys.typescale." + variant["title_typescale"])
        row_is(prefix + ".subtitle.font", "md.sys.typescale." + variant["subtitle_typescale"])
        css_has(
            "--md-comp-app-bar-title-font: var( --md-sys-typescale-"
            + variant["title_typescale"]
            + "-font )",
            f"{name} title does not consume {variant['title_typescale']}",
        )
        css_has(
            "--md-comp-app-bar-subtitle-font: var( --md-sys-typescale-"
            + variant["subtitle_typescale"]
            + "-font )",
            f"{name} subtitle does not consume {variant['subtitle_typescale']}",
        )
        css_has(
            "--md-comp-app-bar-title-size: var( --md-sys-typescale-"
            + variant["title_typescale"]
            + "-size )",
            f"{name} title size is detached from {variant['title_typescale']}",
        )
        css_has(
            "--md-comp-app-bar-subtitle-size: var( --md-sys-typescale-"
            + variant["subtitle_typescale"]
            + "-size )",
            f"{name} subtitle size is detached from {variant['subtitle_typescale']}",
        )

        if "subtitle_height" in variant:
            check(
                dips(token_rows.get(variant["subtitle_height_token"], ""))
                == variant["subtitle_height"],
                f"{name} subtitle height differs from its token row",
            )
            css_has(
                '.ax-app-bar[data-variant="' + name + '"][data-has-subtitle] {\n'
                + "		--md-comp-app-bar-container-height: "
                + str(variant["subtitle_height"])
                + "px",
                f"{name} subtitle height is detached from its token row",
            )

        nowrap = selector + " .ax-app-bar__title {\n		white-space: nowrap"
        check(
            (nowrap in css) is (not variant["title_wraps"]),
            f"{name} title wrapping no longer matches app_bar.yml",
        )

    # THE HEADLINE GROWS, IT DOES NOT CLAMP. "Wrap the headline to two lines
    # maximum" and "Don't truncate the headline text" cannot both be honoured by
    # the component -- clamping to two lines hides the rest, which is the
    # truncation the same page forbids -- so the container is a minimum and the
    # caller keeps the headline brief. Pinned because a reader of the first bound
    # alone would reach for a clamp.
    check(
        "line-clamp" not in css and "max-lines" not in css,
        "the headline is clamped; hiding what overflows is the truncation M3 forbids",
    )
    check(
        any("line count" in str(entry.get("token", "")) for entry in data["discrepancies"]),
        "the headline line-count divergence is no longer recorded as a discrepancy",
    )

    check(
        dips(token_rows.get("md.comp.app-bar.large.container.height", "")) == 152,
        "baseline large row is no longer the expected 152dp trap",
    )
    check(
        "md.comp.app-bar.large.container.height" not in css,
        "stylesheet references the live-but-baseline large row",
    )
    check(
        "--md-comp-app-bar-container-height: 120px" in css,
        "stylesheet does not declare the 120px flexible-large minimum",
    )
    check(
        "min-block-size: var( --md-comp-app-bar-container-height )" in css,
        "container height is no longer implemented as a minimum",
    )

    deprecated = source.partition("skipped (deprecated):")[2]
    top_app_bar_tokens = re.findall(r"md\.comp\.top-app-bar\.[\w.-]+", deprecated)
    check(
        len(top_app_bar_tokens) == 72,
        "the source no longer records all 72 top-app-bar tokens as deprecated",
    )
    check(
        "md.comp.top-app-bar." not in css and "md.comp.top-app-bar." not in js,
        "implementation references the deprecated top-app-bar namespace",
    )

    check(
        "const actionCount = Children.count( actionNodes )" in js,
        "component no longer counts direct trailing action nodes",
    )
    check(
        "if ( 2 < actionCount )" in js,
        "component no longer warns when direct actions exceed the advised maximum",
    )
    check(
        "return (\n		<header" in js,
        "component no longer uses the published header landmark",
    )

    if failures:
        print(f"app bar: {checks} checks, {len(failures)} failed")
        for failure in failures:
            print(" - " + failure)
        return 1
    print(f"app bar: {checks} checks, 0 failed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
