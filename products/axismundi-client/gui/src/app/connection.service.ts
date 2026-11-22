import { Service, computed, linkedSignal, signal } from "@angular/core";
import { invoke, isTauri } from "@tauri-apps/api/core";
import { defaultAccount } from "./default-account";

export type Probe<T> = {status: "found" | "login_required"; detail: T}
  | {status: "not_found" | "unconfirmed"; detail: string} | {status: "skipped"};

export interface Discovery {
  discovery: {
    base: string;
    wordpress: Probe<{name: string; ap_session_advertised: boolean}>;
    jmap: Probe<{session_url: string; origin_changed: boolean; srv: string | null}>;
    activitypub: Probe<{software: string | null}>;
  };
  login: {wordpress: string | null; jmap: string | null; activitypub: string | null};
  notices: string[];
}

export interface Account {
  id: string;
  name: string;
  isPersonal: boolean;
  isReadOnly: boolean;
  capabilities: string[];
}

export interface Actor {id: string}

export interface Connected {
  id: number;
  wordpress: string | null;
  wordpress_document: object | null;
  jmap: Account[] | null;
  /** Capability URI → the Account the server suggests for it. */
  jmap_primary_accounts: Record<string, string> | null;
  jmap_document: object | null;
  activitypub: Actor[] | null;
  activitypub_document: object | null;
  errors: string[];
}

export type DiscoveryOptions = {wordpress: boolean; jmap: boolean; activitypub: boolean};

export const MAIL = "urn:ietf:params:jmap:mail";

/**
 * The one connection the app holds, shared by the shell and every domain route.
 * JMAP Account and ActivityPub Actor selections are independent: the two sessions
 * share authentication only, and neither selection ever changes the other.
 */
@Service()
export class ConnectionService {
  readonly native = isTauri();
  readonly busy = signal<"discover" | "connect" | "disconnect" | null>(null);
  readonly error = signal("");
  readonly discovered = signal<Discovery | null>(null);
  readonly connected = signal<Connected | null>(null);

  readonly accounts = computed(() => this.connected()?.jmap ?? []);
  readonly actors = computed(() => this.connected()?.activitypub ?? []);

  /**
   * Resets when the connection changes. The server's primary Mail Account comes first, then a
   * personal one. Choosing another changes only this app's selection, never the server's Session.
   */
  readonly accountId = linkedSignal(() => {
    return defaultAccount(this.accounts(), this.connected()?.jmap_primary_accounts?.[MAIL]);
  });
  readonly actorId = linkedSignal(() => this.actors()[0]?.id ?? null);

  readonly account = computed(() => this.accounts().find(account => account.id === this.accountId()) ?? null);

  /** Whether any Account on the connection offers the capability, not only the selected one. */
  supports(capability: string): boolean {
    return this.accounts().some(account => account.capabilities.includes(capability));
  }

  readonly addresses = computed(() => {
    const login = this.discovered()?.login;
    return login ? [login.wordpress, login.jmap, login.activitypub].filter((url): url is string => url !== null) : [];
  });

  async discover(address: string, options: DiscoveryOptions): Promise<void> {
    if (this.busy()) return;
    this.discovered.set(null);
    this.connected.set(null);
    this.error.set("");
    this.busy.set("discover");
    try {
      this.discovered.set(await invoke<Discovery>("discover_server", {input: address, options}));
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.busy.set(null);
    }
  }

  async connect(username: string, password: string): Promise<void> {
    if (this.busy()) return;
    this.connected.set(null);
    this.error.set("");
    this.busy.set("connect");
    try {
      this.connected.set(await invoke<Connected>("connect_server", {approved: this.addresses(), username, password}));
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.busy.set(null);
    }
  }

  async disconnect(): Promise<void> {
    const connectionId = this.connected()?.id;
    if (connectionId === undefined || this.busy()) return;
    this.busy.set("disconnect");
    this.error.set("");
    try {
      await invoke("disconnect_server", {connectionId});
      this.connected.set(null);
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.busy.set(null);
    }
  }
}
