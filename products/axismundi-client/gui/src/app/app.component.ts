import { Component, computed, effect, inject } from "@angular/core";
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from "@angular/router";
import { MatIcon } from "@angular/material/icon";
import { MatIconButton } from "@angular/material/button";
import { MatMenu, MatMenuItem, MatMenuTrigger } from "@angular/material/menu";
import { MatDivider } from "@angular/material/divider";
import { MatProgressBar } from "@angular/material/progress-bar";
import { MatToolbar } from "@angular/material/toolbar";
import { MatTooltip } from "@angular/material/tooltip";
import { ConnectionService, MAIL } from "./connection.service";

interface Destination {path: string; label: string; icon: string}

/**
 * The thin app shell: app bar with the account menu, the destination rail and the
 * routed domain screen. It knows which domains a connection offers, never how a
 * domain lays itself out.
 */
@Component({
  selector: "app-root",
  imports: [MatDivider, MatIcon, MatIconButton, MatMenu, MatMenuItem, MatMenuTrigger, MatProgressBar, MatToolbar,
    MatTooltip, RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: "./app.component.html",
  styleUrl: "./app.component.css",
})
export class AppComponent {
  protected readonly connection = inject(ConnectionService);
  private readonly router = inject(Router);

  /**
   * Order is fixed; a destination appears only when the connection offers it and the
   * screen exists. Contacts and Calendar join when their screens are built.
   */
  protected readonly destinations = computed<Destination[]>(() => [
    ...(this.connection.actors().length ? [{path: "/activitypub", label: "ActivityPub", icon: "public"}] : []),
    ...(this.connection.supports(MAIL) ? [{path: "/mail", label: "Mail", icon: "mail"}] : []),
  ]);

  constructor() {
    // A new connection opens its default domain; losing it returns to Settings.
    effect(() => {
      const connected = this.connection.connected();
      void this.router.navigateByUrl(connected ? "/" : "/settings");
    });
  }
}
