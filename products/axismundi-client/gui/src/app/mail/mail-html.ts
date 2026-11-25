/** RFC 2392 URL escapes are decoded, but a Content-ID itself is not case-folded. */
export function contentId(value: string): string {
  return value.trim().replace(/^<([^<>]+)>$/, "$1");
}

export function cidReference(url: string): string | null {
  if (!/^cid:/i.test(url)) return null;
  try { return contentId(decodeURIComponent(url.slice(4))) || null; }
  catch { return null; }
}

export function remoteImage(url: string): string | null {
  try {
    const parsed = new URL(url);
    return parsed.protocol === "https:" && !parsed.username && !parsed.password ? parsed.href : null;
  } catch { return null; }
}

export function externalLink(url: string): string | null {
  try {
    const parsed = new URL(url);
    return ["http:", "https:", "mailto:"].includes(parsed.protocol) && !parsed.username && !parsed.password ? parsed.href : null;
  } catch { return null; }
}
