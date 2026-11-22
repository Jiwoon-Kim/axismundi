import { Component, computed, effect, inject, input, resource, signal, untracked } from "@angular/core";
import { toSignal } from "@angular/core/rxjs-interop";
import { BreakpointObserver } from "@angular/cdk/layout";
import { MatIconButton, MatIconAnchor } from "@angular/material/button";
import { MatIcon } from "@angular/material/icon";
import { MatSidenav, MatSidenavContainer, MatSidenavContent } from "@angular/material/sidenav";
import { MatTooltip } from "@angular/material/tooltip";
import { Router, RouterLink } from "@angular/router";
import { map } from "rxjs";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";
import { MailDetailComponent } from "./mail-detail.component";
import { MailActions } from "./mail-actions";
import { ListView, rowLabel } from "./mail-rows";

/**
 * M3 window size classes (Material 3, Layout › Breakpoints). CDK's `Breakpoints` are the older
 * 600/960/1280/1920 bands, so the queries are given here.
 */
const LARGE = "(min-width: 1200px)";
const EXPANDED = "(min-width: 840px)";

/**
 * Mailboxes, one window of messages and the reading pane for the Account selected in the app bar.
 * The window size class sets how many panes show (docs/DESIGN-MAIL-GUI.md); inside the list, row
 * layout follows the list's own width through a container query. The selection lives in the URL
 * (`/mail?mailbox=…&email=…`), so one route keeps the list loaded while messages open.
 */
@Component({
  selector: "app-mail-page",
  imports: [MailDetailComponent, MatIcon, MatIconAnchor, MatIconButton, MatSidenav, MatSidenavContainer,
    MatSidenavContent, MatTooltip, RouterLink],
  templateUrl: "./mail-page.component.html",
  styleUrl: "./mail-page.component.css",
})
export class MailPageComponent {
  protected readonly connection = inject(ConnectionService);
  protected readonly actions = inject(MailActions);
  private readonly router = inject(Router);

  /** Query parameters, bound by the router. */
  readonly mailbox = input<string>();
  readonly email = input<string>();

  private readonly window = toSignal(
    inject(BreakpointObserver).observe([LARGE, EXPANDED]).pipe(map(state => state.breakpoints)),
    {requireSync: true},
  );
  /** ≥1200: mailboxes, list and detail side by side. */
  protected readonly large = computed(() => this.window()[LARGE]);
  /** ≥840: list beside detail. Below that, one pane at a time. */
  protected readonly twoPanes = computed(() => this.window()[EXPANDED]);
  protected readonly drawerOpen = signal(false);

  protected readonly view = resource({
    params: () => {
      const connectionId = this.connection.connected()?.id;
      const accountId = this.connection.accountId();
      return connectionId === undefined || !accountId
        ? undefined
        : {connectionId, accountId, mailboxId: this.mailbox() ?? null, rows: "messages"};
    },
    loader: async ({params}) => {
      const from = this.actions.mark();
      const view = await invoke<ListView>("mail_sync", params);
      this.loadedFrom.set(from);
      return view;
    },
  });
  /** When the rows on screen started loading; values the server confirmed after that show instead. */
  private readonly loadedFrom = signal(0);

  protected starred(accountId: string, id: string, viewFlagged: boolean | null): boolean {
    return this.actions.shown(accountId, id, "flagged", viewFlagged, this.loadedFrom()) === true;
  }

  protected unread(accountId: string, id: string, viewUnread: boolean): boolean {
    return this.actions.shown(accountId, id, "seen", !viewUnread, this.loadedFrom()) === false;
  }

  protected readonly mailboxes = computed(() => this.view.hasValue() ? this.view.value().mailboxes : []);
  protected readonly selectedMailbox = computed(() => this.view.hasValue() ? this.view.value().mailboxId : null);
  protected readonly mailboxName = computed(
    () => this.mailboxes().find(box => box.id === this.selectedMailbox())?.name ?? "Mail");
  protected readonly rows = computed(() => this.view.hasValue() ? this.view.value().rows.map(rowLabel) : []);

  private readonly time = new Intl.DateTimeFormat(undefined, {timeStyle: "short"});
  private readonly day = new Intl.DateTimeFormat(undefined, {month: "short", day: "numeric"});
  /** Today's mail shows its time, older mail its date. */
  protected listDate(iso: string): string {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return iso;
    return date.toDateString() === new Date().toDateString() ? this.time.format(date) : this.day.format(date);
  }

  constructor() {
    // A confirmed change syncs the listing again: deltas only, with mailbox counts. reload() keeps
    // the rows on screen while it runs.
    let seen = this.actions.changed();
    effect(() => {
      const count = this.actions.changed();
      if (count !== seen) {
        seen = count;
        untracked(() => this.view.reload());
      }
    });
    // Mailbox and message ids belong to one Account: switching Accounts clears the selection.
    let previous: string | null | undefined;
    effect(() => {
      const accountId = this.connection.accountId();
      if (previous !== undefined && previous !== accountId) void this.router.navigate(["/mail"]);
      previous = accountId;
    });
  }
}
