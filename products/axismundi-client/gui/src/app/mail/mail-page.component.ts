import { Component, inject } from "@angular/core";
import { ConnectionService } from "../connection.service";

/** Placeholder until the Mail Tauri commands land; the panes follow docs/DESIGN-MAIL-GUI.md. */
@Component({
  selector: "app-mail-page",
  template: `
    <h1>Mail</h1>
    @if (connection.account(); as account) {
      <p role="status">{{ account.name || account.id }} · mailboxes, list and reading pane are not built yet.</p>
    } @else {
      <p role="status">Select a JMAP Account from the account menu.</p>
    }
  `,
  styles: `
    :host { display: block; padding: 20px 24px; }
    h1 { font: var(--mat-sys-headline-small); margin: 0 0 12px; }
  `,
})
export class MailPageComponent {
  protected readonly connection = inject(ConnectionService);
}
