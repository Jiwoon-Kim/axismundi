import type { ListView, MailboxRow } from "./mail-rows.ts";

export interface Segment { text: string; marked: boolean }

function decodeEntities(value: string): string {
  const named: Record<string, string> = {amp: "&", lt: "<", gt: ">", quot: '"', apos: "'", nbsp: "\u00a0"};
  return value.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (match, body: string) => {
    if (!body.startsWith("#")) return Object.hasOwn(named, body) ? named[body] : match;
    const hex = body[1]?.toLowerCase() === "x";
    const code = Number.parseInt(body.slice(hex ? 2 : 1), hex ? 16 : 10);
    return code > 0 && code <= 0x10ffff && !(code >= 0xd800 && code <= 0xdfff)
      ? String.fromCodePoint(code) : "\ufffd";
  });
}

/** Treat each raw fragment as text, not DOM. Malformed markup falls back to the original row. */
export function snippetParts(value: string | null | undefined, fallback: string): Segment[] {
  const plain = [{text: fallback, marked: false}];
  if (!value) return plain;
  const parts: Segment[] = [];
  let marked = false;
  for (const token of value.split(/(<\/?mark>)/)) {
    if (token === "<mark>") {
      if (marked) return plain;
      marked = true;
    } else if (token === "</mark>") {
      if (!marked) return plain;
      marked = false;
    } else if (token.includes("<")) {
      return plain;
    } else if (token) {
      parts.push({text: decodeEntities(token), marked});
    }
  }
  return marked || !parts.length ? plain : parts;
}

export function searchLabels(ids: readonly string[] | null, ordered: readonly MailboxRow[]): {display: string; full: string} {
  const names = ordered.filter(box => ids?.includes(box.id)).map(box => box.name || "(unnamed)");
  return {
    display: names.length > 1 ? `${names[0]} +${names.length - 1}` : names[0] ?? "",
    full: names.join(" · "),
  };
}

export function appendSearchPage(current: ListView, next: ListView): ListView {
  if (!current.search || !next.search) throw new Error("Invalid search page");
  if (next.search.position === 0) return next;
  if (next.search.position !== current.rows.length || current.search.state !== next.search.state) throw new Error("Search changed");
  const ids = new Set(current.rows.map(row => row.id));
  if (next.rows.some(row => ids.has(row.id))) throw new Error("Search returned duplicate messages");
  return {...next, rows: [...current.rows, ...next.rows],
    search: {...next.search, position: 0, snippets: {...current.search.snippets, ...next.search.snippets}}};
}
