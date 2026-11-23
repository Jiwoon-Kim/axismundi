// What mail_read returns (`DetailView` in src-tauri/src/mail.rs) and the labels the reading pane
// puts on it. Angular-free so `node --test` can check it against the Rust-written fixture.

export interface Address {name: string | null; email: string | null}

export type Body =
  | {kind: "text"; text: string; truncated: boolean; encodingProblem: boolean}
  | {kind: "htmlOnly"}
  | {kind: "notFetched"}
  | {kind: "empty"};

export interface Attachment {partId: string | null; name: string | null; type: string | null; size: number | null}

export type DetailView =
  | {
      kind: "message";
      id: string;
      threadId: string | null;
      subject: string | null;
      from: Address[];
      to: Address[];
      cc: Address[];
      receivedAt: string | null;
      sentAt: string | null;
      /** null when keywords are unknown, as in the listing. */
      unread: boolean | null;
      flagged: boolean | null;
      /** The mailboxes it is in; null when unknown. */
      mailboxIds: string[] | null;
      body: Body;
      attachments: Attachment[];
    }
  | {kind: "unavailable"; id: string; reason: string};

/**
 * Whether opening a message marks it read: only when the server's keywords say it is unread (`seen`
 * false; null means unknown and is left alone), not while an earlier read-state write is still
 * unknown (the click would be a check, not a write), and not where `$seen` may not be changed — a
 * read-only shared mailbox would refuse a write the user never asked for. The caller asks once per
 * opening, so a reload or the user's own "Mark as unread" afterwards is never overwritten.
 */
export function markReadOnOpen(seen: boolean | null, blocked: boolean, maySetSeen: boolean): boolean {
  return seen === false && !blocked && maySetSeen;
}

/**
 * Whether `$seen` may change on a message, from the rights of the mailboxes it is in: it needs
 * maySetSeen in every one (RFC 8621 §2). Only a stated `false` says no; when the message's mailboxes
 * are unknown, the mailbox it is listed in stands for them.
 */
export function maySetSeen(mailboxes: readonly {id: string; maySetSeen: boolean | null}[],
                           mailboxIds: readonly string[] | null, listedIn: string | null): boolean {
  const ids = mailboxIds ?? (listedIn ? [listedIn] : []);
  return ids.every(id => mailboxes.find(box => box.id === id)?.maySetSeen !== false);
}

/** "Name <email>", or whichever half exists. */
export function addressLabel(address: Address): string {
  if (address.name && address.email) return `${address.name} <${address.email}>`;
  return address.name || address.email || "(unknown)";
}

export function addressList(addresses: Address[]): string {
  return addresses.map(addressLabel).join(", ");
}

/** What the reading pane says instead of a body it cannot or will not show. */
export function bodyNotice(body: Body): string | null {
  switch (body.kind) {
    case "text":
      return body.truncated ? "This message is longer than what was downloaded." : null;
    case "htmlOnly": return "This message has only an HTML body, which is not shown yet.";
    case "notFetched": return "The body has not been downloaded.";
    case "empty": return "This message has no text body.";
  }
}
