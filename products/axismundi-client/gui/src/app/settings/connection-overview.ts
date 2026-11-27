import { Component, computed, inject } from "@angular/core";
import { toSignal } from "@angular/core/rxjs-interop";
import { JsonPipe } from "@angular/common";
import { ActivatedRoute, RouterLink } from "@angular/router";
import { MatButton } from "@angular/material/button";
import { MatCard, MatCardActions, MatCardContent, MatCardHeader, MatCardSubtitle, MatCardTitle } from "@angular/material/card";
import { MatExpansionPanel, MatExpansionPanelHeader, MatExpansionPanelTitle } from "@angular/material/expansion";
import { ConnectionService } from "../connection.service";

/**
 * One saved sign-in and the protocols it reached (docs/DESIGN-CONNECTION-DISCOVERY.md, "세 레인"):
 * JMAP, ActivityPub and WordPress each answer from their own document, and each gets its own page
 * under this one. Only JMAP has that page yet; the other two show what they returned.
 */
@Component({
  imports: [JsonPipe, RouterLink, MatButton, MatCard, MatCardActions, MatCardContent, MatCardHeader, MatCardSubtitle,
    MatCardTitle, MatExpansionPanel, MatExpansionPanelHeader, MatExpansionPanelTitle],
  templateUrl: "./connection-overview.html",
  styleUrl: "./connection-overview.css",
})
export class ConnectionOverviewPage {
  protected readonly connection = inject(ConnectionService);
  private readonly params = toSignal(inject(ActivatedRoute).paramMap, {requireSync: true});
  protected readonly item = computed(() =>
    this.connection.connections().find(item => String(item.id) === this.params().get("id")) ?? null);
  protected readonly jmapCapabilities = computed(() =>
    Object.keys((this.item()?.jmap_document as {capabilities?: object} | null)?.capabilities ?? {}).length);
}
