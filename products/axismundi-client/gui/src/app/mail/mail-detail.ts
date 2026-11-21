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
      body: Body;
      attachments: Attachment[];
    }
  | {kind: "unavailable"; id: string; reason: string};

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
