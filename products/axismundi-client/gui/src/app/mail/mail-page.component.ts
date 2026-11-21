import { Component, computed, inject, linkedSignal, resource } from "@angular/core";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";
import { ListView, rowLabel } from "./mail-rows";

/**
 * First wiring of the Mail commands: mailbox names and one window of messages for the Account
 * selected in the app bar. The panes of docs/DESIGN-MAIL-GUI.md replace this layout.
 */
@Component({
  selector: "app-mail-page",
  templateUrl: "./mail-page.component.html",
  styleUrl: "./mail-page.component.css",
})
export class MailPageComponent {
  protected readonly connection = inject(ConnectionService);

  /** Cleared when the Account changes: a mailbox id belongs to one Account. */
  protected readonly mailboxId = linkedSignal<unknown, string | null>({
    source: () => this.connection.accountId(),
    computation: () => null,
  });

  protected readonly view = resource({
    params: () => {
      const connectionId = this.connection.connected()?.id;
      const accountId = this.connection.accountId();
      return connectionId === undefined || !accountId
        ? undefined
        : {connectionId, accountId, mailboxId: this.mailboxId(), rows: "messages"};
    },
    loader: ({params}) => invoke<ListView>("mail_sync", params),
  });

  protected readonly mailboxes = computed(() => this.view.hasValue() ? this.view.value().mailboxes : []);
  protected readonly selectedMailbox = computed(() => this.view.hasValue() ? this.view.value().mailboxId : null);
  protected readonly rows = computed(() => this.view.hasValue() ? this.view.value().rows.map(rowLabel) : []);
}
