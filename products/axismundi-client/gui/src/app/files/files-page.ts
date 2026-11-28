import { Component, DestroyRef, TemplateRef, computed, effect, inject, signal, untracked, viewChild } from "@angular/core";
import { toSignal } from "@angular/core/rxjs-interop";
import { DatePipe } from "@angular/common";
import { ActivatedRoute, Router, RouterLink } from "@angular/router";
import { BreakpointObserver } from "@angular/cdk/layout";
import { map } from "rxjs";
import { invoke } from "@tauri-apps/api/core";
import { listen } from "@tauri-apps/api/event";
import { MatButton, MatFabButton, MatIconButton } from "@angular/material/button";
import { MatCard } from "@angular/material/card";
import { MatChip } from "@angular/material/chips";
import { MatDialog, MatDialogActions, MatDialogClose, MatDialogContent, MatDialogRef, MatDialogTitle } from "@angular/material/dialog";
import { MatFormField, MatLabel } from "@angular/material/form-field";
import { MatIcon } from "@angular/material/icon";
import { MatInput } from "@angular/material/input";
import { MatMenu, MatMenuContent, MatMenuItem, MatMenuTrigger } from "@angular/material/menu";
import { MatProgressBar } from "@angular/material/progress-bar";
import { MatSidenav, MatSidenavContainer, MatSidenavContent } from "@angular/material/sidenav";
import { MatSnackBar } from "@angular/material/snack-bar";
import { MatTableModule } from "@angular/material/table";
import { MatToolbar } from "@angular/material/toolbar";
import { MatTooltip } from "@angular/material/tooltip";
import { ConnectionService } from "../connection.service";
import { EXPANDED } from "../shell/scaffold";
import { UploadQueueComponent } from "../shell/upload-queue.component";
import { type QueueFile, type QueueItem, clearFinished, enqueue, settle } from "../shell/upload-queue";
import { FILENODE, FILENODE_REVISION, type FileNode, type Listing, type Quotas, type UploadResult, folderSort, formatBytes,
  isFolder, kindLabel, quotaLines, setFailure, uploadError, uploadOutcome } from "./files-model";

const PAGE = 200;

type Scope = {connectionId: number; accountId: string; readOnly: boolean};
const sameScope = (a: Scope | null, b: Scope | null) => a?.connectionId === b?.connectionId && a?.accountId === b?.accountId;

/**
 * Files over JMAP FileNode (docs/BRIEF-FILES-FIRST-SLICE.md). Experimental: the protocol is an
 * Internet-Draft, so the title says so; the screen shows what the server advertises and nothing more.
 * The open folder lives in the URL (`?folder=`); uploads go through the shared upload queue.
 */
@Component({
  imports: [DatePipe, RouterLink, MatMenuContent, MatButton, MatFabButton, MatIconButton, MatCard, MatChip, MatDialogActions, MatDialogClose, MatDialogContent,
    MatDialogTitle, MatFormField, MatLabel, MatIcon, MatInput, MatMenu, MatMenuItem, MatMenuTrigger, MatProgressBar, MatSidenav,
    MatSidenavContainer, MatSidenavContent, MatTableModule, MatToolbar, MatTooltip, UploadQueueComponent],
  templateUrl: "./files-page.html",
  styleUrl: "./files-page.css",
})
export class FilesPage {
  private readonly connection = inject(ConnectionService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly dialog = inject(MatDialog);
  private readonly snackBar = inject(MatSnackBar);
  protected readonly revision = FILENODE_REVISION;
  protected readonly expanded = toSignal(inject(BreakpointObserver).observe(EXPANDED).pipe(map(state => state.matches)), {requireSync: true});
  protected readonly drawer = signal(false);
  private readonly query = toSignal(this.route.queryParamMap, {requireSync: true});
  protected readonly folderId = computed(() => this.query().get("folder"));

  /** The connection and the Account that holds files: the FileNode primary, else the first that has it. */
  private readonly scope = computed<Scope | null>(() => {
    const connection = this.connection.connected();
    if (!connection) return null;
    const accounts = this.connection.accounts().filter(account => account.capabilities.includes(FILENODE));
    const account = accounts.find(item => item.id === connection.jmap_primary_accounts?.[FILENODE]) ?? accounts[0];
    return account ? {connectionId: connection.id, accountId: account.id, readOnly: account.isReadOnly} : null;
  }, {equal: sameScope});
  /** The sort this server can do; empty means its own order, which the table says. */
  protected readonly sort = computed(() => {
    const scope = this.scope();
    const session = this.connection.connected()?.jmap_document as {accounts?: Record<string, {accountCapabilities?: Record<string, {fileNodeQuerySortOptions?: string[]}>}>} | null;
    return folderSort(scope ? session?.accounts?.[scope.accountId]?.accountCapabilities?.[FILENODE]?.fileNodeQuerySortOptions ?? [] : []);
  });

  /** Writing needs an Account that is not read only; the server still decides per folder (myRights). */
  protected readonly folderAllowsUpload = computed(() => !!this.scope() && !this.scope()!.readOnly);
  protected readonly nodes = signal<FileNode[]>([]);
  protected readonly total = signal<number | null>(null);
  protected readonly path = signal<FileNode[]>([]);
  protected readonly loading = signal(false);
  protected readonly error = signal("");
  protected readonly quotas = signal<Quotas | null>(null);
  protected readonly storage = computed(() => quotaLines(this.quotas()));
  protected readonly queue = signal<QueueItem[]>([]);
  protected readonly columns = ["name", "size", "kind", "modified", "actions"];
  /**
   * Where each queued file goes, fixed when it is queued: a retry or a conflict choice made after the
   * user switched connection or Account still writes to the folder the file was dropped on.
   */
  private readonly queuedTo = new Map<string, {scope: Scope; parentId: string | null}>();
  /** The query state of the first page; a later page from another state is not appended to it. */
  private queryState: string | null = null;
  private generation = 0;
  private keys = 0;

  protected readonly nameDialog = viewChild.required<TemplateRef<unknown>>("nameDialog");
  protected readonly confirmDialog = viewChild.required<TemplateRef<unknown>>("confirmDialog");
  protected readonly nameValue = signal("");
  protected readonly dialogText = signal({title: "", action: "", note: ""});

  constructor() {
    effect(() => {
      const scope = this.scope(), folder = this.folderId();
      untracked(() => { void this.load(scope, folder); });
    });
    effect(() => {
      const scope = this.scope();
      untracked(() => { this.quotas.set(null); if (scope) void invoke<Quotas>("files_quotas", scope).then(q => this.quotas.set(q)); });
    });
    // The window's native drop registers files for upload; this screen takes them into the open folder.
    const unlisten = listen<QueueFile[]>("compose-files-dropped", event => this.add(event.payload));
    inject(DestroyRef).onDestroy(() => { void unlisten.then(stop => stop()); this.release(this.queue().map(item => item.file.handle)); });
  }

  private async load(scope: ReturnType<FilesPage["scope"]>, folder: string | null, append = false): Promise<void> {
    const generation = ++this.generation;
    if (!scope) { this.nodes.set([]); return; }
    this.loading.set(true);
    this.error.set("");
    try {
      const position = append ? this.nodes().length : 0;
      let listing = await invoke<Listing>("files_list", {...scope, parentId: folder, sort: this.sort(), position, limit: PAGE});
      if (generation !== this.generation) return;
      // RFC 8620 §5.5: positions only line up within one queryState. If the folder changed since the
      // first page, the next page could repeat or skip items, so read from the start instead.
      if (append && listing.queryState !== this.queryState) {
        append = false;
        listing = await invoke<Listing>("files_list", {...scope, parentId: folder, sort: this.sort(), position: 0, limit: PAGE});
        if (generation !== this.generation) return;
      }
      if (!append) this.queryState = listing.queryState;
      this.nodes.set(append ? [...this.nodes(), ...listing.nodes] : listing.nodes);
      this.total.set(listing.total);
      if (!append) this.path.set(folder ? await this.ancestors(scope, folder) : []);
    } catch (error) {
      if (generation === this.generation) { this.error.set(String(error)); if (!append) this.nodes.set([]); }
    } finally {
      if (generation === this.generation) this.loading.set(false);
    }
  }

  /** The breadcrumb from the root to the open folder, from one FileNode/get with fetchParents. */
  private async ancestors(scope: NonNullable<ReturnType<FilesPage["scope"]>>, folder: string): Promise<FileNode[]> {
    const answer = await invoke<{list: FileNode[]}>("files_get", {...scope, nodeId: folder, fetchParents: true});
    const byId = new Map(answer.list.map(node => [node.id, node]));
    const trail: FileNode[] = [];
    for (let node = byId.get(folder); node; node = node.parentId ? byId.get(node.parentId) : undefined) trail.unshift(node);
    return trail;
  }

  protected refresh(): void { void this.load(this.scope(), this.folderId()); }
  protected loadMore(): void { void this.load(this.scope(), this.folderId(), true); }
  protected open(node: FileNode): void {
    if (isFolder(node)) void this.router.navigate([], {relativeTo: this.route, queryParams: {folder: node.id}});
  }
  protected readonly isFolder = isFolder;
  protected readonly kindLabel = kindLabel;
  protected readonly formatBytes = formatBytes;

  // --- uploads -------------------------------------------------------------------------------------
  protected async pick(): Promise<void> {
    this.add(await invoke<QueueFile[]>("mail_attachment_pick"));
  }
  private add(files: QueueFile[]): void {
    if (!files.length || !this.scope() || this.scope()!.readOnly) { this.release(files.map(file => file.handle)); return; }
    const before = this.queue().length, scope = this.scope()!;
    this.queue.set(enqueue(this.queue(), files, () => `u${++this.keys}`));
    for (const item of this.queue().slice(before)) {
      this.queuedTo.set(item.key, {scope, parentId: this.folderId()});
      void this.upload(item.key);
    }
  }
  private async upload(key: string, onExists: "rename" | "replace" | null = null): Promise<void> {
    const item = this.queue().find(entry => entry.key === key), target = this.queuedTo.get(key);
    if (!item || !target) return;
    const {scope, parentId} = target;
    this.queue.set(settle(this.queue(), key, "uploading"));
    try {
      const result = await invoke<UploadResult>("files_upload", {...scope, handle: item.file.handle, parentId, onExists});
      const outcome = uploadOutcome(result);
      this.queue.set(settle(this.queue(), key, outcome.state, outcome.message));
      if (outcome.state === "done" || outcome.state === "unconfirmed") {
        this.release([item.file.handle]);
        if (sameScope(scope, this.scope()) && parentId === this.folderId()) this.refresh();
      }
    } catch (error) {
      this.queue.set(settle(this.queue(), key, "failed", uploadError(String(error))));
    }
  }
  protected retry(key: string): void { void this.upload(key); }
  protected resolve(event: {key: string; choice: "replace" | "rename"}): void { void this.upload(event.key, event.choice); }
  protected remove(key: string): void {
    const item = this.queue().find(entry => entry.key === key);
    if (item) this.release([item.file.handle]);
    this.queuedTo.delete(key);
    this.queue.set(this.queue().filter(entry => entry.key !== key));
  }
  protected clear(): void {
    this.queue.set(clearFinished(this.queue()));
    const kept = new Set(this.queue().map(item => item.key));
    for (const key of [...this.queuedTo.keys()]) if (!kept.has(key)) this.queuedTo.delete(key);
  }
  private release(handles: string[]): void {
    if (handles.length) void invoke("mail_attachment_release", {handles}).catch(() => undefined);
  }

  // --- folder and item actions ----------------------------------------------------------------------
  protected async newFolder(): Promise<void> {
    const name = await this.askName("New folder", "Create", "");
    if (name) await this.mutate({kind: "createFolder", parentId: this.folderId(), name}, "notCreated", "folder", "Folder created");
  }
  protected async rename(node: FileNode): Promise<void> {
    const name = await this.askName(`Rename ${isFolder(node) ? "folder" : "file"}`, "Rename", node.name);
    if (name && name !== node.name) await this.mutate({kind: "rename", nodeId: node.id, name}, "notUpdated", node.id, "Renamed");
  }
  protected async destroy(node: FileNode): Promise<void> {
    // No trash role on this server means deletion is permanent; the dialog says so.
    this.dialogText.set({title: `Delete “${node.name}”?`, action: "Delete permanently",
      note: isFolder(node) ? "The folder and everything in it will be deleted. This cannot be undone." : "This cannot be undone."});
    const ok = await this.dialog.open(this.confirmDialog(), {width: "420px"}).afterClosed().toPromise();
    if (ok) await this.mutate({kind: "destroy", nodeId: node.id, removeChildren: isFolder(node)}, "notDestroyed", node.id, "Deleted");
  }
  protected async download(node: FileNode): Promise<void> {
    const scope = this.scope();
    if (!scope) return;
    try {
      if (await invoke<boolean>("files_download", {...scope, nodeId: node.id})) this.snackBar.open(`Saved “${node.name}”`, undefined, {duration: 2500});
    } catch (error) { this.snackBar.open(`Download failed: ${error}`, "Dismiss", {duration: 6000}); }
  }

  private async askName(title: string, action: string, initial: string): Promise<string | null> {
    this.nameValue.set(initial);
    this.dialogText.set({title, action, note: ""});
    const ref: MatDialogRef<unknown, string> = this.dialog.open(this.nameDialog(), {width: "420px"});
    return (await ref.afterClosed().toPromise())?.trim() || null;
  }
  private async mutate(mutation: object, key: "notCreated" | "notUpdated" | "notDestroyed", id: string, done: string): Promise<void> {
    const scope = this.scope();
    if (!scope) return;
    try {
      const response = await invoke<Record<string, unknown>>("files_mutate", {...scope, mutation});
      const failure = setFailure(response, key, id);
      this.snackBar.open(failure ?? done, failure ? "Dismiss" : undefined, {duration: failure ? 6000 : 2500});
    } catch (error) {
      this.snackBar.open(String(error), "Dismiss", {duration: 6000});
    }
    this.refresh();
  }
}
