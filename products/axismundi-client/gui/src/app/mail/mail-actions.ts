import { DestroyRef, Service, effect, inject, signal } from "@angular/core";
import { invoke } from "@tauri-apps/api/core";
import { listen } from "@tauri-apps/api/event";
import { ConnectionService } from "../connection.service";
import { exclusiveMove } from "./mail-moves";
import type { MailboxRow } from "./mail-rows";
import { type Aspect, type Change, type Keyword, MailWriter, type WriteResult } from "./mail-updates";
import { matchesMailPush, type MailChanged } from "./mail-refresh";

export type { Aspect, Keyword } from "./mail-updates";

/**
 * Writes to mail that the list and the reading pane share. A change shows only after the server
 * confirms it: while it is in flight only that message's control is locked, on a confirmed change
 * `changed` moves so both panes sync again (deltas only, mailbox counts included), and on failure
 * nothing on screen changes and the reason appears once.
 *
 * An `unknown` outcome blocks further writes of that aspect (a keyword, or any move) to that message
 * until a read settles it (see `MailWriter`). Pressing the control meanwhile runs the read, not the
 * write; `unverified` lets the screen say so. The selection UI passes many ids and keeps
 * `settled.keepSelected` selected.
 */
@Service()
export class MailActions {
  private readonly connection = inject(ConnectionService);
  private readonly writer = new MailWriter((command, args) => invoke(command, args));
  /** Bumps after each change the server may have made; views reload when it moves. */
  readonly changed = signal(0);
  private readonly inFlight = signal<ReadonlySet<string>>(new Set());
  /** Moves whenever the writer's blocks or kept values may have changed, so readers re-read. */
  private readonly blocks = signal(0);

  constructor() {
    if (!this.connection.native) return;
    const destroy = inject(DestroyRef);
    const listener = listen<MailChanged>("mail-changed", ({payload}) => {
      if (matchesMailPush(payload, this.connection.connected()?.id, this.connection.accountId())) {
        this.changed.update(count => count + 1);
      }
    });
    void listener.catch(() => undefined);
    destroy.onDestroy(() => { void listener.then(unlisten => unlisten(), () => undefined); });

    // Serialize requests so a slow IPC cannot put an older connection back in charge.
    let requests = Promise.resolve();
    effect(() => {
      const id = this.connection.connected()?.id ?? null;
      requests = requests.then(async () => {
        await listener;
        if (destroy.destroyed || (this.connection.connected()?.id ?? null) !== id) return;
        await invoke("mail_watch", {connectionId: id});
      }).catch(() => undefined);
    });
  }

  busy(emailId: string, aspect: Aspect): boolean {
    return this.inFlight().has(`${emailId} ${aspect}`);
  }

  /** Stamp for a view to take when it starts loading (see `MailWriter.mark`). */
  mark(): number {
    return this.writer.mark();
  }

  /** Whether the message has the keyword, as the screen should show it (see `MailWriter.shown`). */
  shown(accountId: string, emailId: string, keyword: Keyword, viewHas: boolean | null, loadedFrom: number): boolean | null {
    this.blocks();
    const connectionId = this.connection.connected()?.id;
    return connectionId === undefined
      ? viewHas
      : this.writer.shown({connectionId, accountId}, emailId, keyword, viewHas, loadedFrom);
  }

  /** Whether the message is in the mailbox, as the screen should show it (see `MailWriter.shownIn`). */
  shownIn(accountId: string, emailId: string, mailboxId: string, viewIn: boolean, loadedFrom: number): boolean {
    this.blocks();
    const connectionId = this.connection.connected()?.id;
    return connectionId === undefined
      ? viewIn
      : this.writer.shownIn({connectionId, accountId}, emailId, mailboxId, viewIn, loadedFrom);
  }

  /** Whether the last write of this aspect to this message has an unknown outcome not yet read. */
  unverified(accountId: string, emailId: string, aspect: Aspect): boolean {
    this.blocks();
    const connectionId = this.connection.connected()?.id;
    return connectionId !== undefined && this.writer.isUnverified({connectionId, accountId}, emailId, aspect);
  }

  setKeyword(accountId: string, emailId: string, keyword: Keyword, on: boolean): Promise<void> {
    return this.setKeywords(accountId, [emailId], keyword, on).then(() => undefined);
  }

  setKeywords(accountId: string, emailIds: readonly string[], keyword: Keyword, on: boolean): Promise<WriteResult | null> {
    return this.apply(accountId, emailIds, {kind: "keyword", keyword, on}, keywordActionName(keyword, on));
  }

  /**
   * Moves messages from the mailbox they are listed in to `to` (see `cache::move_emails` in Rust).
   * Into Trash or Junk it is exclusive (see `exclusiveMove`).
   */
  move(accountId: string, emailIds: readonly string[], from: string, to: MailboxRow): Promise<WriteResult | null> {
    const change = {kind: "move", from, to: to.id, exclusive: exclusiveMove(to)} as const;
    return this.apply(accountId, emailIds, change, moveActionName(to));
  }

  private async apply(accountId: string, emailIds: readonly string[], change: Change, action: string): Promise<WriteResult | null> {
    const connectionId = this.connection.connected()?.id;
    const aspect: Aspect = change.kind === "keyword" ? change.keyword : "mailboxes";
    const ids = emailIds.filter(id => !this.inFlight().has(`${id} ${aspect}`));
    if (connectionId === undefined || !ids.length) return null;
    const keys = ids.map(id => `${id} ${aspect}`);
    this.inFlight.update(set => new Set([...set, ...keys]));
    try {
      const result = await this.writer.write({connectionId, accountId}, ids, change, action);
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
export function keywordActionName(keyword: Keyword, on: boolean): string {
  return keyword === "seen" ? (on ? "Mark as read" : "Mark as unread") : (on ? "Star" : "Remove star");
}

/** "Move to Trash" for the trash role whatever the server calls it ("Deleted Items"), else the name. */
export function moveActionName(to: MailboxRow): string {
  return to.role === "trash" ? "Move to Trash" : `Move to ${to.name || "mailbox"}`;
}
