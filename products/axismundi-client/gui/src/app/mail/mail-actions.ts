import { Service, inject, signal } from "@angular/core";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";

export type Keyword = "seen" | "flagged";

/**
 * Writes to mail that the list and the reading pane share. A change shows only after the server
 * confirms it: while it is in flight only that message's control is locked, on success `changed`
 * moves so both panes sync again (deltas only, mailbox counts included), and on failure nothing on
 * screen changes and the reason appears once. A confirmed change whose read-back failed (`null`)
 * is still a success: the sync that `changed` triggers is the retry, and the write is not resent.
 */
@Service()
export class MailActions {
  private readonly connection = inject(ConnectionService);
  /** Bumps after each confirmed change; views reload when it moves. */
  readonly changed = signal(0);
  private readonly inFlight = signal<ReadonlySet<string>>(new Set());

  busy(emailId: string, keyword: Keyword): boolean {
    return this.inFlight().has(`${emailId} ${keyword}`);
  }

  async setKeyword(accountId: string, emailId: string, keyword: Keyword, on: boolean): Promise<void> {
    const connectionId = this.connection.connected()?.id;
    const key = `${emailId} ${keyword}`;
    if (connectionId === undefined || this.inFlight().has(key)) return;
    this.inFlight.update(set => new Set(set).add(key));
    try {
      const row = await invoke<unknown>("mail_set_keyword", {connectionId, accountId, emailId, keyword, on});
      if (row === null) {
        this.connection.notice.set("The server saved the change, but the message could not be read back. Refreshing.");
      }
      this.changed.update(count => count + 1);
    } catch (error) {
      const what = keyword === "seen" ? (on ? "mark as read" : "mark as unread") : (on ? "star" : "remove the star");
      this.connection.notice.set(`Could not ${what}: ${error}`);
    } finally {
      this.inFlight.update(set => {
        const next = new Set(set);
        next.delete(key);
        return next;
      });
    }
  }
}
