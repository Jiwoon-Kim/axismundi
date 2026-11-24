import type { MailboxNode } from "./mail-tree.ts";

export const MAILBOX_ROLES = [
  "inbox", "archive", "drafts", "sent", "junk", "trash", "important", "memos", "scheduled", "snoozed", "all", "flagged",
] as const;

export type MailboxChange =
  | {kind: "create"; name: string; parent: string | null; role: string | null}
  | {kind: "rename"; id: string; name: string}
  | {kind: "destroy"; id: string};

export type MailboxResult =
  | {status: "confirmed"; id: string}
  | {status: "refused"; message: string; properties: string[]}
  | {status: "unknown" | "unchanged"};

export interface ParentOption {
  id: string;
  path: string;
  depth: number;
  allowed: boolean;
}

export function allowsMailboxAction(right: boolean | null | undefined): boolean {
  return right !== false;
}

export function mailboxParents(roots: readonly MailboxNode[]): ParentOption[] {
  const options: ParentOption[] = [];
  const walk = (nodes: readonly MailboxNode[], prefix: string, depth: number) => {
    for (const {box, children} of nodes) {
      const path = prefix ? `${prefix} / ${box.name}` : box.name;
      options.push({id: box.id, path, depth, allowed: allowsMailboxAction(box.mayCreateChild)});
      walk(children, path, depth + 1);
    }
  };
  walk(roots, "", 1);
  return options;
}

export function mailboxNameError(name: string, max: number | null): string | null {
  if (!name.trim()) return "Enter a mailbox name.";
  return max !== null && new TextEncoder().encode(name.trim()).length > max
    ? `The name must be at most ${max} UTF-8 bytes.` : null;
}

export function parentAllowed(parent: ParentOption | undefined, topLevel: boolean, maxDepth: number | null): boolean {
  return (parent ? parent.allowed : topLevel) && (maxDepth === null || (parent?.depth ?? 0) + 1 <= maxDepth);
}
