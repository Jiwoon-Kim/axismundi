// The listing the Mail commands return (`ListView` in src-tauri/src/mail.rs), and the labels the
// screen puts on it. Kept free of Angular so `node --test` can check it against the fixture the
// Rust test writes from a real response.

export interface MailboxRow {
  id: string;
  name: string;
  role: string | null;
  parentId: string | null;
  sortOrder: number | null;
  totalEmails: number | null;
  unreadEmails: number | null;
  /** myRights.mayAddItems: a move may end here. null when the server did not say. */
  mayAddItems: boolean | null;
  /** myRights.mayRemoveItems: a move may start here. */
  mayRemoveItems: boolean | null;
  /** myRights.maySetSeen: $seen may change on a message here (in every mailbox it is in). */
  maySetSeen: boolean | null;
}

export type MailRow =
  | {
      kind: "message";
      id: string;
      threadId: string | null;
      senderName: string | null;
      senderEmail: string | null;
      subject: string | null;
      preview: string | null;
      receivedAt: string | null;
      /** null: keywords were not fetched, so read state is unknown. */
      unread: boolean | null;
      /** null on the same terms as unread. */
      flagged: boolean | null;
      hasAttachment: boolean | null;
      /** The mailboxes it is in (in a shared Account, those this login can see); null when unknown. */
      mailboxIds: string[] | null;
    }
  | {kind: "unavailable"; id: string; reason: string};

export interface ListView {
  accountId: string;
  mailboxId: string;
  mailboxes: MailboxRow[];
  rows: MailRow[];
  total: number | null;
  hasMore: boolean | null;
  queryStale: boolean;
  partialCache: boolean;
}

export type RowLabel =
  | {kind: "message"; id: string; sender: string; subject: string; preview: string; unread: boolean; flagged: boolean | null; receivedAt: string | null}
  | {kind: "unavailable"; id: string; reason: string};

/** Unknown read state is shown as read: only a fetched keyword set without `$seen` is unread. */
export function rowLabel(row: MailRow): RowLabel {
  if (row.kind === "unavailable") return row;
  return {
    kind: "message",
    id: row.id,
    sender: row.senderName || row.senderEmail || "(no sender)",
    subject: row.subject || "(no subject)",
    preview: row.preview ?? "",
    unread: row.unread === true,
    flagged: row.flagged,
    receivedAt: row.receivedAt,
  };
}
