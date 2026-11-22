import { Service, inject, signal } from "@angular/core";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";
import { type Keyword, KeywordWriter } from "./mail-keywords";

export type { Keyword } from "./mail-keywords";

/**
 * Writes to mail that the list and the reading pane share. A change shows only after the server
 * confirms it: while it is in flight only that message's control is locked, on a confirmed change
 * `changed` moves so both panes sync again (deltas only, mailbox counts included), and on failure
 * nothing on screen changes and the reason appears once.
 *
 * An `unknown` outcome blocks further writes of that keyword to that message until a read settles
 * it (see `KeywordWriter`). Pressing the control meanwhile runs the read, not the write; `unverified`
 * lets the screen say so. The selection UI uses `setKeywords` with many ids and keeps
 * `settled.keepSelected` selected.
 */
@Service()
export class MailActions {
  private readonly connection = inject(ConnectionService);
  private readonly writer = new KeywordWriter((command, args) => invoke(command, args));
  /** Bumps after each change the server may have made; views reload when it moves. */
  readonly changed = signal(0);
  private readonly inFlight = signal<ReadonlySet<string>>(new Set());
  /** Moves whenever the writer's blocks may have changed, so `unverified` is re-read. */
  private readonly blocks = signal(0);

  busy(emailId: string, keyword: Keyword): boolean {
    return this.inFlight().has(`${emailId} ${keyword}`);
  }

  /** Stamp for a view to take when it starts loading (see `KeywordWriter.mark`). */
  mark(): number {
    return this.writer.mark();
  }

  /** Whether the message has the keyword, as the screen should show it (see `KeywordWriter.shown`). */
  shown(accountId: string, emailId: string, keyword: Keyword, viewHas: boolean | null, loadedFrom: number): boolean | null {
    this.blocks();
    const connectionId = this.connection.connected()?.id;
    return connectionId === undefined
      ? viewHas
      : this.writer.shown({connectionId, accountId}, emailId, keyword, viewHas, loadedFrom);
  }

  /** Whether the last write of this keyword to this message has an unknown outcome not yet read. */
  unverified(accountId: string, emailId: string, keyword: Keyword): boolean {
    this.blocks();
    const connectionId = this.connection.connected()?.id;
    return connectionId !== undefined && this.writer.isUnverified({connectionId, accountId}, emailId, keyword);
  }

  setKeyword(accountId: string, emailId: string, keyword: Keyword, on: boolean): Promise<void> {
    return this.setKeywords(accountId, [emailId], keyword, on).then(() => undefined);
  }

  async setKeywords(accountId: string, emailIds: readonly string[], keyword: Keyword, on: boolean) {
    const connectionId = this.connection.connected()?.id;
    const ids = emailIds.filter(id => !this.inFlight().has(`${id} ${keyword}`));
    if (connectionId === undefined || !ids.length) return null;
    const keys = ids.map(id => `${id} ${keyword}`);
    this.inFlight.update(set => new Set([...set, ...keys]));
    const action = actionName(keyword, on);
    try {
      const result = await this.writer.write({connectionId, accountId}, ids, keyword, on, action);
      const notices = [result.writeError ? `${action}: the request failed, so whether it applied is unknown. ${result.writeError}` : result.settled?.notice];
      if (result.held.length) {
        notices.push(result.unverified.length
          ? `${action}: not sent to ${result.unverified.length} message(s) whose last change is unknown; the server could not be read to check.`
          : `${action}: the server was read for messages whose last change was unknown; press again to change them.`);
      } else if (result.checkFailed) {
        notices.push("The server could not be read to check. Press again to check before changing.");
      }
      const notice = notices.filter(Boolean).join(" ");
      if (notice) this.connection.notice.set(notice);
      if (result.settled?.refresh || result.held.length || result.writeError) this.changed.update(count => count + 1);
      return result;
    } finally {
      this.blocks.update(count => count + 1);
      this.inFlight.update(set => {
        const next = new Set(set);
        for (const key of keys) next.delete(key);
        return next;
      });
    }
  }
}

/** The change in words, for notices: "Mark as read", "Star", ... */
export function actionName(keyword: Keyword, on: boolean): string {
  return keyword === "seen" ? (on ? "Mark as read" : "Mark as unread") : (on ? "Star" : "Remove star");
}
