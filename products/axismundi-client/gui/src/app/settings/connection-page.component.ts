import { Component, inject, signal } from "@angular/core";
import { JsonPipe } from "@angular/common";
import { MatButton } from "@angular/material/button";
import { MatCheckbox } from "@angular/material/checkbox";
import { MatDivider } from "@angular/material/divider";
import { MatFormField, MatLabel } from "@angular/material/form-field";
import { MatInput } from "@angular/material/input";
import { MatList, MatListItem } from "@angular/material/list";
import { MatAccordion, MatExpansionPanel, MatExpansionPanelHeader, MatExpansionPanelTitle } from "@angular/material/expansion";
import { ConnectionService, Discovery, Probe } from "../connection.service";

/** Server discovery, sign-in and the raw Session documents. Usable with no connection. */
@Component({
  selector: "app-connection-page",
  imports: [JsonPipe, MatAccordion, MatButton, MatCheckbox, MatDivider, MatExpansionPanel, MatExpansionPanelHeader,
    MatExpansionPanelTitle, MatFormField, MatInput, MatLabel, MatList, MatListItem],
  templateUrl: "./connection-page.component.html",
  styleUrl: "./connection-page.component.css",
})
export class ConnectionPageComponent {
  protected readonly connection = inject(ConnectionService);
  protected readonly probeWordpress = signal(false);
  protected readonly probeJmap = signal(true);
  protected readonly probeActivitypub = signal(false);

  protected discover(event: SubmitEvent, address: string): void {
    event.preventDefault();
    void this.connection.discover(address, {
      wordpress: this.probeWordpress(), jmap: this.probeJmap(), activitypub: this.probeActivitypub(),
    });
  }

  protected connect(event: SubmitEvent, username: string, input: HTMLInputElement): void {
    event.preventDefault();
    const password = input.value;
    input.value = "";
    void this.connection.connect(username, password);
  }

  protected describe<T>(probe: Probe<T>, label: (detail: T) => string): string {
    switch (probe.status) {
      case "skipped": return "Not requested";
      case "found": return label(probe.detail);
      case "login_required": return `Sign-in required · ${label(probe.detail)}`;
      case "not_found": return `Not advertised · ${probe.detail}`;
      case "unconfirmed": return `Could not confirm · ${probe.detail}`;
    }
  }

  protected readonly wordpressLabel = (info: {name: string}) => info.name || "Detected";
  protected readonly jmapLabel = (info: {session_url: string}) => info.session_url;
  protected readonly apLabel = (info: {software: string | null}) => info.software || "Advertised";

  protected apSessionStatus(probe: Discovery["discovery"]["wordpress"]): string {
    if (probe.status === "skipped") return "Not requested";
    if (probe.status === "unconfirmed") return `Could not confirm · ${probe.detail}`;
    return typeof probe.detail !== "string" && probe.detail.ap_session_advertised
      ? "Advertised · sign-in required"
      : "Not advertised";
  }
}
