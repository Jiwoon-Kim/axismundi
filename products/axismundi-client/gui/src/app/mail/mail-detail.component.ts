import { Component, inject, input, resource } from "@angular/core";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";
import { DetailView, addressList, bodyNotice } from "./mail-detail";

/** The reading pane: one message's headers and plain-text body, fetched on demand. */
@Component({
  selector: "app-mail-detail",
  templateUrl: "./mail-detail.component.html",
  styleUrl: "./mail-detail.component.css",
})
export class MailDetailComponent {
  private readonly connection = inject(ConnectionService);
  readonly accountId = input.required<string>();
  readonly emailId = input<string | null>(null);

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

  private readonly format = new Intl.DateTimeFormat(undefined, {dateStyle: "medium", timeStyle: "short"});
  protected when(iso: string): string {
    const date = new Date(iso);
    return Number.isNaN(date.getTime()) ? iso : this.format.format(date);
  }

  protected readonly addressList = addressList;
  protected readonly bodyNotice = bodyNotice;
}
