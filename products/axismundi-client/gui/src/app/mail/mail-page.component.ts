import { Component, computed, effect, inject, input, resource } from "@angular/core";
import { Router, RouterLink } from "@angular/router";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";
import { MailDetailComponent } from "./mail-detail.component";
import { ListView, rowLabel } from "./mail-rows";

/**
 * Mailboxes, one window of messages and the reading pane for the Account selected in the app bar.
 * The selection lives in the URL (`/mail?mailbox=…&email=…`), so one route keeps the list loaded
 * while messages open. Narrow-window folding of docs/DESIGN-MAIL-GUI.md is not built yet.
 */
@Component({
  selector: "app-mail-page",
  imports: [MailDetailComponent, RouterLink],
  templateUrl: "./mail-page.component.html",
  styleUrl: "./mail-page.component.css",
})
export class MailPageComponent {
  protected readonly connection = inject(ConnectionService);
  private readonly router = inject(Router);

  /** Query parameters, bound by the router. */
  readonly mailbox = input<string>();
  readonly email = input<string>();

  protected readonly view = resource({
    params: () => {
      const connectionId = this.connection.connected()?.id;
      const accountId = this.connection.accountId();
      return connectionId === undefined || !accountId
        ? undefined
        : {connectionId, accountId, mailboxId: this.mailbox() ?? null, rows: "messages"};
    },
    loader: ({params}) => invoke<ListView>("mail_sync", params),
  });

  protected readonly mailboxes = computed(() => this.view.hasValue() ? this.view.value().mailboxes : []);
  protected readonly selectedMailbox = computed(() => this.view.hasValue() ? this.view.value().mailboxId : null);
  protected readonly rows = computed(() => this.view.hasValue() ? this.view.value().rows.map(rowLabel) : []);

  constructor() {
    // Mailbox and message ids belong to one Account: switching Accounts clears the selection.
    let previous: string | null | undefined;
    effect(() => {
      const accountId = this.connection.accountId();
      if (previous !== undefined && previous !== accountId) void this.router.navigate(["/mail"]);
      previous = accountId;
    });
  }
}
