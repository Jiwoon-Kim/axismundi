import { Component, computed, inject, linkedSignal, resource } from "@angular/core";
import { JsonPipe } from "@angular/common";
import { MatFormField, MatLabel } from "@angular/material/form-field";
import { MatProgressBar } from "@angular/material/progress-bar";
import { MatSelect, MatOption } from "@angular/material/select";
import { MatAccordion, MatExpansionPanel, MatExpansionPanelHeader, MatExpansionPanelTitle } from "@angular/material/expansion";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";

type Outbox = {orderedItems?: {object?: string | {id?: string}}[]};

/** Reads the Actor selected in the app bar: its document, first outbox page and one object. */
@Component({
  selector: "app-activitypub-page",
  imports: [JsonPipe, MatAccordion, MatExpansionPanel, MatExpansionPanelHeader, MatExpansionPanelTitle,
    MatFormField, MatLabel, MatOption, MatProgressBar, MatSelect],
  templateUrl: "./activitypub-page.component.html",
  styleUrl: "./activitypub-page.component.css",
})
export class ActivitypubPageComponent {
  protected readonly connection = inject(ConnectionService);

  private readonly target = computed(() => {
    const connectionId = this.connection.connected()?.id;
    const actorId = this.connection.actorId();
    return connectionId === undefined || !actorId ? undefined : {connectionId, actorId};
  });

  protected readonly actor = resource({
    params: () => this.target(),
    loader: async ({params}) => ({
      document: await invoke<object>("actor_document", params),
      outbox: await invoke<Outbox>("outbox_page", params),
    }),
  });

  protected readonly objectIds = computed(() => {
    const items = this.actor.hasValue() ? this.actor.value().outbox.orderedItems : undefined;
    if (!Array.isArray(items)) return [];
    return [...new Set(items.flatMap(item => {
      const object = item?.object;
      const id = typeof object === "string" ? object : object?.id;
      return typeof id === "string" && id ? [id] : [];
    }))];
  });

  /** Cleared whenever the Actor changes. */
  protected readonly objectId = linkedSignal<unknown, string>({source: this.target, computation: () => ""});

  protected readonly object = resource({
    params: () => {
      const target = this.target();
      const objectId = this.objectId();
      return target && objectId ? {...target, objectId} : undefined;
    },
    loader: ({params}) => invoke<object>("object_by_id", params),
  });
}
