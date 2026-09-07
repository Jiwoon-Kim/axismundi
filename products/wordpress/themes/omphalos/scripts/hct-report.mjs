import { Hct } from '../node_modules/@material/material-color-utilities/hct/hct.js';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const data = JSON.parse(readFileSync(join(here,'..','data/m3-palettes.json'),'utf8'));
const argb = h => 0xff000000 | parseInt(h.replace('#',''),16);
const hct = h => { const c = Hct.fromInt(argb(h)); return { h: c.hue, c: c.chroma, t: c.tone }; };

const key = p => p['40'];
const rows = [];
for (const [set, group] of Object.entries({baseline:data.baseline, static:data.static})) {
  for (const [name, tones] of Object.entries(group)) {
    if (!key(tones)) continue;
    const v = hct(key(tones));
    rows.push({ set, name, hex: key(tones), hue: v.h, chroma: v.c, tone: v.t });
  }
}
const primary = rows.find(r => r.set==='baseline' && r.name==='primary');
const delta = h => { let d = (h - primary.hue + 360) % 360; return d; };

rows.sort((a,b) => delta(a.hue) - delta(b.hue));
console.log('Tone 40 of every palette, in HCT. Hue offset is measured from baseline primary.\n');
console.log('  set       palette          hex       hue    chroma  tone   +hue from primary');
for (const r of rows) {
  console.log('  ' + r.set.padEnd(9) + r.name.padEnd(17) + r.hex + '   ' +
    r.hue.toFixed(1).padStart(5) + '  ' + r.chroma.toFixed(1).padStart(6) + '  ' +
    r.tone.toFixed(1).padStart(5) + '   ' + delta(r.hue).toFixed(1).padStart(6) + '°');
}
