#!/usr/bin/env python3
"""Check the FAB record against the token tables it cites.

The component does not exist yet, so this file guards the record rather than an
implementation. Four things it exists to stop:

* A restated figure drifting from the token it names. Every `*_token` key is
  resolved through the tables, including the string-valued ones -- a shape or a
  colour role is compared with what the token actually points at, not only the
  lengths.
* The two generations in one table being tidied away. Which colour sets say
  `focused`/`hovered`, which say `focus`/`hover`, and which carry `lowered` are
  all measured off the table, so a record that normalises them fails here.
* The FAB menu being read off its token set. Web is told three times, in three
  different pages, to open a menu component instead; the citations that say so
  are pinned, because reading `md.comp.fab-menu` alone leads to the opposite
  conclusion and did.
* A stylebook route landing in some of its five places. The check is
  conditional: once the route appears anywhere it must appear everywhere.
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

try:
    import yaml
except ImportError:  # pragma: no cover
    print("PyYAML is required: pip install pyyaml", file=sys.stderr)
    raise SystemExit(2)


ROOT = Path(__file__).resolve().parent.parent.parent
STYLEGUIDE = ROOT / "products/styleguide"
DATA = STYLEGUIDE / "_data/fab.yml"
TABLES = ROOT / "products/wordpress/plugins/axismundi/docs/m3-token-tables"
RAW_FAB = TABLES / "SOURCE-M3-FLOATING-ACTION-BUTTON-RAW.md"
RAW_FAB_MENU = TABLES / "SOURCE-M3-FAB-MENU-RAW.md"
RAW_EXTENDED = TABLES / "SOURCE-M3-EXTENDED-FAB-RAW.md"
FRONTEND = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
NAV_RAIL = FRONTEND / "components/navigations/nav-rail.js"
STYLEBOOK_INDEX = FRONTEND / "pages/stylebook/index.js"
APP = FRONTEND / "app.js"
ROUTE = ROOT / "products/wordpress/plugins/axismundi/includes/route.php"

# Exact, not a floor. The menu record learned this the hard way: at a floor a
# renamed key took three checks with it and the run still passed. Raise this
# line in the same commit that adds a token.
EXPECTED_TOKEN_KEYS = 39

ENTRY = re.compile(r"^  (md\.[A-Za-z0-9._-]+)\s{2,}([A-Z_]+)\s+(.*?)\s*$")
SKIPPED = re.compile(r"^  - (md\.[A-Za-z0-9._-]+)$")

EXPRESSIVE_SETS = (
    "md.comp.fab.primary",
    "md.comp.fab.secondary",
    "md.comp.fab.tertiary",
    "md.comp.fab.primary-container",
    "md.comp.fab.secondary-container",
    "md.comp.fab.tertiary-container",
)
BASELINE_SHAPED_SETS = ("md.comp.fab.surface", "md.comp.fab.branded")


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)


class Table:
    """The raw token tables, parsed once and merged.

    Merged because the record spans two namespaces published as two pages, and
    a token that moved between them should still resolve.
    """

    def __init__(self, texts: list[str]) -> None:
        self.entries: dict[str, str] = {}
        self.deprecated: list[str] = []
        for text in texts:
            in_skipped = False
            for line in text.splitlines():
                if line.startswith("skipped (deprecated)"):
                    in_skipped = True
                if in_skipped:
                    deprecated = SKIPPED.match(line)
                    if deprecated:
                        self.deprecated.append(deprecated.group(1))
                    continue
                entry = ENTRY.match(line)
                if entry:
                    self.entries[entry.group(1)] = entry.group(3)

    def live(self, prefix: str) -> list[str]:
        """Live token names under a prefix, the prefix's own set only.

        `md.comp.fab.primary` must not drag in `md.comp.fab.primary-container`,
        which a plain `startswith` does.
        """
        return [
            name
            for name in self.entries
            if name.startswith(f"{prefix}.") and name not in self.deprecated
        ]

    def reference(self, token: str) -> str | None:
        value = self.entries.get(token)
        if value and value.startswith("-> "):
            return value[3:].split(" | ")[0].strip()
        return None

    def number(self, token: str) -> float | None:
        seen: set[str] = set()
        while token and token not in seen:
            seen.add(token)
            value = self.entries.get(token)
            if value is None:
                return None
            if value.startswith("-> "):
                token = value[3:].split(" | ")[0].strip()
                continue
            if not value.startswith("{"):
                # Opacities are published as bare scalars, not as a length
                # payload. Returning None here made three state checks pass by
                # comparing nothing.
                try:
                    return float(value.split(" | ")[0])
                except ValueError:
                    return None
            payload = json.loads(value.split(" | ")[0])
            if "value" in payload:
                return float(payload["value"])
            return None
        return None


def token_keys(node: object, path: str = "") -> list[tuple[str, dict]]:
    found: list[tuple[str, dict]] = []
    if isinstance(node, dict):
        for key, value in node.items():
            if isinstance(key, str) and key.endswith("_token") and isinstance(value, str):
                found.append((f"{path}.{key}", node))
            found.extend(token_keys(value, f"{path}.{key}"))
    elif isinstance(node, list):
        for index, value in enumerate(node):
            found.extend(token_keys(value, f"{path}[{index}]"))
    return found


def sweep_tokens(report: Report, data: dict, table: Table) -> None:
    sites = token_keys(data)
    report.check(
        len(sites) == EXPECTED_TOKEN_KEYS,
        f"the token sweep found {len(sites)} `*_token` keys, not the {EXPECTED_TOKEN_KEYS} it "
        "covers -- a rename has taken checks with it, or a new token needs this line raised",
    )

    for path, owner in sites:
        key = path.rsplit(".", 1)[1]
        base = key[: -len("_token")]
        token = owner[key]

        report.check(token in table.entries, f"{path}: `{token}` is not in the token tables")
        report.check(
            token not in table.deprecated,
            f"{path}: `{token}` is deprecated and must not be restated as live",
        )

        expected = owner.get(base)
        if isinstance(expected, bool):
            continue
        if isinstance(expected, (int, float)):
            actual = table.number(token)
            report.check(
                actual is not None and abs(actual - expected) < 1e-9,
                f"{path}: `{token}` resolves to {actual}, but `{base}` records {expected}",
            )
        elif isinstance(expected, str) and expected.startswith("md."):
            # The string-valued half of the record -- shapes, colour roles,
            # elevation levels, type scales, spacing. A record that names the
            # wrong role reads perfectly well, which is why this is checked.
            reference = table.reference(token) or ""
            report.check(
                reference == expected,
                f"{path}: `{token}` points at `{reference or 'a literal'}`, but `{base}` "
                f"records `{expected}`",
            )


def check_table_shape(report: Report, data: dict, table: Table) -> None:
    families = data["token_families"]
    live_fab = [name for name in table.live("md.comp.fab") if ".fab-menu." not in name]
    report.check(
        len(live_fab) == families["live_total"],
        f"the table carries {len(live_fab)} live `md.comp.fab` tokens, but the record says "
        f"{families['live_total']}",
    )

    arithmetic = families["arithmetic"]
    report.check(
        eval(arithmetic["expression"], {"__builtins__": {}}, {}) == arithmetic["equals"]
        == families["live_total"],
        "the recorded arithmetic must still sum to the live token total",
    )

    sizes = families["sizes"]
    size_tokens: list[str] = []
    for prefix in sizes["sets"]:
        own = [
            name
            for name in table.live(prefix)
            if name[len(prefix) + 1 :].count(".") == 1
            and not any(name.startswith(f"{other}.") for other in EXPRESSIVE_SETS + BASELINE_SHAPED_SETS)
        ]
        size_tokens.extend(own)
        for leaf in sizes["properties_each"]:
            report.check(
                f"{prefix}.{leaf}" in table.entries,
                f"{prefix}.{leaf} is missing from the table, so the size sets no longer have "
                "the shape the record describes",
            )

    expressive = families["expressive_colour_sets"]
    report.check(
        list(expressive["sets"]) == list(EXPRESSIVE_SETS),
        "the six expressive colour sets must stay named in the record, in the table's own names",
    )
    for prefix in EXPRESSIVE_SETS:
        own = table.live(prefix)
        report.check(
            len(own) == expressive["token_count_each"],
            f"{prefix} has {len(own)} live tokens, not the {expressive['token_count_each']} "
            "the record records",
        )
        # The generation marker. `focused` and `hovered` are what makes these
        # sets expressive; normalising them to `focus`/`hover` invents tokens.
        report.check(
            any(f"{prefix}.focused." in name for name in own)
            and any(f"{prefix}.hovered." in name for name in own),
            f"{prefix} no longer uses the past-participle state names the record reads it by",
        )
        report.check(
            not any(f"{prefix}.lowered." in name for name in own),
            f"{prefix} now publishes `lowered` tokens, which the record says the expressive "
            "sets do not -- the discrepancy entry needs rewriting, not deleting",
        )

    baseline = families["baseline_shaped_colour_sets"]
    for prefix, count in baseline["sets"].items():
        own = table.live(prefix)
        report.check(
            len(own) == count,
            f"{prefix} has {len(own)} live tokens, not the {count} the record records",
        )
        report.check(
            any(f"{prefix}.focus." in name for name in own)
            and any(f"{prefix}.hover." in name for name in own),
            f"{prefix} no longer uses the bare state names that mark it as the older shape",
        )
        report.check(
            any(f"{prefix}.lowered." in name for name in own),
            f"{prefix} no longer publishes `lowered`, which is the only place it survives",
        )

    report.check(
        sorted(data["colour_styles"]["lowered"]["exists_in"]) == sorted(BASELINE_SHAPED_SETS),
        "the record must keep saying that `lowered` survives only in the two sets M3 no longer "
        "recommends, because that is why the rail's resting elevation is a project decision",
    )


def check_sizes(report: Report, data: dict) -> None:
    sizes = data["sizes"]
    derivation = sizes["padding_is_derived"]
    report.check(
        derivation["expression"] == "( container_height - icon_size ) / 2",
        "the padding derivation must stay written as an expression, not a list of numbers",
    )
    for name in ("fab", "medium", "large", "small"):
        size = sizes[name]
        report.check(
            size["container_height"] == size["container_width"],
            f"sizes.{name}: a FAB container is square, and this one is not",
        )
        report.check(
            size["derived_icon_padding"] * 2 + size["icon_size"] == size["container_height"],
            f"sizes.{name}: the recorded padding does not centre the icon in the container",
        )
    report.check(
        sizes["square_invariant"] == "container_height == container_width",
        "the square invariant stays recorded as an expression, for the same reason",
    )
    report.check(
        "derived_icon_padding" in derivation["unpublished"],
        "the derived padding must stay marked unpublished: M3 publishes it only as an image",
    )


def check_colour_and_states(report: Report, data: dict, table: Table) -> None:
    colour = data["colour_styles"]
    report.check(
        colour["default"] == "primary-container",
        "primary container is M3's default FAB colour style",
    )
    live_names = [style["name"] for style in colour["live"]]
    report.check(
        sorted(live_names) == sorted(prefix.rsplit(".", 1)[1] for prefix in EXPRESSIVE_SETS),
        "the recorded colour styles must be exactly the six live sets, under the table's names",
    )
    for style in colour["live"]:
        container = f"md.comp.fab.{style['name']}.container.color"
        icon = f"md.comp.fab.{style['name']}.icon.color"
        report.check(
            table.reference(container) == style["container"],
            f"colour_styles: `{container}` points at `{table.reference(container)}`, not "
            f"`{style['container']}`",
        )
        report.check(
            table.reference(icon) == style["on_colour"],
            f"colour_styles: `{icon}` points at `{table.reference(icon)}`, not `{style['on_colour']}`",
        )
        # M3 states this in prose, and the table backs it: the state layer takes
        # the icon's colour, not the container's.
        layer = f"md.comp.fab.{style['name']}.hovered.state-layer.color"
        report.check(
            table.reference(layer) == style["on_colour"],
            f"colour_styles: `{layer}` must resolve to the icon colour `{style['on_colour']}`",
        )

    report.check(
        "size, not color" in colour["colour_is_not_a_variant"]
        and "configuration" in colour["colour_is_not_a_variant"],
        "the record must keep saying colour is a configuration and size is the variant",
    )

    states = data["states"]
    report.check(
        states["hovered"]["container_elevation"] == "md.sys.elevation.level4"
        and states["enabled"]["container_elevation"] == "md.sys.elevation.level3",
        "hover raises a FAB from level 3 to level 4; the record must keep both numbers",
    )
    report.check(
        states["disabled"]["published"] is False
        and ("remove" in states["disabled"]["rule"]
             or "shouldn't appear" in states["disabled"]["rule"]),
        "M3 publishes no disabled FAB, and the record must say an unavailable action removes it",
    )
    report.check(
        data["project_policy"]["disabled"].startswith("Not accepted"),
        "the policy must keep refusing a `disabled` prop, or a dimmed FAB arrives by default",
    )
    report.check(
        "Elevation level" in states["elevation_ownership"]
        and "md-sys-elevation-shadow-level" in states["elevation_ownership"],
        "the elevation entry must keep both halves: the primitive this app owns, and the "
        "custom properties the theme deliberately does not define",
    )


def check_prose(report: Report, data: dict) -> None:
    sources = data["prose_sources"]
    for name, entry in sources.items():
        report.check(
            isinstance(entry, dict) and set(entry) == {"source", "statement"},
            f"prose_sources.{name} must carry exactly a source and a statement",
        )
        report.check(
            str(entry.get("source", "")).startswith("https://m3.material.io/components/"),
            f"prose_sources.{name}: a statement without an M3 component page behind it is not "
            "a citation",
        )
    components = {
        str(entry["source"]).split("/components/")[1].split("/")[0] for entry in sources.values()
    }
    report.check(
        components == {"floating-action-button", "extended-fab", "fab-menu"},
        f"the record cites {sorted(components)}; all three FAB pages must stay cited, because "
        "the three components are recorded together",
    )


def check_fab_menu(report: Report, data: dict, table: Table) -> None:
    menu = data["fab_menu"]
    web = menu["on_web_it_is_a_fab_and_a_menu"]
    report.check(
        web["status"] == "published",
        "that web opens a menu component is published M3, not a project choice",
    )
    report.check(
        len(web["evidence"]) == 3,
        "three M3 pages say this independently, and all three citations stay listed",
    )
    for citation in web["evidence"]:
        key = citation.split(".", 1)[1]
        report.check(
            key in data["prose_sources"],
            f"fab_menu evidence names `{citation}`, which `prose_sources` does not carry",
        )
    report.check(
        web["gap_between_fab_and_menu"] == 4,
        "the recommended gap between a FAB and its menu stays recorded",
    )
    report.check(
        "baseline" in web["inherits_from"] and "vertical" in web["inherits_from_note"],
        "the record must keep the mismatch: M3 names the baseline menu, and this project "
        "implemented the vertical row",
    )
    # The thing that misled this record once already.
    report.check(
        "token table alone" in web["consequence"] or "token table" in web["consequence"],
        "the consequence must keep saying that reading the token set alone gives the opposite "
        "answer, which is how this record first got it wrong",
    )

    android = menu["android_shape"]
    report.check(
        android["namespace"] == "md.comp.fab-menu"
        and len(table.live("md.comp.fab-menu")) > 0,
        "the FAB menu namespace must still exist in the tables, or this section describes "
        "nothing",
    )
    behaviour = menu["behaviour_published_for_every_platform"]
    report.check(
        behaviour["item_count"] == {"min": 2, "max": 6},
        "2 to 6 items is published M3 and stays recorded",
    )
    report.check(
        behaviour["initial_focus"].startswith("the close button"),
        "initial focus on the close button is the published behaviour",
    )
    report.check(
        "navigation rail" in behaviour["not_with"],
        "M3 says not to use a FAB menu when the FAB is paired with a rail, which is exactly "
        "where this app's FAB sits",
    )
    conflict = menu["our_shell_conflict"]
    report.check(
        conflict["status"] == "resolved" and "no rail" in conflict["resolution"],
        "the rail conflict is resolved by the fact that a compact window has no rail, and the "
        "record must keep saying which half of M3's rule it turns on",
    )
    report.check(
        "582" in conflict["measured"],
        "the resolution rests on a measurement of a shipped product at a stated width, not on "
        "a reading of the prose",
    )

    axis = menu["presentation_axis"]
    report.check(
        axis["status"] == "adopted" and axis["compact"] != axis["expanded"],
        "the presentation axis is adopted and the two presentations differ, or there is no axis",
    )
    report.check(
        axis["gate"] == "breakpoint and pointer"
        and "not a window size" in axis["not_gated_on_display_mode"],
        "the gate is the breakpoint, not the PWA display mode, and the record must keep saying "
        "why -- the same phone would otherwise behave differently installed",
    )

    held = extended_lab = data["extended_fab"]["no_token_table"]["what_the_lab_already_holds"]
    report.check(
        (ROOT / held["path"] / held["audit"]).exists(),
        f"the lab FAB audit this record cites is not at `{held['path']}/{held['audit']}`",
    )
    report.check(
        held["extended_fab"]["inline_padding"] == 16
        and "baseline figure" in held["which_row_this_is"],
        "the lab's 16dp padding is the baseline figure, not the expressive one, and conflating "
        "them would close an open question with the wrong number",
    )
    report.check(
        "no disabled FAB" in held["deviation_to_drop_on_port"],
        "the lab's disabled extended FAB must stay marked as something the port drops",
    )

    # A deviation that stops naming what it departs from stops being one.
    deviation = menu["deviation_on_compact"]
    report.check(
        deviation["status"] == "NON-STANDARD",
        "serving the stack on compact departs from M3's web advice and is marked, not implied",
    )
    report.check(
        len(deviation["against"]) == 2
        and all(cite.split(".", 1)[1] in data["prose_sources"] for cite in deviation["against"]),
        "both web citations the deviation departs from stay named and resolvable",
    )
    report.check(
        "Google Contacts" in deviation["also_against"],
        "the record must keep admitting that the observed product does not do this either",
    )
    report.check(
        "cost_if_wrong" in deviation and deviation["cost_if_wrong"],
        "a deviation carries its cost, or the next reader cannot weigh it",
    )

    lab = menu["lab_implementation"]
    report.check(
        (ROOT / lab["path"]).is_dir(),
        f"the lab implementation this record cites is not at `{lab['path']}`",
    )
    report.check(
        "md-sys-elevation-shadow-level" in lab["porting_consequence"]
        and "Elevation" in lab["porting_consequence"],
        "the port's one real obstacle -- elevation custom properties this app does not define "
        "-- stays recorded with its replacement",
    )
    lab_css = (ROOT / lab["authority"].split(", ")[0]).read_text(encoding="utf-8", errors="ignore")
    report.check(
        "--md-sys-elevation-shadow-level3" in lab_css,
        "the lab stylesheet no longer paints with the elevation custom properties this record "
        "says it does; the porting note needs rereading",
    )


def check_extended_fab(report: Report, data: dict) -> None:
    extended = data["extended_fab"]
    raw = RAW_EXTENDED.read_text(encoding="utf-8")
    report.check(
        "md.comp." not in raw,
        "SOURCE-M3-EXTENDED-FAB-RAW.md now carries tokens; the record's central claim about "
        "the extended FAB -- that no table exists -- needs rewriting",
    )
    report.check(
        "md.comp." not in raw and extended["no_token_table"]["what_would_unblock_it"],
        "the extended FAB still has no token table of its own, and the record must keep saying "
        "what would be one",
    )
    report.check(
        extended["sizes_m3_expressive"] == {
            "small": 56,
            "medium": 80,
            "large": 96,
            "source": "https://m3.material.io/components/extended-fab/overview",
            "note": extended["sizes_m3_expressive"]["note"],
        },
        "the three extended FAB heights are the only sizes prose states, and they stay exact",
    )
    report.check(
        extended["type_scale"]["medium"] == extended["measured_from_compose"]["medium"]["type_scale"]
        and extended["type_scale"]["large"] == extended["measured_from_compose"]["large"]["type_scale"]
        and "FloatingActionButton.kt" in extended["type_scale"]["medium_and_large_source"],
        "the medium and large type scales are not prose figures, so they must agree with the "
        "generated source they came from and keep naming it",
    )
    report.check(
        extended["label_rules"]["icon_without_label"] == "forbidden"
        and extended["label_rules"]["label_without_icon"] == "allowed",
        "the asymmetry is the published rule: an icon needs a label, a label does not need an "
        "icon",
    )
    observed = extended["observed_in_the_wild"]
    # Both rules, by name. Listing only the ones still convenient is how a
    # deviation quietly becomes a smaller deviation.
    report.check(
        observed["deviation"]["against"] == [
            "prose_sources.extended_no_tooltip",
            "prose_sources.extended_accessible_label",
        ],
        "the observed deviation departs from two published rules -- the tooltip and the "
        "first-word rule -- and both stay named",
    )
    for citation in observed["deviation"]["against"]:
        key = citation.split(".", 1)[1]
        report.check(
            key in data["prose_sources"],
            f"the observed deviation is measured against `{citation}`, which `prose_sources` "
            "does not carry -- a deviation with no rule behind it is an opinion",
        )
    report.check(
        "not as licence" in observed["deviation"]["note"]
        and "deliberate deviation" in observed["deviation"]["note"],
        "a shipped product departing from M3 is evidence, not permission, and the record must "
        "keep saying so",
    )
    hole = extended["no_token_table"]
    report.check(
        hole["status"] == "resolved_by_a_second_source"
        and "20" in hole["the_one_number_as_it_stood"]
        and "to 16" in hole["the_one_number_as_it_stood"],
        "the hole is closed by a second generated source, and the record must keep what the "
        "prose sentence actually meant",
    )

    compose = extended["measured_from_compose"]
    report.check(
        compose["why_this_counts"]["header"] == "GENERATED CODE - DO NOT MODIFY BY HAND"
        and len(compose["why_this_counts"]["files"]) == 4,
        "the Compose figures stand on generated token files, and the record names every file "
        "they came from -- four, including the component file the type styles are in",
    )
    for name in ("small", "medium", "large"):
        size = compose[name]
        report.check(
            size["min_inline_size"] == size["container_height"],
            f"extended_fab.measured_from_compose.{name}: the minimum width is the container "
            "height, so it can never be narrower than the FAB it collapses into",
        )
        report.check(
            size["type_scale"].startswith("md.sys.typescale."),
            f"extended_fab.measured_from_compose.{name}: the type scale must name a system "
            "token, not a size",
        )
    report.check(
        compose["medium"]["icon_label_space"] != compose["medium"]["icon_label_space_token_says"]
        and compose["large"]["icon_label_space"] != compose["large"]["icon_label_space_token_says"],
        "both numbers stay for medium and large: the token says one thing and two independent "
        "sources draw another, and collapsing them hides which was chosen",
    )
    report.check(
        compose["large"]["icon_size"] == data["sizes"]["large"]["icon_size"] == 36
        and compose["large"]["icon_size_compose_token_says"] == 32,
        "the kit and the FAB token agree on a 36dp icon at the large size, and the Compose "
        "token that says 32 stays recorded as the outlier it is",
    )

    kit = compose["confirmed_in_the_design_kit"]
    report.check(
        len(kit["instances_read"]) == 3 and set(kit["instances_read"]) == {"small", "medium", "large"},
        "all three sizes were read from the kit, not one generalised to three",
    )
    report.check(
        "rested on one source" in kit["what_it_corrected"],
        "the kit corrected a claim this record had already made, and the correction stays "
        "written down rather than edited away",
    )
    report.check(
        compose["large"]["block_space"] != compose["large"]["leading_space"]
        and compose["large"]["block_space"] * 2 + compose["large"]["icon_size"]
        == compose["large"]["container_height"],
        "at the large size the block padding centres the icon and the inline padding is the "
        "token's leading space; they are two different numbers and must not be merged",
    )
    for name in ("small", "medium"):
        size = compose[name]
        report.check(
            size["block_space"] * 2 + size["icon_size"] == size["container_height"]
            if "block_space" in size
            else (size["leading_space"] * 2 + size["icon_size"] == size["container_height"]),
            f"extended_fab.measured_from_compose.{name}: the block padding must still centre "
            "the icon in the container",
        )
    report.check(
        data["variants"]["extended_fab"]["web_expressive_status"] == "unavailable",
        "M3 lists Web: Expressive as unavailable for the extended FAB, which is why this slice "
        "does not build it",
    )


def check_consumers_and_route(report: Report, data: dict) -> None:
    consumer = data["placement"]["existing_consumer"]
    rail = NAV_RAIL.read_text(encoding="utf-8")
    report.check(
        consumer["slot"] in rail,
        f"the navigation rail no longer carries the `{consumer['slot']}` slot this record names "
        "as the FAB's first consumer",
    )
    report.check(
        "elevation 0" in rail,
        "the rail's resting elevation note is what makes a lowered FAB a project decision; it "
        "must stay in the component that asks for it",
    )

    route = data["project_policy"]["stylebook_route"]
    places = route["declared_in"]
    report.check(
        len(places) == 5 and len(set(places)) == 3,
        "the route is declared in five places across three files, and both numbers matter: the "
        "menu slice shipped with one missing and the page did not open",
    )
    for place in set(places):
        report.check(
            (ROOT / place).exists(),
            f"stylebook_route names `{place}`, which does not exist",
        )
    # Conditional, because the route is not built yet. Once the slug lands
    # anywhere it has to land everywhere, which is the failure this pins.
    slug = route["path"].rsplit("/", 1)[1]
    texts = {
        "route.php": ROUTE.read_text(encoding="utf-8"),
        "app.js": APP.read_text(encoding="utf-8"),
        "stylebook/index.js": STYLEBOOK_INDEX.read_text(encoding="utf-8"),
    }
    present = {name: f"components/{slug}" in text or f"|{slug})" in text or f"'{slug}'" in text
               for name, text in texts.items()}
    report.check(
        len(set(present.values())) == 1,
        "the FAB stylebook route is declared in "
        f"{[name for name, found in present.items() if found]} but not in "
        f"{[name for name, found in present.items() if not found]}",
    )


def main() -> int:
    report = Report()
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    table = Table(
        [RAW_FAB.read_text(encoding="utf-8"), RAW_FAB_MENU.read_text(encoding="utf-8")]
    )

    meta = data["meta"]
    report.check(
        meta["token_table"] == RAW_FAB.relative_to(ROOT).as_posix(),
        "meta.token_table must name the table this check reads",
    )
    report.check(
        meta["namespace_without_a_table"] == "md.comp.extended-fab"
        and "md.comp.extended-fab" not in table.entries,
        "the record names `md.comp.extended-fab` as unpublished; if it now resolves, the "
        "extended FAB section is no longer guesswork and should say so",
    )

    sweep_tokens(report, data, table)
    check_table_shape(report, data, table)
    check_sizes(report, data)
    check_colour_and_states(report, data, table)
    check_prose(report, data)
    check_fab_menu(report, data, table)
    check_extended_fab(report, data)
    check_consumers_and_route(report, data)

    print(f"fab: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
