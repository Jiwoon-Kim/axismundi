"""The React surface's half of the Surface contract.

Imported by validate_styleguide_surface.py. It lives beside that file rather
than inside it because the two bind the same yml to two different runtimes: the
block's stylesheet and view.js on one side, the frontend app's components on the
other. `surface.yml` is the one numeric original for both.

Every expectation here is read out of the yml. Nothing is spelled twice.
"""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
APP = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
SURFACE_CSS = APP / "styles/components/surface.css"
DIALOG_CSS = APP / "styles/components/dialog.css"
SCAFFOLD_CSS = APP / "foundations/layout/scaffold/scaffold.css"
STYLE_INDEX = APP / "styles/index.css"
PAGE = APP / "pages/stylebook/components/surfaces/index.js"
# The other runtime that carries the same rule, read but never written here.
BLOCK_CSS = ROOT / "products/wordpress/plugins/axismundi-dialogs/blocks/dialog/style.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8") if path.is_file() else ""


def _normalise(css: str) -> str:
    """Whitespace and the optional last semicolon are not part of the rule."""
    return re.sub(r"\s+", "", css).replace(";}", "}")


def _block(css: str, opener: str) -> str:
    """The body of one media query, matched by brace depth."""
    start = css.find(opener)
    if -1 == start:
        return ""
    depth = 0
    for index in range(css.index("{", start), len(css)):
        if "{" == css[index]:
            depth += 1
        elif "}" == css[index]:
            depth -= 1
            if 0 == depth:
                return css[start : index + 1]
    return ""


def _medium_block(css: str) -> str:
    return _block(css, "@media ( min-width: 600px )")


def _compact_block(css: str) -> str:
    return _block(css, "@media ( max-width: 599px )")


def check_react_surface(data: dict, report) -> None:
    react = data.get("react_surface")
    report.check(react is not None, "the React surface record disappeared from surface.yml")
    if react is None:
        return

    root = ROOT / react["root"]
    core_path = root / react["core"]["file"]
    core = read(core_path)
    report.check(bool(core), f"react: the shared core {react['core']['file']} is missing")
    dialog_js = read(root / "dialog.js")
    dialog_css = read(DIALOG_CSS)
    page = read(PAGE)

    # --- rows and components ------------------------------------------------
    # Four published rows map onto three components. The union has to stay
    # equal to the presentation axis, so a row cannot be quietly dropped by
    # deleting the component that claimed it.
    axis = next((a for a in data.get("axes", []) if a.get("name") == "presentation"), {})
    published = list(axis.get("values") or [])
    claimed: list[str] = []
    for component in react["components"]:
        claimed.extend(component.get("presentations") or [])
    report.check(
        sorted(claimed) == sorted(published),
        f"react: components claim {sorted(claimed)}, the presentation axis publishes {sorted(published)}",
    )
    report.check(len(claimed) == len(set(claimed)), "react: two components claim the same presentation")

    implemented: dict[str, str] = {}
    for component in react["components"]:
        name = component["name"]
        path = root / component["file"]
        shipped = "not implemented" != component.get("status")
        report.check(
            path.is_file() == shipped,
            f"react: {component['file']} is {'missing' if shipped else 'present'} "
            f"while the record says {component.get('status', 'implemented')}",
        )
        if shipped:
            source = read(path)
            report.check(f"export function {name}" in source, f"react: {component['file']} stops exporting {name}")
            for presentation in component.get("presentations") or []:
                implemented[presentation] = component["file"]
            # A component that pairs two rows has to say why in the record,
            # because the pairing is the one thing a reader cannot recover from
            # the source.
            if 1 < len(component.get("presentations") or []):
                report.check(
                    bool(component.get("why_paired")),
                    f"react: {name} pairs rows without recording why",
                )

    # --- the escape contract ------------------------------------------------
    report.check(
        "createPortal" not in core,
        "react: the core reaches for createPortal, which the escape record rules out",
    )
    report.check(react["host"]["modal"].rstrip("()") + "()" in core, "react: the core stopped calling showModal()")
    report.check(".show()" in core or "dialog.show()" in core, "react: the core stopped calling show()")

    # The scroll owner's prohibition, re-checked from this side. Its reason
    # changed when the escape question was measured; the pin did not.
    scaffold = read(SCAFFOLD_CSS)
    stripped = re.sub(r"/\*.*?\*/", "", scaffold, flags=re.S)
    offenders = [line.strip() for line in stripped.splitlines() if re.search(r"^\s*overflow[-a-z]*\s*:", line)]
    report.check(
        not offenders,
        f"react: the scaffold declares overflow, which the scroll record forbids: {offenders}",
    )
    report.check(
        "minmax( 0, 1fr )" in scaffold,
        "react: the scaffold's body lost its minmax( 0, 1fr ) track",
    )

    # --- the closed state ---------------------------------------------------
    # Cheap pin, expensive lesson: an author `display` on the host outranks the
    # UA's `dialog:not([open]) { display: none }`, so without this every closed
    # surface on the page paints at once.
    surface_css = read(SURFACE_CSS)
    closed = re.search(
        r"\.ax-surface:not\(\s*\[open\]\s*\)\s*\{[^}]*display:\s*none", surface_css, re.S
    )
    report.check(bool(closed), "react: closed surfaces no longer re-declare display: none")

    # --- the scrim ----------------------------------------------------------
    opacity = data["scrim"]["opacity"]
    report.check(
        f"--ax-surface-scrim-opacity: {opacity}" in surface_css,
        f"react: the scrim opacity is not the published {opacity}",
    )
    report.check(
        "--md-sys-color-scrim" in surface_css,
        "react: the scrim stopped reading the theme's scrim colour role",
    )
    # The scrim follows the resolved row, so it is keyed on modality in CSS and
    # nowhere in the components. A per-row attribute cannot answer a media
    # query, and the version that tried shipped a medium dialog floating over a
    # transparent backdrop.
    report.check(
        "[data-modality='modal']::backdrop" in surface_css,
        "react: the scrim is no longer keyed on modality",
    )
    for source in (core, dialog_js):
        # Setting it, not mentioning it. The comment that explains why the
        # attribute was wrong is worth keeping, and a pin that fires on its own
        # explanation is a pin nobody can satisfy.
        report.check(
            not re.search(r"data-scrim\s*=|dataset\.scrim", source),
            "react: a component sets a per-row scrim attribute again, which a media query cannot reach",
        )

    # --- the adaptive swap --------------------------------------------------
    # `transfers` and `keeps` are the yml's, and each side is bound to
    # something a reader can check. A swap that takes the box but not the
    # scrim, headline or speed is the defect this pins shut.
    swap = next(
        (a for a in data["adaptive"] if a.get("presentation") == "dialog-full-screen"),
        None,
    )
    report.check(swap is not None, "react: the full-screen adaptive rule disappeared")
    if swap is not None and "dialog-full-screen" in implemented:
        transfers = swap.get("transfers") or []
        keeps = swap.get("keeps") or []
        report.check(bool(transfers) and bool(keeps), "react: the adaptive swap stopped saying what crosses")

        medium = _medium_block(dialog_css)
        report.check(bool(medium), "react: the full-screen row has no medium media query to swap in")
        compact = _compact_block(dialog_css)
        report.check(bool(compact), "react: nothing scopes the full-screen row's scrim removal to compact")

        # Each transferred thing has a witness in the medium block.
        witnesses = {
            "size": "max-inline-size",
            "container colour": "--md-comp-dialog-container-color",
            "elevation": ".ax-elevation",
            "shape": "--md-comp-dialog-container-shape",
            "headline typescale": "--md-comp-dialog-headline-size",
            "motion": "--ax-surface-motion-enter",
            "scrim": None,
        }
        for item in transfers:
            report.check(item in witnesses, f"react: the swap transfers '{item}', which nothing checks")
            witness = witnesses.get(item)
            if witness is None:
                # The scrim crosses by NOT being removed above medium, so its
                # witness is the compact bound rather than the medium block.
                report.check(
                    "::backdrop" in compact and "transparent" in compact,
                    "react: the full-screen scrim is not removed on compact, or not only there",
                )
                continue
            report.check(witness in medium, f"react: the swap no longer transfers {item} ({witness})")

        # `keeps` is bound in both directions. A part the component renders
        # for this row has to be named in the record, or dropping the name
        # silently retires the check that the swap leaves it alone -- which is
        # how the first version of this pin passed while the record lost its
        # close button.
        parts = {
            "header": "ax-dialog__header",
            "close icon button": "ax-dialog__close",
            "header actions": "ax-dialog__header",
            "body": "ax-dialog__body",
        }
        for item, marker in parts.items():
            rendered = marker in dialog_js
            report.check(
                not rendered or item in keeps,
                f"react: the component renders '{item}' for this row and the record no longer keeps it",
            )
            if item in keeps:
                report.check(
                    f".{marker} {{" not in medium,
                    f"react: the medium swap restyles '{item}', which the record says it keeps",
                )

    # --- native close -------------------------------------------------------
    # The contract records form-method-dialog close, and the browser performs
    # it without asking. A close this component did not make has to reach the
    # caller, or `open` stays true and the trigger is dead for good.
    report.check(
        "onClose={ onNativeClose }" in core,
        "react: a native close no longer reaches the caller, so the surface cannot be reopened",
    )
    report.check(
        "selfClosingRef" in core,
        "react: nothing separates the component's own close from someone else's",
    )
    form_close = (data["components"]["dialog"].get("web") or {}).get("close_by_form")
    report.check(
        form_close is not None and form_close.replace('"', "'") in page.replace('"', "'"),
        "react: no specimen closes a surface the way the contract records",
    )

    # --- the scroll affordance stays fresh ----------------------------------
    # Both conditions change without the region's box changing, so a
    # ResizeObserver alone goes stale: content growing inside a max-block-size,
    # and a focusable appearing or leaving.
    # Bound to the wiring, not to the word. The first version of this pin
    # checked that "MutationObserver" appeared in the file, and it passed with
    # the constructor renamed to something that does not exist -- the word was
    # still there in a comment and in the feature guard.
    report.check(
        re.search(r"new window\.MutationObserver\(", core) is not None,
        "react: the scroll affordance no longer constructs a MutationObserver",
    )
    observed = re.search(r"\.observe\(\s*region,\s*\{(.*?)\}\s*\)", core, re.S)
    report.check(observed is not None, "react: nothing observes the scrolling region's contents")
    if observed is not None:
        for option in ("childList: true", "subtree: true", "attributes: true"):
            report.check(
                option in observed.group(1),
                f"react: the scrolling region is observed without {option}, so one of the two conditions goes stale",
            )
        # Focusability is decided by attributes, so the filter has to admit the
        # ones that change it.
        for attribute in ("disabled", "href", "tabindex"):
            report.check(
                f"'{attribute}'" in observed.group(1),
                f"react: a change to {attribute} no longer re-measures the scroll affordance",
            )

    # --- the scroll lock ----------------------------------------------------
    # Keyed on `:modal`, so it follows modality the way the scrim does. Both
    # runtimes carry the same rule and the yml holds both, so each is checked
    # against the file that ships it.
    lock = react.get("scroll_lock")
    report.check(lock is not None, "react: the scroll lock left the record")
    if lock is not None:
        report.check(
            "modality" == lock.get("follows"),
            "react: the scroll lock no longer follows modality",
        )
        report.check(
            _normalise(lock["react"]) in _normalise(surface_css),
            "react: the page behind a modal is no longer locked",
        )
        report.check(
            ":modal" in surface_css,
            "react: the lock is not keyed on :modal, so a standard sheet would lock the page too",
        )
        block_css = read(BLOCK_CSS)
        report.check(
            not block_css or _normalise(lock["block"]) in _normalise(block_css),
            "the block's scroll lock no longer matches the shared record",
        )

    # --- initial focus ------------------------------------------------------
    # This one is implemented rather than inherited, and the yml says why. If
    # the measurement note goes, the next reader will delete the code as
    # redundant with the platform.
    conflict = next((c for c in data["conflicts"] if c.get("subject") == "initial focus"), None)
    report.check(conflict is not None, "react: the initial-focus decision left the conflict record")
    if conflict is not None:
        report.check(
            bool(conflict.get("measured")),
            "react: the initial-focus record lost the measurement that forced an implementation",
        )
    report.check("applyInitialFocus" in core, "react: the core stopped implementing initial focus")
    report.check(
        "data-surface-scroll" in core,
        "react: initial focus no longer skips the surface's scrolling region",
    )
    report.check(
        'data-surface-scroll=""' in dialog_js,
        "react: the dialog body stopped marking itself as the scrolling region",
    )

    # --- motion -------------------------------------------------------------
    # Which token each row takes is this project's choice, recorded per
    # presentation. The stylesheet has to spend the recorded tokens, for the
    # rows that ship.
    for presentation, tokens in (data.get("motion", {}).get("presentations") or {}).items():
        if presentation not in implemented:
            continue
        for key, token in tokens.items():
            if "reason" == key or not isinstance(token, str):
                continue
            report.check(
                f"--{token}" in dialog_css,
                f"react: {presentation} no longer takes --{token} for {key}",
            )

    # The choreography's own numbers, which the component spends rather than
    # invents.
    choreography = data["motion"]["dialog"]
    report.check(
        f"CONTAINER_TRAVEL = {choreography['translate']}" in dialog_js,
        f"react: the container travel is not the recorded {choreography['translate']}px",
    )
    report.check(
        f"FOLDED_HEIGHT = {choreography['folded_height'] / 100}" in dialog_js,
        f"react: the folded height is not the recorded {choreography['folded_height']}%",
    )
    report.check(
        f"EXIT_SLOT_FADE = {choreography['exit_slot_fade'].replace('/', ' / ')}" in dialog_js,
        f"react: the exit slot fade is not the recorded {choreography['exit_slot_fade']}",
    )
    for slot in ("content", "actions"):
        hold = choreography[slot]["hold"]
        share = choreography[slot]["share_of_enter"]
        report.check(
            re.search(rf"hold: {hold}, share: {share}", dialog_js) is not None,
            f"react: the {slot} slot no longer holds {hold} and spends {share} of the enter",
        )

    # --- the stylesheets are actually reachable -----------------------------
    index = read(STYLE_INDEX)
    for sheet in ("surface.css", "dialog.css"):
        report.check(
            f"components/{sheet}" in index,
            f"react: {sheet} is not imported, so none of it ships",
        )
