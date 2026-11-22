import { Component, computed, effect, inject, input, linkedSignal, resource, untracked } from "@angular/core";
import { MatButton } from "@angular/material/button";
import { MatIcon } from "@angular/material/icon";
import { NgTemplateOutlet } from "@angular/common";
import { RouterLink } from "@angular/router";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";
import { MailActions } from "./mail-actions";
import { DetailView, addressList, bodyNotice } from "./mail-detail";
import { rowLabel } from "./mail-rows";
import { ThreadView } from "./mail-thread";

/**
 * The reading pane: the selected message's headers and plain-text body, inside its conversation.
 * Other members show one line each and open by navigation; only the selected one has its body.
 */
@Component({
  selector: "app-mail-detail",
  imports: [MatButton, MatIcon, NgTemplateOutlet, RouterLink],
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

  protected readonly detail = resource({
    params: () => {
      const connectionId = this.connection.connected()?.id;
      const emailId = this.emailId();
      return connectionId === undefined || !emailId
        ? undefined
        : {connectionId, accountId: this.accountId(), emailId};
    },
    loader: ({params}) => invoke<DetailView>("mail_read", params),
  });

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
    // A confirmed change reads the message and its conversation again.
    let seen = this.actions.changed();
    effect(() => {
      const count = this.actions.changed();
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
