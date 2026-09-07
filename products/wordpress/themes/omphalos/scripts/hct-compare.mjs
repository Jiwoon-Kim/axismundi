import { Hct } from '../node_modules/@material/material-color-utilities/hct/hct.js';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
const here = dirname(fileURLToPath(import.meta.url));
const d = JSON.parse(readFileSync(join(here,'..','data/m3-palettes.json'),'utf8'));
const argb = h => 0xff000000 | parseInt(h.replace('#',''),16);
const H = h => { const c = Hct.fromInt(argb(h)); return c; };
const a = d.baseline.error, b = d.static.red;
console.log('baseline Error  vs  static Red, tone by tone (HCT)\n');
console.log('  tone   Error hex   hue  chroma   |   Red hex    hue  chroma   |  dHue  dChroma');
for (const t of ['100','98','95','90','80','70','60','50','40','30','20','10','0']) {
  if (!a[t] || !b[t]) { console.log('  ' + t.padStart(4) + '   ' + (a[t]||'  --   ') + '                 |   ' + (b[t]||'  --   ') + (a[t]&&!b[t]?'                 |  (stop only in Error)':'')); continue; }
  const x = H(a[t]), y = H(b[t]);
  console.log('  ' + t.padStart(4) + '   ' + a[t] + '  ' + x.hue.toFixed(1).padStart(5) + '  ' + x.chroma.toFixed(1).padStart(5) +
    '   |   ' + b[t] + '  ' + y.hue.toFixed(1).padStart(5) + '  ' + y.chroma.toFixed(1).padStart(5) +
    '   | ' + (y.hue-x.hue).toFixed(1).padStart(5) + '  ' + (y.chroma-x.chroma).toFixed(1).padStart(6));
}
