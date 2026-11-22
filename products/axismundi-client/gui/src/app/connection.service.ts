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
    /** Pre-FEP `/.well-known/activitypub`: the Session URL, same origin as the document. */
    activitypub_session: Probe<{session_url: string}>;
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
  /** Who signed in and where: how the connection is named in the GUI. */
  username: string;
  server: string;
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
 * The signed-in connections and the one the app shows, shared by the shell and every route.
 * Within a connection, JMAP Account and ActivityPub Actor selections are independent: the two
 * sessions share authentication only, and neither selection ever changes the other. Switching
 * connection resets both, and every request names its connection, so nothing crosses over.
 */
@Service()
export class ConnectionService {
  readonly native = isTauri();
  readonly busy = signal<"discover" | "connect" | "disconnect" | null>(null);
  readonly error = signal("");
  readonly discovered = signal<Discovery | null>(null);
  readonly connections = signal<Connected[]>([]);
  readonly activeId = signal<number | null>(null);
  /** The connection the app shows. */
  readonly connected = computed(() => this.connections().find(item => item.id === this.activeId()) ?? null);

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

  /** Discovery adds a connection; the ones already signed in stay. */
  async discover(address: string, options: DiscoveryOptions): Promise<void> {
    if (this.busy()) return;
    this.discovered.set(null);
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
    this.error.set("");
    this.busy.set("connect");
    try {
      const added = await invoke<Connected>("connect_server", {approved: this.addresses(), username, password});
      this.connections.update(list => [...list, added]);
      this.activeId.set(added.id);
      this.discovered.set(null);
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.busy.set(null);
    }
  }

  switchTo(connectionId: number): void {
    if (this.connections().some(item => item.id === connectionId)) this.activeId.set(connectionId);
  }

  /** Ends one connection; the app moves to another if one is left. */
  async disconnect(connectionId = this.activeId()): Promise<void> {
    if (connectionId === null || this.busy()) return;
    this.busy.set("disconnect");
    this.error.set("");
    try {
      await invoke("disconnect_server", {connectionId});
      const left = this.connections().filter(item => item.id !== connectionId);
      this.connections.set(left);
      if (this.activeId() === connectionId) this.activeId.set(left[0]?.id ?? null);
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.busy.set(null);
    }
  }

  /** Ends every connection. Caches stay on disk, and no password or token is revoked on a server. */
  async disconnectAll(): Promise<void> {
    if (this.busy()) return;
    this.busy.set("disconnect");
    this.error.set("");
    try {
      await invoke("disconnect_all");
      this.connections.set([]);
      this.activeId.set(null);
      this.discovered.set(null);
    } catch (error) {
      this.error.set(String(error));
    } finally {
      this.busy.set(null);
    }
  }
}
