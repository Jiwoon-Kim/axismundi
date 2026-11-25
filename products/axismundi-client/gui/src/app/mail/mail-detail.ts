// What mail_read returns (`DetailView` in src-tauri/src/mail.rs) and the labels the reading pane
// puts on it. Angular-free so `node --test` can check it against the Rust-written fixture.

export interface Address {name: string | null; email: string | null}

export interface BodyPart {kind: "text" | "html"; partId: string; value: string; truncated: boolean; encodingProblem: boolean}
export interface Body {parts: BodyPart[]; notFetched: boolean}

export interface Attachment {partId: string | null; name: string | null; type: string | null; size: number | null; cid: string | null}

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

export function chooseBodyParts(body: Body, usableHtml: (value: string) => boolean): BodyPart[] {
  const text = body.parts.filter(part => part.kind === "text");
  const html = body.parts.filter(part => part.kind === "html");
  if (html.length && html.every(part => usableHtml(part.value)) && (!text.length || !html.some(part => part.truncated))) return html;
  return text;
}
