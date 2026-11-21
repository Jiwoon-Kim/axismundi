// What mail_thread returns (`ThreadView` in src-tauri/src/mail.rs). Members reuse the listing row;
// a member's body is a separate mail_read.
import type { MailRow } from "./mail-rows";

export type ThreadView =
  | {kind: "thread"; threadId: string; messages: MailRow[]}
  | {kind: "notFound"; threadId: string};
