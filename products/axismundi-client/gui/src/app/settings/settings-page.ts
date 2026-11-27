import { Component, computed, inject, signal } from "@angular/core";
import { toSignal } from "@angular/core/rxjs-interop";
import { BreakpointObserver } from "@angular/cdk/layout";
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from "@angular/router";
import { MatIconButton } from "@angular/material/button";
import { MatIcon } from "@angular/material/icon";
import { MatListItem, MatListItemIcon, MatListItemLine, MatListItemTitle, MatNavList } from "@angular/material/list";
import { MatSidenav, MatSidenavContainer, MatSidenavContent } from "@angular/material/sidenav";
import { MatTooltip } from "@angular/material/tooltip";
import { filter, map } from "rxjs";
import { ConnectionService } from "../connection.service";
import { EXPANDED } from "../shell/scaffold";
import { settingsCrumbs } from "./settings-crumbs";

/**
 * Settings as a navigation drawer and a page: sections on the side (each signed-in connection under
 * Connections, so its detail is one step away), a breadcrumb above the page for where you are in it.
 */
@Component({
  imports: [RouterLink, RouterLinkActive, RouterOutlet, MatIconButton, MatIcon, MatNavList, MatListItem, MatListItemIcon,
    MatListItemTitle, MatListItemLine, MatSidenav, MatSidenavContainer, MatSidenavContent, MatTooltip],
  templateUrl: "./settings-page.html",
  styleUrl: "./settings-page.css",
})
export class SettingsPage {
  protected readonly connection = inject(ConnectionService);
  private readonly router = inject(Router);
  protected readonly expanded = toSignal(inject(BreakpointObserver).observe(EXPANDED).pipe(map(state => state.matches)),
    {requireSync: true});
  protected readonly drawer = signal(false);
  private readonly url = toSignal(this.router.events.pipe(filter(event => event instanceof NavigationEnd),
    map(() => this.router.url)), {initialValue: this.router.url});
  protected readonly crumbs = computed(() => settingsCrumbs(this.url(), id =>
    this.connection.connections().find(item => String(item.id) === id)?.username ?? null));

  protected host(server: string): string {
    try { return new URL(server).host; } catch { return server; }
  }
}
