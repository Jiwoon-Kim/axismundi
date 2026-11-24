import { Component, computed, effect, inject, input, linkedSignal, resource, signal, untracked } from "@angular/core";
import { MatButton } from "@angular/material/button";
import { MatMenuTrigger } from "@angular/material/menu";
import { MatTooltip } from "@angular/material/tooltip";
import { MatIcon } from "@angular/material/icon";
import { NgTemplateOutlet } from "@angular/common";
import { Router, RouterLink } from "@angular/router";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";
import { MailActions } from "./mail-actions";
import { DetailView, addressList, bodyNotice, markReadOnOpen, maySetSeen } from "./mail-detail";
import { MailMoveMenuComponent } from "./mail-move-menu.component";
import { exclusiveMove, type MoveOptions } from "./mail-moves";
import type { MailboxRow } from "./mail-rows";
import { rowLabel } from "./mail-rows";
import { ThreadView } from "./mail-thread";
import { reloadRevision } from "./mail-refresh";

/**
 * The reading pane: the selected message's headers and plain-text body, inside its conversation.
 * Other members show one line each and open by navigation; only the selected one has its body.
 */
@Component({
  selector: "app-mail-detail",
  imports: [MailMoveMenuComponent, MatButton, MatIcon, MatMenuTrigger, MatTooltip, NgTemplateOutlet, RouterLink],
  templateUrl: "./mail-detail.component.html",
  styleUrl: "./mail-detail.component.css",
})
export class MailDetailComponent {
  private readonly connection = inject(ConnectionService);
  protected readonly actions = inject(MailActions);
  readonly accountId = input.required<string>();
  readonly emailId = input<string | null>(null);
  /** Kept on member links so the list beside the pane stays on the same mailbox. */
  readonly mailbox = input<string | null>(null);
  /**
   * The mailbox the list beside the pane shows (the default Inbox resolved), which a move takes the
   * message out of, and the moves that listing offers.
   */
  readonly listedIn = input<string | null>(null);
  readonly moves = input<MoveOptions | null>(null);
  /** The Account's mailboxes, for their rights. */
  readonly mailboxes = input<readonly MailboxRow[]>([]);
  private readonly router = inject(Router);

  /** Moves the open message; once the server confirms it left this listing, back to the list. */
  protected async moveTo(id: string, to: MailboxRow | null): Promise<void> {
    const from = this.listedIn();
    if (!to || (!from && !exclusiveMove(to))) return;
    const accountId = this.accountId();
    const connectionId = this.connection.connected()?.id;
    const result = await this.actions.move(accountId, [id], from, to);
    // Only if the pane still shows that message of that login, Account and listing: ids repeat across
    // connections, so an answer from an earlier login must not close the new one's pane.
    const still = this.connection.connected()?.id === connectionId && this.emailId() === id
      && this.accountId() === accountId && this.listedIn() === from;
    if (still && from !== null && result?.settled?.confirmed.includes(id)) {
      void this.router.navigate(["/mail"], {queryParams: {mailbox: this.mailbox()}});
    }
  }

  protected readonly detail = resource({
    params: () => {
      const connectionId = this.connection.connected()?.id;
      const emailId = this.emailId();
      return connectionId === undefined || !emailId
        ? undefined
        : {connectionId, accountId: this.accountId(), emailId};
    },
    loader: async ({params}) => {
      const from = this.actions.mark();
      const detail = await invoke<DetailView>("mail_read", params);
      this.loadedFrom.set(from);
      return detail;
    },
  });
  /** When the message on screen started loading; values the server confirmed after that show instead. */
  private readonly loadedFrom = signal(0);
  /**
   * Whether this opening of the message has been handled for read state. Resets whenever the open
   * id changes (including to none and back), so each opening marks read at most once.
   */
  private readonly openHandled = linkedSignal({source: this.emailId, computation: () => false});

  protected starred(id: string, viewFlagged: boolean | null): boolean {
    return this.actions.shown(this.accountId(), id, "flagged", viewFlagged, this.loadedFrom()) === true;
  }

  /** Unknown read state counts as unread, as before: the button offers "Mark as read". */
  protected unread(id: string, viewUnread: boolean | null): boolean {
    return this.actions.shown(this.accountId(), id, "seen", viewUnread === null ? null : !viewUnread, this.loadedFrom()) !== true;
  }

  /**
   * The thread resource depends on this id only, so opening another member of the same thread does
   * not refetch it. While the next message loads its detail has no value; the id is held until the
   * load settles instead of dropping to null and back.
   */
  private readonly threadId = linkedSignal<DetailView | null | undefined, string | null>({
    source: () => this.detail.hasValue() ? this.detail.value() : this.detail.isLoading() ? undefined : null,
    computation: (detail, previous) =>
      detail === undefined ? previous?.value ?? null : detail?.kind === "message" ? detail.threadId : null,
  });

  protected readonly thread = resource({
    params: () => {
      const connectionId = this.connection.connected()?.id;
      const threadId = this.threadId();
      return connectionId === undefined || !threadId
        ? undefined
        : {connectionId, accountId: this.accountId(), threadId};
    },
    loader: ({params}) => invoke<ThreadView>("mail_thread", params),
  });

  private readonly view = computed(() => {
    const thread = this.thread.hasValue() ? this.thread.value() : undefined;
    return thread?.kind === "thread" ? thread.messages : [];
  });
  protected readonly members = computed(() => this.view().map(rowLabel));
  protected memberDate(id: string): string | null {
    const row = this.view().find(member => member.id === id);
    return row?.kind === "message" ? row.receivedAt : null;
  }

  constructor() {
    // Opening an unread message marks it read, once, when its own detail has landed. Reloads after
    // `changed` and a "Mark as unread" pressed while it is open do not trigger it again.
    effect(() => {
      const id = this.emailId();
      if (!id || this.openHandled() || !this.detail.hasValue()) return;
      const message = this.detail.value();
      if (message.id !== id) return;
      untracked(() => {
        this.openHandled.set(true);
        if (message.kind !== "message") return;
        const accountId = this.accountId();
        const seen = this.actions.shown(accountId, id, "seen", message.unread === null ? null : !message.unread, this.loadedFrom());
        const allowed = maySetSeen(this.mailboxes(), message.mailboxIds, this.listedIn());
        if (markReadOnOpen(seen, this.actions.unverified(accountId, id, "seen"), allowed)) {
          void this.actions.setKeyword(accountId, id, "seen", true);
        }
      });
    });
    // A confirmed change reads the message and its conversation again.
    let seen = this.actions.changed();
    effect(() => {
      const requested = this.actions.changed();
      const count = reloadRevision(seen, requested,
        requested !== seen && (this.detail.isLoading() || this.thread.isLoading()));
      if (count !== seen) {
        seen = count;
        untracked(() => {
          this.detail.reload();
          this.thread.reload();
        });
      }
    });
  }

  private readonly format = new Intl.DateTimeFormat(undefined, {dateStyle: "medium", timeStyle: "short"});
  protected when(iso: string): string {
    const date = new Date(iso);
    return Number.isNaN(date.getTime()) ? iso : this.format.format(date);
  }

  protected readonly addressList = addressList;
  protected readonly bodyNotice = bodyNotice;
}
