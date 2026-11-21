import { Component, computed, signal } from "@angular/core";
import { JsonPipe } from "@angular/common";
import { MatButton } from "@angular/material/button";
import { MatCheckbox } from "@angular/material/checkbox";
import { MatDivider } from "@angular/material/divider";
import { MatFormField, MatLabel } from "@angular/material/form-field";
import { MatInput } from "@angular/material/input";
import { MatList, MatListItem } from "@angular/material/list";
import { MatProgressBar } from "@angular/material/progress-bar";
import { MatAccordion, MatExpansionPanel, MatExpansionPanelHeader, MatExpansionPanelTitle } from "@angular/material/expansion";
import { MatSelect, MatOption } from "@angular/material/select";
import { MatToolbar } from "@angular/material/toolbar";
import { invoke, isTauri } from "@tauri-apps/api/core";

type Probe<T> = {status: "found" | "login_required"; detail: T}
  | {status: "not_found" | "unconfirmed"; detail: string} | {status: "skipped"};

interface Discovery {
  discovery: {
    base: string;
    wordpress: Probe<{name: string; ap_session_advertised: boolean}>;
    jmap: Probe<{session_url: string}>;
    activitypub: Probe<{software: string | null}>;
  };
  login: {wordpress: string | null; jmap: string | null; activitypub: string | null};
  notices: string[];
}

interface Account {
  id: string;
  name: string;
  isPersonal: boolean;
  isReadOnly: boolean;
  capabilities: string[];
}

interface Actor {id: string}
interface Connected {
  id: number;
  wordpress: string | null;
  wordpress_document: object | null;
  jmap: Account[] | null;
  jmap_document: object | null;
  activitypub: Actor[] | null;
  activitypub_document: object | null;
  errors: string[];
}

type Outbox = {orderedItems?: {object?: string | {id?: string}}[]};

@Component({
  selector: "app-root",
  imports: [JsonPipe, MatAccordion, MatButton, MatCheckbox, MatDivider, MatExpansionPanel, MatExpansionPanelHeader, MatExpansionPanelTitle,
    MatFormField, MatInput, MatLabel, MatList, MatListItem, MatOption, MatProgressBar, MatSelect, MatToolbar],
  templateUrl: "./app.component.html",
  styleUrl: "./app.component.css",
})
export class AppComponent {
  protected readonly probeWordpress = signal(false);
  protected readonly probeJmap = signal(true);
  protected readonly probeActivitypub = signal(false);
  protected readonly native = isTauri();
  protected readonly loading = signal<"discover" | "connect" | "actor" | "object" | "disconnect" | null>(null);
  protected readonly discovered = signal<Discovery | null>(null);
  protected readonly connected = signal<Connected | null>(null);
  protected readonly error = signal("");
  protected readonly actorId = signal("");
  protected readonly actorDocument = signal<object | null>(null);
  protected readonly outbox = signal<Outbox | null>(null);
  protected readonly objectId = signal("");
  protected readonly objectDocument = signal<object | null>(null);
  protected readonly objectIds = computed(() => {
    const items = this.outbox()?.orderedItems;
    if (!Array.isArray(items)) return [];
    return [...new Set(items.flatMap(item => {
      const object = item?.object;
      const id = typeof object === "string" ? object : object?.id;
      return typeof id === "string" && id ? [id] : [];
    }))];
  });
  protected readonly addresses = computed(() => {
    const login = this.discovered()?.login;
    return login ? [login.wordpress, login.jmap, login.activitypub].filter((url): url is string => url !== null) : [];
  });

  protected async discover(event: SubmitEvent, address: string): Promise<void> {
    event.preventDefault();
    if (this.loading()) return;
    this.discovered.set(null);
    this.connected.set(null);
    this.clearDocuments();
    this.error.set("");
    this.loading.set("discover");
    try {
      this.discovered.set(await invoke<Discovery>("discover_server", { input: address, options: {
        wordpress:this.probeWordpress(), jmap:this.probeJmap(), activitypub:this.probeActivitypub()
      }}));
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.loading.set(null);
    }
  }

  private clearDocuments(): void {
    this.actorId.set("");
    this.actorDocument.set(null);
    this.outbox.set(null);
    this.objectId.set("");
    this.objectDocument.set(null);
  }

  protected async disconnect(): Promise<void> {
    const connectionId = this.connected()?.id;
    if (connectionId === undefined || this.loading()) return;
    this.loading.set("disconnect");
    this.error.set("");
    try {
      await invoke("disconnect_server", {connectionId});
      this.connected.set(null);
      this.clearDocuments();
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.loading.set(null);
    }
  }

  protected async selectActor(actorId: string): Promise<void> {
    const connectionId = this.connected()?.id;
    if (connectionId === undefined || this.loading()) return;
    this.clearDocuments();
    this.actorId.set(actorId);
    this.error.set("");
    this.loading.set("actor");
    try {
      const args = {connectionId, actorId};
      this.actorDocument.set(await invoke<object>("actor_document", args));
      this.outbox.set(await invoke<Outbox>("outbox_page", args));
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.loading.set(null);
    }
  }

  protected async selectObject(objectId: string): Promise<void> {
    const connectionId = this.connected()?.id;
    const actorId = this.actorId();
    if (connectionId === undefined || !actorId || this.loading()) return;
    this.objectId.set(objectId);
    this.objectDocument.set(null);
    this.error.set("");
    this.loading.set("object");
    try {
      this.objectDocument.set(await invoke<object>("object_by_id", {connectionId, actorId, objectId}));
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.loading.set(null);
    }
  }

  protected async connect(event: SubmitEvent, username: string, input: HTMLInputElement): Promise<void> {
    event.preventDefault();
    if (this.loading()) return;
    const password = input.value;
    input.value = "";
    this.connected.set(null);
    this.error.set("");
    this.loading.set("connect");
    try {
      this.connected.set(await invoke<Connected>("connect_server", {
        approved: this.addresses(), username, password,
      }));
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.loading.set(null);
    }
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
