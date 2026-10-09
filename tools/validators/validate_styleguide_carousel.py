#!/usr/bin/env python3
"""Check the Carousel source contract against its implementation."""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any

import yaml

ROOT = Path(__file__).resolve().parent.parent.parent
DATA = ROOT / "products/styleguide/_data/carousel.yml"
TABLE = ROOT / (
    "products/wordpress/plugins/axismundi/docs/m3-token-tables/"
    "SOURCE-M3-CAROUSEL-RAW.md"
)
HOME_PLAN = ROOT / "products/wordpress/plugins/axismundi/docs/PLAN-FRONTEND-HOME.md"
SCROLL_DECISION = ROOT / (
    "products/wordpress/plugins/axismundi/docs/"
    "DECISION-FRONTEND-SCROLL-OWNERSHIP.md"
)
CAROUSEL = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/components/"
    "carousels/carousel.js"
)
CAROUSEL_STRATEGY = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/components/"
    "carousels/carousel-strategy.js"
)
CAROUSEL_STRATEGY_TEST = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/components/"
    "carousels/__tests__/carousel-strategy.test.js"
)
UNCONTAINED_CAROUSEL = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/components/"
    "carousels/uncontained-carousel.js"
)
MULTI_BROWSE_CAROUSEL = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/components/"
    "carousels/multi-browse-carousel.js"
)
CENTERED_HERO_CAROUSEL = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/components/"
    "carousels/centered-hero-carousel.js"
)
CAROUSEL_ITEM = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/components/"
    "carousels/carousel-item.js"
)
CAROUSEL_ITEM_MEDIA = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/components/"
    "carousels/carousel-item-media.js"
)
CAROUSEL_ITEM_TEXT = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/components/"
    "carousels/carousel-item-text.js"
)
CAROUSEL_CSS = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/"
    "carousel.css"
)
STYLE_INDEX = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/styles/index.css"
)
STYLEBOOK_INDEX = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/pages/stylebook/index.js"
)
STYLEBOOK_PAGE = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/pages/stylebook/"
    "components/carousels/index.js"
)
STYLEBOOK_CSS = ROOT / (
    "products/wordpress/plugins/axismundi/src/apps/frontend/pages/stylebook/"
    "components/carousels/carousels.css"
)
APP = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/app.js"
ROUTE = ROOT / "products/wordpress/plugins/axismundi/includes/route.php"
PLUGIN = ROOT / "products/wordpress/plugins/axismundi/axismundi.php"

TOKEN = re.compile(r"md\.[a-z0-9.\-]+")
ROW = re.compile(r"^  (md\.[a-z0-9.\-]+)\s+[A-Z_]+\s+(.+?)\s*$")


def rows(text: str) -> dict[str, str]:
    return {
        match.group(1): match.group(2)
        for line in text.splitlines()
        if (match := ROW.match(line))
    }


def strings(value: Any) -> list[str]:
    if isinstance(value, str):
        return [value]
    if isinstance(value, dict):
        return [item for child in value.values() for item in strings(child)]
    if isinstance(value, list):
        return [item for child in value for item in strings(child)]
    return []


def declarations(text: str) -> str:
    """Discard explanations so a comment cannot satisfy a CSS contract."""
    return re.sub(r"/\*.*?\*/", "", text, flags=re.DOTALL)


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    source = TABLE.read_text(encoding="utf-8")
    home = HOME_PLAN.read_text(encoding="utf-8")
    scroll = SCROLL_DECISION.read_text(encoding="utf-8")
    carousel = CAROUSEL.read_text(encoding="utf-8")
    carousel_strategy = CAROUSEL_STRATEGY.read_text(encoding="utf-8")
    carousel_strategy_test = CAROUSEL_STRATEGY_TEST.read_text(encoding="utf-8")
    uncontained_carousel = UNCONTAINED_CAROUSEL.read_text(encoding="utf-8")
    multi_browse_carousel = MULTI_BROWSE_CAROUSEL.read_text(encoding="utf-8")
    centered_hero_carousel = CENTERED_HERO_CAROUSEL.read_text(encoding="utf-8")
    carousel_item = CAROUSEL_ITEM.read_text(encoding="utf-8")
    carousel_item_media = CAROUSEL_ITEM_MEDIA.read_text(encoding="utf-8")
    carousel_item_text = CAROUSEL_ITEM_TEXT.read_text(encoding="utf-8")
    carousel_css = declarations(CAROUSEL_CSS.read_text(encoding="utf-8"))
    style_index = declarations(STYLE_INDEX.read_text(encoding="utf-8"))
    stylebook_index = STYLEBOOK_INDEX.read_text(encoding="utf-8")
    stylebook_page = STYLEBOOK_PAGE.read_text(encoding="utf-8")
    stylebook_css = declarations(STYLEBOOK_CSS.read_text(encoding="utf-8"))
    app = APP.read_text(encoding="utf-8")
    route = ROUTE.read_text(encoding="utf-8")
    plugin = PLUGIN.read_text(encoding="utf-8")
    token_rows = rows(source)
    live_source, _, skipped_source = source.partition("skipped (deprecated):")
    live_component_tokens = set(
        re.findall(r"md\.comp\.carousel-item[.a-z-]+", live_source)
    )
    skipped_component_tokens = set(
        re.findall(r"md\.comp\.carousel-item[.a-z-]+", skipped_source)
    )
    skipped_system_tokens = set(
        re.findall(r"md\.sys\.[.a-z-]+", skipped_source)
    )
    recorded_tokens = {
        token
        for value in strings(data)
        for token in TOKEN.findall(value)
        if token.startswith("md.comp.carousel-item")
        and token != "md.comp.carousel-item"
    }
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

    meta = data["meta"]
    check(meta["table_revision"] in source, "recorded table revision is absent")
    check(meta["namespace"] == "md.comp.carousel-item", "namespace drifted")
    check(len(live_component_tokens) == 26, "live component token count is not 26")
    check(len(skipped_component_tokens) == 1, "deprecated component token count is not 1")
    check(
        "md.sys.color.surface-tint" in skipped_system_tokens,
        "the related deprecated surface-tint system row is no longer recorded",
    )
    check(meta["live_component_token_count"] == 26, "yml live count is not 26")
    check(
        meta["deprecated_component_token_count"] == 1,
        "yml deprecated component count is not 1",
    )
    check(
        meta["skipped_related_system_token_count"] == 1,
        "yml skipped related system count is not 1",
    )
    check(
        live_component_tokens <= recorded_tokens,
        "one or more live carousel-item tokens are absent from carousel.yml",
    )
    check(
        recorded_tokens == live_component_tokens | skipped_component_tokens,
        "carousel.yml token references no longer equal the captured component rows",
    )

    item = data["item"]
    row_is(item["container_color_token"], "md.sys.color." + item["container_color_role"])
    row_is(
        item["container_shape_token"],
        "md.sys.shape." + item["container_shape_role"],
    )
    row_is(item["container_shadow_color_token"], "md.sys.color.shadow")
    check(
        item["container_shape_resolved"] == 28,
        "resolved extra-large item shape is no longer 28dp",
    )
    check(
        item["widths"]["small"] == {
            "dynamic": True,
            "minimum": 40,
            "maximum": 56,
        },
        "small item width range no longer records 40-56dp dynamic",
    )

    layouts = data["layouts"]
    check(
        set(layouts) == {"multi-browse", "uncontained", "hero", "full-screen"},
        "the four layout strategies changed",
    )
    uncontained = layouts["uncontained"]
    check(uncontained["item_width"] == "unresolved", "Uncontained width was decided")
    check(
        uncontained["item_width_published"] is False,
        "Uncontained width is presented as published",
    )
    check(
        uncontained["padding_inline_trailing"] == 0
        and uncontained["padding_inline_trailing_published_here"] is False
        and "Inferred" in uncontained["padding_inline_trailing_provenance"],
        "Uncontained trailing zero lost its inference provenance",
    )
    check(
        layouts["hero"]["minimum_items_by_alignment"]["center"]
        == {"large": 1, "small": 2},
        "center-aligned Hero no longer adds the second small preview",
    )
    check(
        layouts["full-screen"]["required_scroll_behavior"] == "snap",
        "full-screen no longer requires snap scrolling",
    )

    expected_forbidden = [
        {"layout": "full-screen", "scroll_behavior": "default"},
        {"layout": "multi-browse", "scroll_behavior": "default"},
        {"layout": "hero", "scroll_behavior": "default"},
        {
            "layout": "full-screen",
            "window_size": "compact-or-medium",
            "orientation": "landscape",
        },
        {"configuration": "multi-aspect", "layout": "not-uncontained"},
        {
            "window_size": "compact",
            "item_has_text": True,
            "visible_item_count": "greater-than-3",
        },
    ]
    forbidden = data["constraints"]
    check(len(forbidden) == 6, "forbidden combination count is not 6")
    for combination in expected_forbidden:
        check(
            any(
                entry.get("combination") == combination
                and entry.get("allowed") is False
                for entry in forbidden
            ),
            f"forbidden combination is missing: {combination}",
        )

    reduced = data["behavior"]["reduced_motion"]
    check(reduced["all_items_same_size"] is True, "reduced motion is not uniform")
    check(
        reduced["geometry_path"] == "uniform-uncontained",
        "reduced-motion geometry is detached from Uncontained",
    )
    pointer_drag = data["behavior"]["pointer_drag"]
    check(
        pointer_drag["drag_activation_threshold"] == 6
        and pointer_drag["drag_activation_threshold_published"] is False,
        "the local 6px drag threshold is missing or presented as M3",
    )
    check(
        pointer_drag["web_mechanism"]
        == "native-touch-scroll-and-enhanced-mouse-or-pen-drag",
        "pointer enhancement no longer preserves native touch scrolling",
    )
    check(
        "const DRAG_THRESHOLD = 6;" in carousel
        and "'touch' === event.pointerType" in carousel,
        "Carousel drag no longer matches its recorded local mechanism",
    )
    check(
        "track.setPointerCapture" in carousel
        and "track.scrollLeft =" in carousel
        and "drag.startScrollLeft + ( rtl ? distance : -distance )" in carousel
        and "preventDraggedClick" in carousel,
        "mouse or pen drag lost capture, movement or click suppression",
    )

    accessibility = data["accessibility"]
    container = accessibility["container"]
    check(container["published_role_name"] == "container", "published role wording changed")
    check(
        container["web_role"] == "group"
        and container["web_role_source"] == "WAI-ARIA-APG-carousel-pattern"
        and container["aria_roledescription"] == "carousel",
        "web role is no longer the traceable APG group mapping",
    )
    check(
        accessibility["skip_over_items"]
        == {
            "required": True,
            "mechanism": "ArrowUp-or-ArrowDown-focuses-adjacent-page-control",
        },
        "skip requirement lost its explicit keyboard mechanism",
    )
    check('role="group"' in carousel, "Carousel is not an ARIA group")
    check(
        "aria-roledescription={ roleDescription }" in carousel
        and "roleDescription = 'carousel'" in carousel,
        "Carousel lost its APG role description",
    )
    check(
        "aria-label={ label || undefined }" in carousel,
        "Carousel no longer requires an accessible name in the DOM",
    )
    check(
        "ArrowLeft" in carousel
        and "ArrowRight" in carousel
        and "ArrowUp" in carousel
        and "ArrowDown" in carousel,
        "Carousel keyboard contract is incomplete",
    )
    check(
        "tabIndex" not in carousel_item,
        "CarouselItem introduced a roving or synthetic tab order",
    )
    check(
        "aria-roledescription={ roleDescription }" in carousel_item
        and "roleDescription = 'slide'" in carousel_item
        and 'role="group"' in carousel_item,
        "CarouselItem lost its non-focusable APG slide wrapper",
    )
    check(
        "data-carousel-action" in carousel_item
        and "const Host = isLink ? 'a' : 'button'" in carousel_item,
        "CarouselItem is no longer one native action",
    )
    # M3 requires the current and total values in every item's name, and the
    # wording is ours. A solidus is not: it is spoken as "slash", as "of", or
    # not at all, which is why the default follows APG's own carousel example.
    # The formatter is injectable so a localized name needs no fork, the same
    # reason roleDescription is a prop.
    check(
        "aria-label={ positionLabel }" in carousel_item
        and "aria-label={ label || undefined }" in carousel_item
        and "formatPosition( position, setSize )" in carousel_item,
        "CarouselItem label no longer includes current and total",
    )
    check(
        "`${ position } of ${ setSize }`" in carousel_item,
        "the default position name is no longer APG's spoken wording",
    )
    check(
        "/${ setSize }`" not in carousel_item,
        "the position name is punctuation again; a solidus is read inconsistently",
    )
    check(
        "formatPosition = defaultPositionLabel" in carousel_item
        and "formatPosition," in carousel,
        "the position name is no longer injectable for localization",
    )
    check(
        "data-layout={ implementedLayout }" in carousel
        and "data-scroll-behavior={ effectiveBehavior }" in carousel,
        "Carousel no longer exposes its geometry and scroll behavior",
    )
    check(
        "new window.ResizeObserver( measureKeylines )" in carousel
        and "multiBrowseStrategy" in carousel
        and "heroStrategy" in carousel,
        "Carousel is no longer driven by the shared measured strategy path",
    )
    check(
        "function keylineProfile" not in carousel
        and "KEYLINE_CONTAINER_BREAKPOINT" not in carousel
        and "LARGE_REFERENCE_WIDTH" not in carousel,
        "Figma specimen profiles returned as runtime Carousel inputs",
    )
    check(
        "a0c645de83b353f2aa79c3b33a11e8ba853ca1e4" in carousel_strategy
        and "Apache-2.0" in carousel_strategy
        and "createCarouselStrategy" in carousel_strategy
        and "keylinesForScrollOffset" in carousel_strategy
        and "scrollOffsetForItem" in carousel_strategy,
        "the AndroidX-derived strategy lost its pinned source or core contract",
    )
    check(
        "keylinesForScrollOffset" in carousel
        and "itemGeometry" in carousel
        and "track.addEventListener( 'scroll', scheduleKeylines" in carousel
        and "window.requestAnimationFrame" in carousel,
        "Carousel lost scroll-position keyline interpolation",
    )
    check(
        "rtl ? -track.scrollLeft : track.scrollLeft" in carousel
        and "rtl ? distance : -distance" in carousel,
        "Carousel morph or pointer drag no longer follows logical RTL scrolling",
    )
    check(
        "strategy.startShiftDistance" in carousel
        and "strategy.endShiftDistance" in carousel
        and "keylinesForScrollOffset" in carousel
        and "root.dataset.keylineState" in carousel,
        "Carousel no longer shifts through start/default/end keyline states",
    )
    check(
        "keylineState = 'start'" in carousel
        and "centeredHeroStrategy" in carousel_strategy
        and "startKeylineSteps" in carousel_strategy,
        "Center-aligned Hero can no longer make its first item focal",
    )
    check(
        'layout="uncontained"' in uncontained_carousel
        and 'scrollBehavior="default"' in uncontained_carousel
        and "itemWidth" in uncontained_carousel
        and 'layout="multi-browse"' in multi_browse_carousel
        and 'scrollBehavior="snap"' in multi_browse_carousel
        and 'alignment="center"' in centered_hero_carousel
        and 'layout="hero"' in centered_hero_carousel,
        "public Carousel wrappers no longer match the Android variant contracts",
    )
    check(
        "data-multi-aspect={ implementedMultiAspect ? '' : undefined }" in carousel
        and "multiAspect is an Uncontained configuration" in carousel,
        "Multi-aspect ratio stopped being an explicit Uncontained configuration",
    )
    check(
        "requires snap scrolling" in carousel
        and "data-layout={ implementedLayout }" in carousel
        and "scrollOffsetForItem" in carousel
        and 'scroll-snap-type: none' in carousel_css,
        "Advanced Carousel layouts no longer force the published snap path",
    )
    check(
        "<Elevation level={ 0 } />" in carousel_item,
        "CarouselItem cannot express its published hover elevation",
    )

    figma = data["figma_reference"]
    expected_ratios = ["16:9", "4:3", "1:1", "3:4", "9:16"]
    check(
        figma["source_kind"]
        == "community-kit-implementation-reference-not-token-source"
        and figma["contexts_are_public_api"] is False,
        "Figma context is being treated as a token source or runtime API",
    )
    check(
        figma["item_aspect_ratio_building_blocks"] == expected_ratios,
        "Figma's five item aspect-ratio building blocks drifted",
    )
    check(
        figma["specimen_only_controls"] == ["Show 4th item", "Show 5th item"],
        "Figma specimen item toggles changed or became product state",
    )
    measured = figma["measured_profiles"]
    check(
        measured["hero"]["mobile"]["items"]
        == [
            {"role": "large", "inline_size": 316, "block_size": 205},
            {"role": "small", "inline_size": 56, "block_size": 205},
        ]
        and measured["multi-browse"]["mobile"]["items"]
        == [
            {"role": "large", "inline_size": 188, "block_size": 205},
            {"role": "medium", "inline_size": 120, "block_size": 205},
            {"role": "small", "inline_size": 56, "block_size": 205},
        ],
        "Figma Mobile Hero or Multi-browse profile drifted",
    )
    check(
        measured["hero"]["tablet"]["items"]
        == measured["multi-browse"]["tablet"]["items"]
        == [
            {"role": "large", "inline_size": 184, "block_size": 204},
            {"role": "large", "inline_size": 184, "block_size": 204},
            {"role": "medium", "inline_size": 120, "block_size": 204},
            {"role": "small", "inline_size": 56, "block_size": 204},
        ],
        "Figma Tablet Hero or Multi-browse profile drifted",
    )
    check(
        measured["center-aligned-hero"]["mobile"]["items"]
        == [
            {"role": "small", "inline_size": 56, "block_size": 205},
            {"role": "large", "inline_size": 252, "block_size": 205},
            {"role": "small", "inline_size": 56, "block_size": 205},
        ]
        and measured["center-aligned-hero"]["tablet"]["items"]
        == measured["hero"]["tablet"]["items"],
        "Figma Center-aligned Hero profiles drifted",
    )
    multi_aspect = measured["multi-aspect-ratio"]
    check(
        multi_aspect["ratios"] == expected_ratios
        and multi_aspect["item_inline_sizes"]
        == [362.22, 270.67, 204, 153.25, 116]
        and multi_aspect["item_block_size"] == 204
        and multi_aspect["contexts"]["mobile"]["resolved_container_block_size"]
        == 220
        and multi_aspect["contexts"]["tablet"]["resolved_container_block_size"]
        == 220,
        "Figma Multi-aspect ratio profiles drifted",
    )
    check(
        all(f"'{ratio}'" in carousel_item_media for ratio in expected_ratios)
        and 'data-aspect-ratio={ ratio }' in carousel_item_media,
        "CarouselItemMedia no longer implements the five recorded ratios",
    )
    check(
        "supportingText" in carousel_item_text
        and "ax-carousel-item-text__label" in carousel_item_text
        and "ax-carousel-item-text__supporting" in carousel_item_text,
        "CarouselItemText lost its required label or optional supporting text",
    )
    check(
        "data-appearance={ textAppearance }" in carousel_item_text
        and "appearance = 'stacked'" in carousel_item_text,
        "CarouselItemText lost its explicit overlay appearance",
    )

    check(
        re.search(
            r"flex:\s*0 0 var\(\s*--ax-carousel-item-width\s*\);",
            carousel_css,
        )
        is not None,
        "Uncontained width gained a fallback or stopped using its project property",
    )
    check(
        re.search(r"(?m)^\s*--ax-carousel-item-width\s*:", carousel_css) is None,
        "component CSS decides the unpublished Uncontained width",
    )
    check(
        re.search(
            r"--ax-carousel-padding-inline-start:\s*var\(\s*--md-sys-measurement-space200\s*\)",
            carousel_css,
        )
        is not None
        and "--ax-carousel-padding-inline-end: 0px;" in carousel_css
        and re.search(
            r"--ax-carousel-padding-block:\s*var\(\s*--md-sys-measurement-space100\s*\)",
            carousel_css,
        )
        is not None
        and re.search(
            r"--ax-carousel-item-gap:\s*var\(\s*--md-sys-measurement-space100\s*\)",
            carousel_css,
        )
        is not None,
        "Uncontained 16/0 inline, 8 block or 8 gap geometry drifted",
    )
    check(
        re.search(
            r"--md-comp-carousel-item-container-shape:\s*var\(\s*--md-sys-shape-corner-value-extra-large\s*\)",
            carousel_css,
        )
        is not None,
        "CarouselItem stopped consuming the published extra-large shape token",
    )
    check(
        re.search(
            r"outline:\s*3px solid var\(\s*--md-sys-color-secondary\s*\)",
            carousel_css,
        )
        is not None
        and "outline-offset: 2px;" in carousel_css,
        "CarouselItem focus indicator drifted from 3dp with 2dp outer offset",
    )
    check(
        "opacity: 0.38;" in carousel_css,
        "CarouselItem disabled opacity drifted from 0.38",
    )
    check(
        "scroll-snap-type: inline mandatory;" in carousel_css,
        "Uncontained lost its optional snap path",
    )
    check(
        ".ax-carousel__items[data-dragging]" in carousel_css
        and "scroll-snap-type: none !important;" in carousel_css
        and "settleToNearestItem" in carousel
        and "track.scrollTo" in carousel,
        "pointer drag no longer suspends snap and settles to a reachable item",
    )
    check(
        "scroll-padding-inline-start: var(--ax-carousel-padding-inline-start);"
        in carousel_css
        and ".ax-carousel-item:last-child" in carousel_css
        and "scroll-snap-align: end;" in carousel_css,
        "advanced Carousel start padding or final end snap is no longer reachable",
    )
    check(
        "--ax-carousel-mask-width" in carousel_css
        and "--ax-carousel-item-offset" in carousel_css
        and "ax-carousel-item__content" in carousel_item
        and "ax-carousel-item__content" in carousel_css,
        "Carousel lost its stable slot and masked full-size content shell",
    )
    for ratio, declaration in {
        "16:9": "aspect-ratio: 16 / 9;",
        "4:3": "aspect-ratio: 4 / 3;",
        "1:1": "aspect-ratio: 1;",
        "3:4": "aspect-ratio: 3 / 4;",
        "9:16": "aspect-ratio: 9 / 16;",
    }.items():
        check(
            f'data-aspect-ratio="{ratio}"' in carousel_css
            and declaration in carousel_css,
            f"CarouselItemMedia CSS lost the {ratio} building block",
        )
    check(
        "prefers-reduced-motion: reduce" in carousel_css
        and "scroll-behavior: auto;" in carousel_css,
        "Carousel reduced-motion path no longer disables animated scrolling",
    )
    check(
        'data-multi-aspect' in carousel_css
        and "--ax-carousel-multi-aspect-item-width" in carousel_css
        and "--ax-carousel-item-block-size" in carousel_css,
        "Multi-aspect ratio no longer derives Uncontained item geometry from its media",
    )
    check(
        re.search(
            r"data-multi-aspect[^}]+flex-grow:\s*0;[^}]+flex-shrink:\s*0;",
            carousel_css,
            re.DOTALL,
        )
        is not None,
        "Multi-aspect items can shrink instead of overflowing horizontally",
    )
    check(
        'data-appearance="overlay"' in carousel_css
        and "inset: 0;" in carousel_css
        and "align-content: end;" in carousel_css
        and "rgb(0 0 0 / 50%)" in carousel_css
        and "--md-sys-typescale-label-small-size" in carousel_css
        and "--md-ref-palette-neutral-100" in carousel_css,
        "Figma Carousel text overlay styling drifted",
    )
    check(
        data["figma_reference"]["optional_text_building_block"]["overlay"]["gradient_extent"]
        == "full-item"
        and data["figma_reference"]["optional_text_building_block"]["overlay"]["content_alignment"]
        == "block-end",
        "Carousel text gradient no longer covers the full item",
    )
    check(
        "reducedMotion.matches" in carousel
        and "item.style.removeProperty( '--ax-carousel-item-width' )" in carousel
        and "item.style.removeProperty( '--ax-carousel-item-offset' )" in carousel
        and "item.style.removeProperty( '--ax-carousel-mask-width' )" in carousel
        and "reducedMotion.addEventListener" in carousel,
        "Advanced Carousel layouts no longer return to uniform consumer sizing for reduced motion",
    )
    # The morph was a local policy while the keylines were measured Figma
    # profiles. It is not one any more: the strategy is ported from a pinned
    # AndroidX revision and its geometry is held by that upstream's own test
    # vectors. The record has to say which of those two it is, because the
    # difference decides whether a future figure may be invented or has to be
    # derived.
    morph = data["source_boundaries"]["implementation_status"]
    check(
        morph["scroll_position_morph"]
        == "implemented-with-androidx-derived-keyline-strategy",
        "Carousel morph is no longer recorded as the AndroidX-derived strategy",
    )
    check(
        morph["scroll_position_morph_model"]
        == "large-slot-plus-interpolated-multi-step-keylines",
        "Carousel morph no longer interpolates multi-step keylines over a stable slot",
    )
    check(
        "not inputs to this path" in morph["scroll_position_morph_note"],
        "the record no longer keeps Figma's profiles out of the runtime inputs",
    )
    # THE THRESHOLD IS GONE, SO REQUIRING ITS RECORD WOULD BE REQUIRING A FALSE
    # ONE. An earlier generation picked between two measured Figma profiles at a
    # 600px container width, and this check existed to stop that number being
    # mistaken for the window breakpoint of the same value. The AndroidX
    # strategy has no such branch -- it fills any width from a preferred item
    # size -- so the pin that matters is the prohibition above, which keeps the
    # retired constants out of the engine, plus this one, which keeps the record
    # from growing the key back.
    check(
        "container_threshold"
        not in data["source_boundaries"]["implementation_policy"],
        "the retired 600px container threshold is back in the record",
    )
    check(
        any(
            discrepancy.get("subject") == "Hero tablet medium item"
            for discrepancy in data["discrepancies"]
        ),
        "Tablet Hero's measured medium-item discrepancy was dropped",
    )
    # The two Google implementations agree on the centred-hero split and the
    # Figma frame does not, so the Stylebook deliberately shows both numbers.
    # Without this entry the next reader finds a static profile that the runtime
    # never reproduces and reads it as a bug.
    # MASKING AND SNAPPING ARE DIFFERENT QUESTIONS, AND ONE LIST USED TO ANSWER
    # BOTH. Uncontained is masked at the edges like the others -- M3 makes it
    # the one layout whose items need not be fully visible -- while only
    # multi-browse and hero are required to snap, because uncontained is
    # published with both scrolling behaviours.
    check(
        "const KEYLINE_LAYOUTS = [ 'uncontained', 'multi-browse', 'hero' ];"
        in carousel,
        "uncontained is out of the keyline path again, so its edges stop masking",
    )
    check(
        "const SNAP_REQUIRED_LAYOUTS = [ 'multi-browse', 'hero' ];" in carousel
        and "ADVANCED_LAYOUTS" not in carousel,
        "snap is forced on a layout the guidelines let the caller choose for",
    )

    # The two paths mean different things by the strategy's item size, and
    # carousel-strategy.test.js holds the difference. Writing it as the
    # uncontained box would widen every item by one gap.
    check(
        "const boxSize = uncontained ? declaredItemWidth : itemSize;" in carousel
        and "if ( ! uncontained ) {" in carousel,
        "uncontained no longer keeps the caller's item width",
    )
    check(
        "const stride = uncontained ? itemSize : itemSize + gap;" in carousel,
        "the uncontained stride counts its spacing twice or not at all",
    )
    check(
        "uncontained reports a stride while the advanced layouts report a box"
        in carousel_strategy_test,
        "nothing holds the stride-versus-box difference between the two paths",
    )
    # Multi-aspect is masked too. It was briefly excluded on the grounds that a
    # layout of varying widths has no single stride to interpolate against,
    # which confused a limit of the uniform arithmetic with a property of the
    # layout: M3 calls it "the same layout as the uncontained carousel" with
    # items of various sizes. Each item now carries its own box and the centres
    # are a running sum, which reduces to the old arithmetic when every box is
    # the same.
    check(
        "implementedMultiAspect" not in carousel.split( "function measureKeylines" )[0].split( "KEYLINE_LAYOUTS.includes" )[-1],
        "multi-aspect is excluded from masking again",
    )
    check(
        "boxes = domItems.map(" in carousel
        and "const box = geometryBoxes[ index ] ?? boxSize;" in carousel
        and "const centers = boxes.map(" in carousel,
        "the keyline path lost its per-item boxes, so varying widths cannot be masked",
    )

    # Reduced motion asks for one size, not for none. Removing the widths left
    # flex-basis with nothing to resolve and the advanced layouts collapsed to
    # an image's intrinsic width -- 31px and 49px when this was measured.
    # A classic scrollbar is laid out inside the border box, so it made the
    # component 15px taller than the composition it implements: 236 where
    # Compose's reference is 221, a 205dp item between 8dp of block padding.
    # Drag, wheel and keyboard all still scroll without it.
    # A scroll container clips at its padding box, so the anchor keylines --
    # the off-stage positions an item interpolates towards as it leaves -- were
    # painted inside the 16px strip as 10px slivers after the small item. M3
    # requires carousel items to be fully visible on-screen and names
    # uncontained as the one exception, so the contained layouts clip to their
    # content box and uncontained still bleeds past the edge.
    check(
        "const offStage =" in carousel
        and "item.style.visibility = offStage ? 'hidden' : '';" in carousel,
        "items resting on the anchor keylines are painted in the padding again",
    )
    check(
        "clip-path: inset(" not in carousel_css,
        "the track clips to its content box again, which slices items on their way out",
    )

    check(
        "scrollbar-width: none;" in carousel_css
        and ".ax-carousel__items::-webkit-scrollbar" in carousel_css,
        "the carousel shows a scrollbar again, which adds to its published height",
    )

    check(
        "function applyUniformGeometry(" in carousel
        and "applyUniformGeometry( domItems, uncontained, boxSize )" in carousel,
        "reduced motion no longer gives the advanced layouts a uniform width",
    )

    subjects = {d.get("subject") for d in data["discrepancies"]}
    check(
        "Center-aligned hero large-to-small split" in subjects,
        "the centred-hero split between Figma and the implementations is unrecorded",
    )
    check(
        "Uncontained edge masking" in subjects,
        "the uncontained masking gap is unrecorded",
    )

    # Named with Flutter's spellings on purpose: a reader can find the upstream
    # definition from the name, which a translated name would cost.
    gaps = {gap["name"] for gap in data["upstream_api_gaps"]}
    check(
        {"initialItem", "shrinkExtent", "consumeMaxWeight"} <= gaps,
        f"an upstream API gap was dropped from the record: {sorted(gaps)}",
    )

    # A measurement specimen that hides most of its measurement is not one.
    # Multi-aspect needs 1170px for the five widths it exists to show.
    check(
        "overflow-x: auto;" in stylebook_css
        and "overflow: hidden;" not in stylebook_css.split("__static-track")[1].split("}")[0],
        "the static profile track hides the widths it exists to show",
    )
    check(
        2 == stylebook_page.count( 'layout="uncontained"' ),
        "the Stylebook has no runtime multi-aspect carousel, only a static one",
    )
    check(
        "FIGMA_ASPECT_RATIOS.length" in stylebook_page,
        "multi-aspect ratios no longer cycle, so items past the fifth have none",
    )

    check(
        "@import url( './components/carousel.css' );" in style_index,
        "Carousel component CSS is not in the frontend style graph",
    )
    check(
        "itemWidth={ 280 }" in stylebook_page
        and "local policy, not M3" in stylebook_page,
        "Stylebook width is no longer visibly isolated as a local VQA policy",
    )
    check(
        "CarouselItemMedia" in stylebook_page
        and "CarouselItemText" in stylebook_page
        and "FIGMA_ASPECT_RATIOS" in stylebook_page
        and "https://picsum.photos/seed/" in stylebook_page
        and '<img alt="" draggable="false"' in stylebook_page,
        "Stylebook no longer exercises the Figma-derived building blocks",
    )
    check(
        "KeylineSample" in stylebook_page
        and 'layout="hero"' in stylebook_page
        and 'layout="multi-browse"' in stylebook_page
        and 'data-context="Mobile"' in stylebook_css
        and 'data-context="Tablet"' in stylebook_css,
        "Stylebook no longer exposes the measured Hero/Multi-browse profiles",
    )
    check(
        'alignment="center"' in stylebook_page
        and "multiAspect" in stylebook_page
        and "--ax-carousel-multi-aspect-item-width: 362.22px" in stylebook_css
        and "--ax-carousel-multi-aspect-item-width: 116px" in stylebook_css
        and 'appearance="overlay"' in stylebook_page,
        "Stylebook lost Center-aligned Hero or Multi-aspect ratio fixtures",
    )
    implementation_status = data["source_boundaries"]["implementation_status"]
    check(
        implementation_status["measured_keyline_profiles"] == "implemented"
        and implementation_status["center_aligned_hero"] == "implemented"
        and implementation_status["multi_aspect_ratio"]
        == "implemented-as-uncontained-configuration"
        and implementation_status["carousel_text_overlay"] == "implemented"
        and implementation_status["resize_recalculation"] == "implemented"
        and implementation_status["scroll_position_morph"]
        == "implemented-with-androidx-derived-keyline-strategy",
        "Carousel scroll-position morph status changed without an explicit record",
    )
    # This composite asks which parts exist. It used to also require a phrase in
    # the note, which fires on a reword and says nothing a reword could break.
    # The note's two load-bearing claims are pinned where they can actually
    # fail: Figma's profiles staying out of the runtime inputs, above, and the
    # strategy owning the snap rather than native CSS, in the snap checks.
    check(
        "scrollLeft" in stylebook_page,
        "Stylebook readout no longer exposes whether drag actually scrolls",
    )
    check(
        stylebook_page.count("</UncontainedCarousel>") == 1
        and stylebook_page.index("</UncontainedCarousel>")
        < stylebook_page.index('className="ax-stylebook-carousels__show-all"'),
        "Show all moved inside the Uncontained Carousel container",
    )
    check(
        stylebook_page.count("UncontainedCarousel") >= 3
        and stylebook_page.count("MultiBrowseCarousel") >= 2
        and stylebook_page.count("CenteredHeroCarousel") >= 2,
        "Stylebook no longer exercises all three public Carousel wrappers",
    )
    check(
        'href="#all-items"' in stylebook_page
        and 'id="all-items"' in stylebook_page,
        "Stylebook lost the required non-horizontal Show all path",
    )
    check(
        "StylebookCarouselsPage" in stylebook_index
        and "'carousels' === component" in stylebook_index
        and "/social/stylebook/components/carousels" in stylebook_index,
        "Stylebook does not dispatch or link the Carousel page",
    )
    check(
        app.count("carousels") == 1,
        "frontend route parser does not name carousels exactly once",
    )
    check(
        route.count("carousels") == 2,
        "WordPress route and public-route guard do not both admit carousels",
    )
    rewrite = re.search(r"AXISMUNDI_CAPSTONE_REWRITE_VERSION\s+=\s+'(\d+)'", plugin)
    check(
        rewrite is not None and 16 <= int(rewrite.group(1)),
        "rewrite version was not advanced for the Carousel route",
    )
    check(
        "Cross-component rule" in accessibility["items"]["nested_controls_source"]
        and "Card accessibility" in accessibility["items"]["nested_controls_source"],
        "nested-control prohibition lost its cross-component source",
    )
    alternative = accessibility["horizontal_scroll_alternative"]
    check(
        alternative["required_on_vertically_scrolling_pages"] is True,
        "horizontal-scroll alternative is no longer required",
    )
    check(
        alternative["exception_layouts"] == ["full-screen"],
        "full-screen is no longer the only Show all exception",
    )
    check(
        alternative["with_header"]["icon_button_size"] == 48,
        "header arrow is no longer 48dp",
    )
    check(
        alternative["without_header"]["padding"] == 4,
        "Show all button padding is no longer 4dp",
    )

    # THE TABLE IS THE RECORD, NOT THE SENTENCE. An earlier version pinned the
    # Korean sentence that states the count, including its markdown bold. That
    # fires on a reword that changes nothing, and the sentence is not where the
    # fact lives: the section table is. Four sections carry a Carousel; sections
    # 3 and 5 are a Card collection and a List, and saying so is the correction
    # this plan exists to carry.
    section_rows = [line for line in home.splitlines() if line.startswith("| Section ")]
    check(6 == len(section_rows), f"the Home plan lists {len(section_rows)} sections, not six")
    check(
        4 == sum(1 for row in section_rows if '| 있음 |' in row),
        "the Home plan no longer counts four Carousel sections",
    )
    for number in ("3", "5"):
        row = next(r for r in section_rows if r.startswith(f"| Section {number} "))
        check(
            '없음' in row,
            f"Home section {number} is no longer recorded as carrying no Carousel",
        )

    # Losing a step from the order is a real regression, so these stay -- but
    # matched on the distinctive phrase rather than on the code block's spacing.
    for marker in (
        "Section 3: existing non-actionable Card + grid",
        "Section 5: ListItem",
        "ActionableCard",
    ):
        check(marker in home, f"Home implementation order lost: {marker}")

    # The Show all obligation is owned by the scroll decision, pinned below. A
    # loose word pair here passed on coincidence, so it is gone.
    check(
        "### 3-5. Carousel의 비수평 전체 목록 경로" in scroll
        and "선택적 enhancement가 아니라 필수 조합" in scroll,
        "scroll decision no longer makes the Carousel alternative mandatory",
    )

    if failures:
        print(f"carousel: {checks} checks, {len(failures)} failed")
        for failure in failures:
            print(" - " + failure)
        return 1

    print(f"carousel: {checks} checks, 0 failed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
