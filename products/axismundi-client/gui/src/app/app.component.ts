import { Component, computed, signal } from "@angular/core";
import { MatButton } from "@angular/material/button";
import { MatDivider } from "@angular/material/divider";
import { MatFormField, MatLabel } from "@angular/material/form-field";
import { MatInput } from "@angular/material/input";
import { MatList, MatListItem } from "@angular/material/list";
import { MatProgressBar } from "@angular/material/progress-bar";
import { MatToolbar } from "@angular/material/toolbar";
import { invoke, isTauri } from "@tauri-apps/api/core";

type Probe<T> = {status: "found" | "login_required"; detail: T}
  | {status: "not_found" | "unconfirmed"; detail: string};

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
  actorUri: string | null;
  isPersonal: boolean;
  isReadOnly: boolean;
  capabilities: string[];
}

interface Actor {id: string; name: string; type: string}
interface Connected {
  wordpress: string | null;
  jmap: Account[] | null;
  activitypub: Actor[] | null;
  errors: string[];
}

@Component({
  selector: "app-root",
  imports: [MatButton, MatDivider, MatFormField, MatInput, MatLabel, MatList, MatListItem, MatProgressBar, MatToolbar],
  templateUrl: "./app.component.html",
  styleUrl: "./app.component.css",
})
export class AppComponent {
  protected readonly native = isTauri();
  protected readonly loading = signal<"discover" | "connect" | null>(null);
  protected readonly discovered = signal<Discovery | null>(null);
  protected readonly connected = signal<Connected | null>(null);
  protected readonly error = signal("");
  protected readonly addresses = computed(() => {
    const login = this.discovered()?.login;
    return login ? [login.wordpress, login.jmap, login.activitypub].filter((url): url is string => url !== null) : [];
  });

  protected async discover(event: SubmitEvent, address: string): Promise<void> {
    event.preventDefault();
    if (this.loading()) return;
    this.discovered.set(null);
    this.connected.set(null);
    this.error.set("");
    this.loading.set("discover");
    try {
      this.discovered.set(await invoke<Discovery>("discover_server", { input: address }));
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
      case "found": return label(probe.detail);
      case "login_required": return `Sign-in required · ${label(probe.detail)}`;
      case "not_found": return "Not advertised";
      case "unconfirmed": return "Could not confirm";
    }
  }

  protected readonly wordpressLabel = (info: {name: string}) => info.name || "Detected";
  protected readonly jmapLabel = (info: {session_url: string}) => info.session_url;
  protected readonly apLabel = (info: {software: string | null}) => info.software || "Advertised";

  protected apSessionStatus(probe: Discovery["discovery"]["wordpress"]): string {
    if (probe.status === "unconfirmed") return "Could not confirm";
    return typeof probe.detail !== "string" && probe.detail.ap_session_advertised
      ? "Advertised · sign-in required"
      : "Not advertised";
  }

  protected actorFor(account: Account, actors: Actor[] | null): Actor | undefined {
    return actors?.find(actor => actor.id === account.actorUri);
  }
}
