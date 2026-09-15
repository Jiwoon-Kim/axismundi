#!/usr/bin/env python3
"""Build a browser harness for the Unicode adapter against Core's real emoji scripts.

No WordPress: the page loads wordpress-develop's emoji-loader.js (inline module, as
_print_emoji_detection_script() prints it), wp/emoji.js and vendor/twemoji.js unmodified,
plus this plugin's adapter.js in the head with a config that sets diagnostics.assumeFont,
so wrapping can be measured before a font file exists.

Serve the output over HTTP (file:// blocks the module and the Worker), open index.html in
a browser that lacks native flag emoji (Windows Chromium), and read window.harnessResult.

The config is read from --config, a JSON file holding axismundi_emoji_unicode_config()
output, so profiles are never restated here:

  npx wp-env run cli wp eval "echo wp_json_encode( axismundi_emoji_unicode_config() );" > config.json

Built with Python because emoji-loader.js carries backslash-u escapes that file-editing
tools decode.
"""

from __future__ import annotations

import argparse
import json
import shutil
from pathlib import Path

HERE = Path(__file__).resolve().parent
ADAPTER = HERE.parent.parent / "assets" / "unicode" / "adapter.js"

CHECKS = r"""
<script type="module">
const kr = String.fromCodePoint(0x1F1F0, 0x1F1F7);
const eng = String.fromCodePoint(0x1F3F4, 0xE0067, 0xE0062, 0xE0065, 0xE006E, 0xE0067, 0xE007F);
const black = String.fromCodePoint(0x1F3F4);
const wait = ms => new Promise(r => setTimeout(r, ms));
for (let i = 0; i < 100 && !(window.wp && window.wp.emoji && window.twemoji); i++) await wait(100);
await wait(800);
const q = (sel, root = document) => root.querySelectorAll(sel).length;
const at = id => document.getElementById(id);
const dyn = at('dyn');
const put = (id, build) => { const d = document.createElement('div'); d.id = id; build(d); dyn.appendChild(d); return d; };

// Dynamic cases, Q2: our observer must see each batch before Core's.
put('d-text', d => { d.textContent = 'dynamic ' + kr; });
put('d-nested', d => { d.innerHTML = '<p>deep <b>' + kr + '</b></p>'; });
const later = put('d-textnode', () => {});
later.appendChild(document.createTextNode(kr + ' appended text node'));
put('d-core-excluded', d => { const s = document.createElement('span'); s.className = 'wp-exclude-emoji'; s.textContent = kr; d.appendChild(s); });
await wait(1500);

window.harnessResult = {
  supports: window._wpemojiSettings && window._wpemojiSettings.supports,
  decisions: window.axismundiEmojiUnicode && window.axismundiEmojiUnicode.decisions(),
  static_country_wrapped: q('.ax-unicode-emoji[data-ax-emoji-profile="flags-country"]', at('s-country')),
  static_country_img: q('img.emoji', at('s-country')),
  static_england_wrapped: q('.ax-unicode-emoji[data-ax-emoji-profile="flags-subdivision"]', at('s-england')),
  static_black_flag_wrapped: q('.ax-unicode-emoji', at('s-black')),
  static_mixed_wrapped: q('.ax-unicode-emoji', at('s-mixed')),
  static_mixed_text: at('s-mixed').textContent,
  static_textarea_wrapped: q('.ax-unicode-emoji', at('s-skip')),
  static_author_excluded_wrapped: q('.ax-unicode-emoji', at('s-author-excluded')),
  dynamic_text: { wrapped: q('.ax-unicode-emoji', at('d-text')), img: q('img.emoji', at('d-text')) },
  dynamic_nested: { wrapped: q('.ax-unicode-emoji', at('d-nested')), img: q('img.emoji', at('d-nested')) },
  dynamic_textnode: { wrapped: q('.ax-unicode-emoji', at('d-textnode')), img: q('img.emoji', at('d-textnode')) },
  dynamic_core_excluded_span: { wrapped: q('.ax-unicode-emoji', at('d-core-excluded')), img: q('img.emoji', at('d-core-excluded')) },
  any_wrapper_contains_img: q('.ax-unicode-emoji img'),
};
document.title = 'done';
</script>
"""


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--wp-develop", type=Path, required=True, help="wordpress-develop checkout")
    parser.add_argument("--config", type=Path, required=True, help="axismundi_emoji_unicode_config() JSON")
    parser.add_argument("--out", type=Path, required=True, help="output directory (outside the plugin)")
    parser.add_argument("--font-mode", choices=["assume", "none", "real", "core"], default="assume",
                        help="assume: pretend every profile has a working font; none: exercise the unwrap-to-Core path; "
                             "real: serve the shipped WOFF2 through @font-face; core: no adapter at all (Core only)")
    args = parser.parse_args()

    enqueues = args.wp_develop / "src" / "js" / "_enqueues"
    out = args.out
    out.mkdir(parents=True, exist_ok=True)
    shutil.copy(enqueues / "vendor" / "twemoji.js", out / "twemoji.js")
    shutil.copy(enqueues / "wp" / "emoji.js", out / "wp-emoji.js")
    shutil.copy(ADAPTER, out / "adapter.js")
    loader = (enqueues / "lib" / "emoji-loader.js").read_text(encoding="utf-8")

    config = json.loads(args.config.read_text(encoding="utf-8-sig"))
    for profile in config["profiles"].values():
        profile["font"] = True
    if args.font_mode == "assume":
        config["diagnostics"] = {"assumeFont": True}

    head = f'<script id="axismundi-emoji-unicode-config" type="application/json">{json.dumps(config)}</script>\n<script src="adapter.js" defer></script>'
    if args.font_mode == "core":
        head = ""
    if args.font_mode == "real":
        manifest = json.loads((ADAPTER.parent.parent / "fonts" / "noto-color-emoji" / "manifest.json").read_text(encoding="utf-8"))
        faces = {}
        for profile in manifest["profiles"].values():
            faces.setdefault(profile["file"], []).append(profile["unicodeRange"])
        css = ""
        for rel, ranges in faces.items():
            name = Path(rel).name
            alias = manifest["files"][rel]["alias"]
            shutil.copy(HERE.parent.parent / rel, out / name)
            css += (f'@font-face{{font-family:"{alias}";font-style:normal;font-weight:400;font-display:swap;'
                    f'src:url("{name}") format("woff2");unicode-range:{", ".join(ranges)};}}\n')
        css += ".ax-unicode-emoji{font-style:normal;font-weight:400;}\n"
        for pid, profile in manifest["profiles"].items():
            alias = manifest["files"][profile["file"]]["alias"]
            config["profiles"][pid]["fontFamily"] = alias
            css += f'.ax-unicode-emoji[data-ax-emoji-profile="{pid}"]{{font-family:"{alias}";}}\n'
        head = f'<script id="axismundi-emoji-unicode-config" type="application/json">{json.dumps(config)}</script>\n<script src="adapter.js" defer></script>'
        head = f"<style>{css}</style>\n" + head

    core_settings = {
        "baseUrl": "https://s.w.org/images/core/emoji/17.0.2/72x72/", "ext": ".png",
        "svgUrl": "https://s.w.org/images/core/emoji/17.0.2/svg/", "svgExt": ".svg",
        "source": {"wpemoji": "wp-emoji.js", "twemoji": "twemoji.js"},
    }
    kr = chr(0x1F1F0) + chr(0x1F1F7)
    eng = "".join(map(chr, [0x1F3F4, 0xE0067, 0xE0062, 0xE0065, 0xE006E, 0xE0067, 0xE007F]))
    black = chr(0x1F3F4)

    html = f"""<!doctype html><meta charset="utf-8"><title>unicode harness</title>
<script>try{{sessionStorage.clear()}}catch(e){{}}</script>
<style>body{{font:32px/1.4 system-ui,sans-serif;margin:16px}}p{{margin:4px 0}}img.emoji{{height:1em;width:1em;vertical-align:-0.1em}}</style>
{head}
<p id="s-country">{kr}</p>
<p id="s-england">{eng}</p>
<p id="s-black">{black}</p>
<p id="s-mixed">Seoul {kr} and London {eng}.</p>
<p id="s-skip"><textarea>{kr}</textarea></p>
<p id="s-author-excluded"><span class="wp-exclude-emoji">{kr}</span></p>
<div id="dyn"></div>
<script id="wp-emoji-settings" type="application/json">{json.dumps(core_settings)}</script>
<script type="module">
{loader}
</script>
{CHECKS}
"""
    (out / "index.html").write_text(html, encoding="utf-8", newline="\n")
    print(f"Wrote {out / 'index.html'} (font mode: {args.font_mode})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
