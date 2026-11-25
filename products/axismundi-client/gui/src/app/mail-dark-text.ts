type Color = [number, number, number, number];

function parseColor(value: string): Color | null {
  const match = /^rgba?\((\d+), (\d+), (\d+)(?:, ([\d.]+))?\)$/.exec(value);
  return match ? [+match[1], +match[2], +match[3], match[4] === undefined ? 1 : +match[4]] : null;
}

function luminance([r, g, b]: Color): number {
  return [r, g, b].reduce((sum, channel, index) => {
    const s = channel / 255;
    return sum + [0.2126, 0.7152, 0.0722][index] * (s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4);
  }, 0);
}

function contrast(text: Color, background: Color): number {
  const [r, g, b, alpha] = text;
  const a = luminance([
    r * alpha + background[0] * (1 - alpha),
    g * alpha + background[1] * (1 - alpha),
    b * alpha + background[2] * (1 - alpha), 1,
  ]);
  const l = luminance(background);
  return (Math.max(a, l) + 0.05) / (Math.min(a, l) + 0.05);
}

/** Measure the reader's palette with the same color-scheme as the message. */
function palette(doc: Document, view: Window) {
  const probe = doc.createElement("span");
  probe.style.cssText = "display:none;color:var(--mat-sys-primary);background-color:var(--mat-sys-surface-container)";
  doc.head.append(probe);
  const style = view.getComputedStyle(probe);
  const result = {panel: parseColor(style.backgroundColor), primary: parseColor(style.color)};
  probe.remove();
  return result;
}

export function applyReadingColors(doc: Document, dark: boolean): void {
  const view = doc.defaultView;
  if (!view) return;
  const root = view.getComputedStyle(doc.documentElement);
  const foreground = parseColor(root.color);
  const canvas = parseColor(root.backgroundColor);
  if (!foreground || !canvas || canvas[3] !== 1 || root.backgroundImage !== "none") return;
  const {panel, primary} = palette(doc, view);

  const backdrops = new Map<Element, Color | null>();
  const marked: [Element, string, string][] = [];
  for (const node of [doc.body, ...doc.body.querySelectorAll("*")]) {
    const style = view.getComputedStyle(node);
    const own = parseColor(style.backgroundColor);
    const inherited = node === doc.body ? canvas : (backdrops.get(node.parentElement!) ?? null);
    let background = style.backgroundImage !== "none" || !own ? null :
      own[3] === 1 ? own : own[3] === 0 ? inherited : null;
    const isButton = node.matches("a[href]") && !!own && own[3] === 1 &&
      (style.display.includes("flex") || style.display === "inline-block") && parseFloat(style.paddingInlineStart) > 0;
    if (isButton) marked.push([node, "data-reader-button-link", ""]);
    if (dark && panel && panel[3] === 1 && background && own?.[3] === 1 && luminance(own) > 0.65 &&
        Math.max(own[0], own[1], own[2]) - Math.min(own[0], own[1], own[2]) < 42) {
      background = panel;
      marked.push([node, "data-reader-dark-panel", ""]);
    }
    backdrops.set(node, background);
    if (!dark || !background || contrast(foreground, background) < 4.5 ||
        !Array.from(node.childNodes).some(child => child.nodeType === 3 && child.textContent?.trim())) continue;
    const current = parseColor(style.color);
    if (current && contrast(current, background) < 4.5) {
      const link = !isButton && !!node.closest("a[href]") && primary && contrast(primary, background) >= 4.5;
      marked.push([node, "data-reader-dark-text", link ? "link" : "body"]);
    }
  }
  for (const [node, name, value] of marked) node.setAttribute(name, value);
}
