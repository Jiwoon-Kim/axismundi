// The mailbox tree the side panel shows, built from `Mailbox.parentId` (RFC 8621 §2). Angular-free
// so `node --test` can check it against the listing fixture.
import type { MailboxRow } from "./mail-rows.ts";

export interface MailboxNode {
  box: MailboxRow;
  children: MailboxNode[];
}

/** Roles first, in this order (docs/DESIGN-MAIL-GUI.md "부품 대응"); other roles after; none last. */
const ROLE_ORDER = ["inbox", "drafts", "sent", "junk", "trash", "archive"];

function rank(box: MailboxRow): number {
  if (box.role === null) return ROLE_ORDER.length + 1;
  const index = ROLE_ORDER.indexOf(box.role);
  return index === -1 ? ROLE_ORDER.length : index;
}

/** Role order, then the server's sortOrder, then name: siblings at every level. */
function compare(a: MailboxRow, b: MailboxRow): number {
  return rank(a) - rank(b)
    || (a.sortOrder ?? 0) - (b.sortOrder ?? 0)
    || a.name.localeCompare(b.name);
}

/**
 * Nests mailboxes under their parents. A mailbox whose own parent this login cannot see (a shared
 * Account that shares only part of a hierarchy) is not hidden: it becomes a root, and what is visible
 * below it keeps its nesting — hidden A → visible B → visible C shows B at the top with C under it.
 * A mailbox whose ancestry loops back to itself (a parentId cycle, which a server should never send)
 * also becomes a root, so nothing loops and nothing is dropped.
 */
export function mailboxTree(mailboxes: readonly MailboxRow[]): MailboxNode[] {
  const byId = new Map(mailboxes.map(box => [box.id, box]));
  const parentOf = (box: MailboxRow): string | null => {
    if (box.parentId === null || !byId.has(box.parentId)) return null;
    // Walk up from the parent: coming back to this box is a cycle; running out of visible mailboxes
    // (a hidden ancestor further up) is not, and leaves this box under its parent.
    const seen = new Set<string>();
    let up: string | null = box.parentId;
    while (up !== null && byId.has(up) && !seen.has(up)) {
      if (up === box.id) return null;
      seen.add(up);
      up = byId.get(up)!.parentId;
    }
    return box.parentId;
  };
  const nodes = new Map(mailboxes.map(box => [box.id, {box, children: [] as MailboxNode[]}]));
  const roots: MailboxNode[] = [];
  for (const box of mailboxes) {
    const parent = parentOf(box);
    (parent === null ? roots : nodes.get(parent)!.children).push(nodes.get(box.id)!);
  }
  const sort = (list: MailboxNode[]) => {
    list.sort((a, b) => compare(a.box, b.box));
    for (const node of list) sort(node.children);
  };
  sort(roots);
  return roots;
}

/** Ids of the mailboxes above `id`, nearest first; empty for a root or an unknown id. */
export function ancestorsOf(mailboxes: readonly MailboxRow[], id: string | null): string[] {
  const byId = new Map(mailboxes.map(box => [box.id, box]));
  const out: string[] = [];
  let parent = id === null ? null : byId.get(id)?.parentId ?? null;
  while (parent !== null && byId.has(parent) && !out.includes(parent)) {
    out.push(parent);
    parent = byId.get(parent)!.parentId;
  }
  return out;
}
