#!/usr/bin/env python3
"""
validate_token_layering.py — token architecture guard.

Checks that the Axismundi design-system stylesheets keep their layering:

  E. Token layering — every `--md-sys-color-*` is defined as `var(--md-ref-*)`,
     never as a literal hex value.
  F. Bridge layering — every `--wp--preset--color--*` and
     `--wp--custom--axismundi--*` is defined as a `var()` pointing at a token
     that actually exists upstream, never as a literal.

Axis E reads the shipped theme and the lab. Axis F reads the lab only: it
audits hand-written WordPress bridge CSS, and the theme has none because
WordPress generates --wp--preset--* from theme.json at runtime.

History: this file is the surviving half of `validate_theme_pilot.py`, which
also carried axes A-D and G. Those read the two pilot themes
(`axismundi-pilot`, `ontology-theme-pilot`), removed in this commit. Axis G
needed a `theme.json` carrying `settings.custom.axismundi`; the shipped theme
declares no `settings.custom` at all, so there was nothing to repoint it at.

Unlike its predecessor, this exits non-zero when a check fails. The old script
always returned None, so its CI job passed no matter what the audit found.
"""

import re
import sys
from pathlib import Path

UTF8 = "utf-8"
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding=UTF8)
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding=UTF8)

LAB = Path("products/reference-implementations/axismundi-lab/stylesheets")
THEME = Path("products/wordpress/themes/axismundi/assets/styles")

# Axis E runs against both, and the shipped theme is the one that matters.
# Until now this script read the lab only, which meant the invariant everyone
# treats as the hard gate was guarding the workbench and not the product. The
# theme happened to comply -- 96 declarations, no literals -- but nothing was
# checking, so it held by habit rather than by rule.
#
# The two carry the same tokens under different file names: the lab splits its
# scheme files by mode, the theme by mode within the colour layer.
E_SOURCES = (
    ("lab", LAB, ("tokens.sys.light.css", "tokens.sys.dark.css")),
    ("theme", THEME, ("tokens.sys.color.light.css", "tokens.sys.color.dark.css")),
)

# Axis F stays lab-only, and that is not an omission. It audits the CSS bridge
# files the lab hand-writes; the theme has no equivalent, because WordPress
# generates --wp--preset--* from theme.json settings at runtime and there is no
# stylesheet to read.


def axis_e_token_layering(styles_dir, sys_filenames):
    """E. Token layering axis — md-sys color tokens must consume md-ref."""
    findings = {}
    sys_files = [styles_dir / name for name in sys_filenames]

    sys_color_def_pattern = re.compile(r"^\s*(--md-sys-color-[a-z0-9-]+)\s*:\s*([^;]+);", re.MULTILINE)
    direct_hex_pattern = re.compile(r"^\s*(--md-sys-color-[a-z0-9-]+)\s*:\s*(#[0-9A-Fa-f]{3,8})\b", re.MULTILINE)
    md_ref_pattern = re.compile(r"^\s*--md-sys-color-[a-z0-9-]+\s*:\s*var\(--md-ref-", re.MULTILINE)

    scores = []
    for path in sys_files:
        if not path.exists():
            findings[str(path)] = {
                "exists": False,
                "score": 0.0,
            }
            scores.append(0.0)
            continue

        text = path.read_text(encoding=UTF8)
        definitions = [
            {"token": match.group(1), "value": match.group(2).strip()}
            for match in sys_color_def_pattern.finditer(text)
        ]
        direct_hex = [
            {
                "line": text[:match.start()].count("\n") + 1,
                "token": match.group(1),
                "value": match.group(2),
            }
            for match in direct_hex_pattern.finditer(text)
        ]
        md_ref_refs = len(md_ref_pattern.findall(text))

        score = 1.0 if definitions and not direct_hex and md_ref_refs == len(definitions) else 0.0
        scores.append(score)
        findings[str(path)] = {
            "exists": True,
            "sys_color_definitions": len(definitions),
            "md_ref_references": md_ref_refs,
            "direct_hex_count": len(direct_hex),
            "direct_hex": direct_hex,
            "score": score,
        }

    overall = sum(scores) / len(scores) if scores else 0.0
    return findings, overall


def axis_f_bridge_layering(styles_dir):
    """F. Bridge layering axis — WP bridge tokens stay downstream of M3/comp."""
    findings = {}
    token_files = [
        styles_dir / "tokens.ref.css",
        styles_dir / "tokens.sys.light.css",
        styles_dir / "tokens.sys.core.css",
        styles_dir / "tokens.sys.dark.css",
        styles_dir / "tokens.comp.css",
    ]
    bridge_files = [
        styles_dir / "wp-preset.bridge.css",
        styles_dir / "wp-custom.bridge.css",
    ]

    token_def_pattern = re.compile(r"^\s*(--(?:md-ref|md-sys|comp)-[a-z0-9-]+)\s*:", re.MULTILINE)
    bridge_def_pattern = re.compile(r"^\s*(--wp--(?:preset--color|custom--axismundi)--[a-z0-9-]+(?:--[a-z0-9-]+)*)\s*:\s*([^;]+);", re.MULTILINE)
    var_ref_pattern = re.compile(r"var\((--(?:md-ref|md-sys|comp)-[a-z0-9-]+)\)")
    literal_pattern = re.compile(r"^\s*--wp--(?:preset--color|custom--axismundi)--[a-z0-9-]+(?:--[a-z0-9-]+)*\s*:\s*(?!\s*var\()[^;]+;", re.MULTILINE)

    upstream_tokens = set()
    for path in token_files:
        if not path.exists():
            findings[str(path)] = {"exists": False, "score": 0.0}
            continue
        upstream_tokens.update(token_def_pattern.findall(path.read_text(encoding=UTF8)))

    scores = []
    for path in bridge_files:
        if not path.exists():
            findings[str(path)] = {
                "exists": False,
                "score": 0.0,
            }
            scores.append(0.0)
            continue

        text = path.read_text(encoding=UTF8)
        definitions = [
            {"token": match.group(1), "value": match.group(2).strip()}
            for match in bridge_def_pattern.finditer(text)
        ]
        literal_values = [
            {
                "line": text[:match.start()].count("\n") + 1,
                "declaration": match.group(0).strip(),
            }
            for match in literal_pattern.finditer(text)
        ]
        var_refs = [match.group(1) for match in var_ref_pattern.finditer(text)]
        broken_refs = sorted({ref for ref in var_refs if ref not in upstream_tokens})

        score = 1.0 if definitions and not literal_values and not broken_refs and len(var_refs) == len(definitions) else 0.0
        scores.append(score)
        findings[str(path)] = {
            "exists": True,
            "bridge_definitions": len(definitions),
            "var_references": len(var_refs),
            "literal_value_count": len(literal_values),
            "literal_values": literal_values,
            "broken_references": broken_refs,
            "score": score,
        }

    overall = sum(scores) / len(scores) if scores else 0.0
    return findings, overall




def report(name, findings, score):
    mark = "PASS" if score == 1.0 else "FAIL"
    print(f"  {name}: {score:.3f}  {mark}")
    if score == 1.0:
        return
    for path, data in findings.items():
        if data.get("score") == 1.0:
            continue
        if not data.get("exists", True):
            print(f"    {path}: missing")
            continue
        for key in ("direct_hex", "literal_values", "broken_references"):
            for item in data.get(key) or []:
                print(f"    {path}: {key}: {item}")


def main():
    for _, styles_dir, _ in E_SOURCES:
        if not styles_dir.is_dir():
            print(f"stylesheets directory not found: {styles_dir}")
            return 1

    print("=== Token layering audit ===")
    scored = []
    for label, styles_dir, sys_filenames in E_SOURCES:
        print(f"  E source: {styles_dir}")
        findings, score = axis_e_token_layering(styles_dir, sys_filenames)
        scored.append((f"E {label}", findings, score))
    print(f"  F source: {LAB}\n")

    findings_f, score_f = axis_f_bridge_layering(LAB)

    for name, findings, score in scored:
        report(f"{name} token layering".ljust(17), findings, score)
    report("F bridge layering", findings_f, score_f)

    failed = [n for n, _, s in scored if s != 1.0]
    failed += ["F"] if score_f != 1.0 else []
    print()
    if failed:
        print(f"FAIL — axes {', '.join(failed)} did not pass.")
        return 1
    print("PASS — token layering holds.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
