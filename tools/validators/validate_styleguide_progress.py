"""Check product Progress slots against the recorded spec and contract."""
import argparse
import json
from pathlib import Path
import re
import sys
import yaml

ROOT = Path(__file__).resolve().parents[2]
PLUGIN = ROOT / 'products/wordpress/plugins/axismundi'


def validate(css, data, raw):
    failures = []
    checks = 0

    def check(ok, label):
        nonlocal checks
        checks += 1
        if not ok:
            failures.append(label)

    for name, value in data['css_slots'].items():
        check(f'--md-comp-progress-indicator-{name}: {value};' in css, f'CSS slot {name}')
        # Raw names distinguish dimensions from component part names.
        raw_names = {
            'linear-height': 'linear.height', 'circular-size': 'circular.size',
            'circular-active-indicator-thickness': 'circular.active-indicator.thickness',
            'active-indicator-color': 'active-indicator.color',
            'track-color': 'track.color', 'stop-indicator-color': 'stop-indicator.color',
        }
        line = next((line for line in raw.splitlines() if line.strip().startswith('md.comp.progress-indicator.' + raw_names[name] + ' ')), '')
        check(bool(line), f'raw token {name}')
        if value.endswith('px'):
            check(f'"value":{int(value[:-2])},' in line, f'raw dimension {name}')
        else:
            role = re.search(r'--md-sys-([^)]+)', value).group(1).replace('-', '.', 1)
            check('md.sys.' + role in line, f'raw role {name}')
    check('@media (prefers-reduced-motion: reduce)' in css and 'animation: none !important' in css, 'reduced motion disables animation')
    check(':dir(rtl):not([data-circular])' in css, 'RTL is linear only')
    check('animation-play-state: paused' in css, 'visibility pause CSS')
    check(data['geometry'] == {'stroke': 4, 'gap': 4, 'stop_diameter': 4, 'circular_radius': 18}, 'fixed geometry profile')
    for name, value in data.get('wave_tokens', {}).items():
        line = next((line for line in raw.splitlines() if line.strip().startswith('md.comp.progress-indicator.' + name + ' ')), '')
        match = re.search(r'\{.*\}', line)
        check(bool(match) and json.loads(match.group())['value'] == value, f'wave raw dimension {name}')
    check(data['policy']['wave'] == 'supported' and 'd: var(--ax-wave-flat-path) !important' in css,
          'supported wave has reduced-motion flat path')
    return checks, failures


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--self-test', action='store_true')
    args = parser.parse_args()
    data = yaml.safe_load((ROOT / 'products/styleguide/_data/progress.yml').read_text(encoding='utf-8'))
    css = (PLUGIN / 'src/apps/frontend/styles/components/progress-indicator.css').read_text(encoding='utf-8')
    raw = (ROOT / data['raw']).read_text(encoding='utf-8')
    checks, failures = validate(css, data, raw)
    if args.self_test:
        for old, new in [('linear-height: 4px', 'linear-height: 8px'), ('animation: none !important', 'animation: unset'), (':dir(rtl):not([data-circular])', ':dir(rtl)'), ('d: var(--ax-wave-flat-path) !important', 'd: none')]:
            checks += 1
            if not validate(css.replace(old, new), data, raw)[1]:
                failures.append('mutation escaped: ' + old)
    print(f'progress: {checks} checks, {len(failures)} failed')
    for failure in failures:
        print('FAIL: ' + failure)
    return 1 if failures else 0


if __name__ == '__main__':
    sys.exit(main())
