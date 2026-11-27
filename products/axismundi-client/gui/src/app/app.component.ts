import { Component, computed, effect, inject, untracked } from "@angular/core";
import { NgTemplateOutlet } from "@angular/common";
import { MatSnackBar } from "@angular/material/snack-bar";
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from "@angular/router";
import { MatIcon } from "@angular/material/icon";
import { MatIconButton } from "@angular/material/button";
import { MatMenu, MatMenuItem, MatMenuTrigger } from "@angular/material/menu";
import { MatDivider } from "@angular/material/divider";
import { MatProgressBar } from "@angular/material/progress-bar";
import { MatToolbar } from "@angular/material/toolbar";
import { MatTooltip } from "@angular/material/tooltip";
import { ConnectionService, MAIL } from "./connection.service";
import { Scaffold } from "./shell/scaffold";
import { CONTACTS } from "./contacts/contact-model";

interface Destination {path: string; label: string; icon: string}

/**
 * The thin app shell (M3 Scaffold): app bar with the account menu, the destination rail or app
 * menu, and the routed domain screen. The interface layout setting picks between them. It knows which domains a connection offers, never how a
 * domain lays itself out.
 */
@Component({
  selector: "app-root",
  imports: [MatDivider, MatIcon, MatIconButton, MatMenu, MatMenuItem, MatMenuTrigger, MatProgressBar, MatToolbar,
    MatTooltip, NgTemplateOutlet, RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: "./app.component.html",
  styleUrl: "./app.component.css",
})
export class AppComponent {
  protected readonly connection = inject(ConnectionService);
  protected readonly scaffold = inject(Scaffold);
  private readonly router = inject(Router);
  private readonly snackBar = inject(MatSnackBar);

  /**
   * Order is fixed; a destination appears only when the connection offers it and the
   * screen exists. Contacts and Calendar join when their screens are built.
   */
  protected readonly destinations = computed<Destination[]>(() => [
    ...(this.connection.actors().length ? [{path: "/activitypub", label: "ActivityPub", icon: "public"}] : []),
    ...(this.connection.supports(MAIL) ? [{path: "/mail", label: "Mail", icon: "mail"}] : []),
    ...(this.connection.supports(CONTACTS) ? [{path: "/contacts", label: "Contacts", icon: "contacts"}] : []),
  ]);

  constructor() {
    // Results that need attention are shown once; the lasting state stays on its own screen.
    effect(() => {
      const notice = this.connection.notice();
      if (!notice) return;
      this.snackBar.open(notice, "Dismiss", {duration: 10000});
      untracked(() => this.connection.notice.set(""));
    });
    // A new connection opens its default domain; losing it returns to Settings.
    let hadConnection = false;
    effect(() => {
      const connected = this.connection.connected();
      const shouldNavigate = !!connected || hadConnection;
      hadConnection = !!connected;
      if (!shouldNavigate || untracked(() => this.router.url.startsWith("/settings/appearance"))) return;
      void this.router.navigateByUrl(connected ? "/" : "/settings");
    });
  }
}
