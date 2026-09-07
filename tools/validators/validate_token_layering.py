#!/usr/bin/env python3
"""
validate_token_layering.py — token architecture guard.

Checks that the Axismundi design-system stylesheets keep their layering:

  E. Token layering — every `--md-sys-color-*` is defined as `var(--md-ref-*)`,
     never as a literal hex value.
  F. Bridge layering — every `--wp--preset--color--*` and
     `--wp--custom--axismundi--*` is defined as a `var()` pointing at a token
     that actually exists upstream, never as a literal.

These are the two axes CLAUDE.md calls the permanent guards on token
architecture. They read the design-system stylesheets only.

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

# Design-system stylesheets under audit. This path moves when the lab is
# promoted to products/styleguide/; it is the only path this script knows.
STYLES = Path("products/reference-implementations/axismundi-lab/stylesheets")


def axis_e_token_layering(styles_dir):
    """E. Token layering axis — md-sys color tokens must consume md-ref."""
    findings = {}
    sys_files = [
        styles_dir / "tokens.sys.light.css",
        styles_dir / "tokens.sys.dark.css",
    ]

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
    if not STYLES.is_dir():
        print(f"stylesheets directory not found: {STYLES}")
        return 1

    print(f"=== Token layering audit ===\n  source: {STYLES}\n")
    findings_e, score_e = axis_e_token_layering(STYLES)
    findings_f, score_f = axis_f_bridge_layering(STYLES)

    report("E token layering ", findings_e, score_e)
    report("F bridge layering", findings_f, score_f)

    failed = [n for n, s in (("E", score_e), ("F", score_f)) if s != 1.0]
    print()
    if failed:
        print(f"FAIL — axes {', '.join(failed)} did not pass.")
        return 1
    print("PASS — token layering holds.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
