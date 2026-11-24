// Which moves a mailbox listing offers, from the Account's mailboxes and their rights. Kept free of
// Angular so `node --test` can check it against the listing fixture.
import type { MailboxRow } from "./mail-rows.ts";

export interface MoveOptions {
  /** Whether "Move to Trash" belongs on this listing at all: not inside Trash itself. */
  showTrash: boolean;
  /** The Account's Trash, when it has one this login can see. */
  trash: MailboxRow | null;
  /** Why "Move to Trash" cannot be used here, or null when it can. */
  trashBlocked: string | null;
  /** Where "Move to…" may send a message: every other mailbox that takes items, any role or none. */
  targets: MailboxRow[];
  /** Why "Move to…" cannot be used here, or null when it can. */
  moveBlocked: string | null;
}

/**
 * Moves stay inside one Account: JMAP has no cross-Account move, so a shared Account that does not
 * share its Trash has no Trash to move to, and the app never falls back to destroying the message.
 * A right the server did not state (`null`) is not treated as missing; the server still decides.
 */
export function moveOptions(mailboxes: readonly MailboxRow[], from: string | null, searching = false): MoveOptions {
  const source = mailboxes.find(box => box.id === from) ?? null;
  const cannotRemove = source?.mayRemoveItems === false ? "You cannot remove messages from this mailbox." : null;
  const trash = mailboxes.find(box => box.role === "trash") ?? null;
  const targets = mailboxes.filter(box => box.id !== from && box.mayAddItems !== false);
  return {
    showTrash: source?.role !== "trash",
    trash,
    trashBlocked: cannotRemove
      ?? (!trash ? "This account has no Trash mailbox you can use."
        : trash.mayAddItems === false ? "You cannot add messages to Trash in this account." : null),
    targets,
    moveBlocked: searching ? "Open the message's mailbox to move it."
      : cannotRemove ?? (targets.length ? null : "There is no other mailbox you can move messages to."),
  };
}

/**
 * Whether a move to this mailbox also takes the message out of every other mailbox this login sees.
 * Trash and Junk: Stalwart destroys a message outright once it has sat in either past the retention
 * period (DataRetention expungeTrashAfter, 30 days by default), every other membership included
 * (1.1.0 `emails_auto_expunge`). Left in Project while in Trash, it would vanish from Project later,
 * unasked. So it leaves what this login can see; what a shared Account hides is never in the request.
 * Every other destination is a plain move: only the source membership changes.
 */
export function exclusiveMove(to: MailboxRow): boolean {
  return to.role === "trash" || to.role === "junk";
}

/** A Material Symbols name for a mailbox, by role. */
export function mailboxIcon(box: MailboxRow): string {
  switch (box.role) {
    case "inbox": return "inbox";
    case "sent": return "send";
    case "drafts": return "draft";
    case "archive": return "archive";
    case "junk": return "report";
    case "trash": return "delete";
    default: return "folder";
  }
}
