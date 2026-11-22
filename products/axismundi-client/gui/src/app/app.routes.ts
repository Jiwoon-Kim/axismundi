import { inject } from "@angular/core";
import { CanActivateFn, Router, Routes } from "@angular/router";
import { ConnectionService, MAIL } from "./connection.service";

/** A domain route opens only while the connection offers it; otherwise Settings. */
const offered = (available: (connection: ConnectionService) => boolean): CanActivateFn =>
  () => available(inject(ConnectionService)) || inject(Router).parseUrl("/settings");

export const routes: Routes = [
  {
    path: "",
    pathMatch: "full",
    redirectTo: () => {
      const connection = inject(ConnectionService);
      if (connection.actors().length) return "activitypub";
      if (connection.supports(MAIL)) return "mail";
      return "settings";
    },
  },
  {
    path: "activitypub",
    canActivate: [offered(connection => connection.actors().length > 0)],
    loadComponent: () => import("./activitypub/activitypub-page.component").then(m => m.ActivitypubPageComponent),
  },
  {
    path: "mail",
    canActivate: [offered(connection => connection.supports(MAIL))],
    loadComponent: () => import("./mail/mail-page.component").then(m => m.MailPageComponent),
  },
  {
    path: "settings",
    loadComponent: () => import("./settings/settings-page").then(m => m.SettingsPage),
    children: [
      {path: "", pathMatch: "full", redirectTo: "connections"},
      {path: "connections", loadComponent: () => import("./settings/connection-page.component").then(m => m.ConnectionPageComponent)},
      {path: "appearance", loadComponent: () => import("./settings/appearance-page").then(m => m.AppearancePage)},
    ],
  },
  {path: "**", redirectTo: ""},
];
