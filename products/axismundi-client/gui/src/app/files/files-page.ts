import { Component, DestroyRef, TemplateRef, computed, effect, inject, signal, untracked, viewChild } from "@angular/core";
import { toSignal } from "@angular/core/rxjs-interop";
import { DatePipe, DecimalPipe } from "@angular/common";
import { ActivatedRoute, Router, RouterLink } from "@angular/router";
import { BreakpointObserver } from "@angular/cdk/layout";
import { map } from "rxjs";
import { invoke } from "@tauri-apps/api/core";
import { listen } from "@tauri-apps/api/event";
import { MatButton, MatFabButton, MatIconButton } from "@angular/material/button";
import { MatCard } from "@angular/material/card";
import { MatCheckbox } from "@angular/material/checkbox";
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
import { MatTree, MatTreeNode, MatTreeNodeDef, MatTreeNodeToggle } from "@angular/material/tree";
import { MatToolbar } from "@angular/material/toolbar";
import { MatTooltip } from "@angular/material/tooltip";
import { ConnectionService } from "../connection.service";
import { EXPANDED } from "../shell/scaffold";
import { UploadQueueComponent } from "../shell/upload-queue.component";
import { type QueueFile, type QueueItem, clearFinished, enqueue, settle } from "../shell/upload-queue";
import { FILENODE, FILENODE_REVISION, type FileNode, type FolderNode, type Listing, type Quotas, type UploadResult, ROOT, accessLabel, batchReport, endsFolder, folderTrail, moveBlocked, folderSort, fileKind, folderTree, formatBytes, type Usage, usageError, usageSummary, holdsAllFolders,
  isFolder, kindLabel, quotaLines, setFailure, uploadError, uploadOutcome } from "./files-model";

const PAGE = 200;
/** The most pages the tree reads from one folder before it stops and says so. */
const PAGES = 20;

type Scope = {connectionId: number; accountId: string; readOnly: boolean};
type UsageState = {summary: ReturnType<typeof usageSummary>} | {error: string} | "loading";
const sameScope = (a: Scope | null, b: Scope | null) => a?.connectionId === b?.connectionId && a?.accountId === b?.accountId;

/**
 * Files over JMAP FileNode (docs/BRIEF-FILES-FIRST-SLICE.md). Experimental: the protocol is an
 * Internet-Draft, so the title says so; the screen shows what the server advertises and nothing more.
 * The open folder lives in the URL (`?folder=`); uploads go through the shared upload queue.
 */
@Component({
  imports: [DatePipe, DecimalPipe, RouterLink, MatCheckbox, MatMenuContent, MatButton, MatFabButton, MatIconButton, MatCard, MatChip, MatDialogActions, MatDialogClose, MatDialogContent,
    MatDialogTitle, MatFormField, MatLabel, MatIcon, MatInput, MatMenu, MatMenuItem, MatMenuTrigger, MatProgressBar, MatSidenav,
    MatSidenavContainer, MatSidenavContent, MatTableModule, MatToolbar, MatTooltip, MatTree, MatTreeNode, MatTreeNodeDef, MatTreeNodeToggle, UploadQueueComponent],
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
  /** More items after the ones shown; known from a full page when the server does not count. */
  protected readonly more = signal(false);
  protected readonly path = signal<FileNode[]>([]);
  protected readonly loading = signal(false);
  protected readonly error = signal("");
  protected readonly quotas = signal<Quotas | null>(null);
  protected readonly storage = computed(() => quotaLines(this.quotas()));
  protected readonly queue = signal<QueueItem[]>([]);
  protected readonly columns = ["select", "name", "size", "kind", "modified", "actions"];

  // --- selection mode -----------------------------------------------------------------------------
  /**
   * The checked items, by id, in the open folder. Checking one turns the toolbar into the selection bar
   * (M3 contextual top app bar); a folder change, a new Account or a finished batch clears it, except
   * for the items a batch could not handle, which stay checked so the user sees which.
   */
  protected readonly checked = signal<ReadonlySet<string>>(new Set());
  protected readonly checkedNodes = computed(() => this.nodes().filter(node => this.checked().has(node.id)));
  protected readonly allChecked = computed(() => !!this.nodes().length && this.checkedNodes().length === this.nodes().length);
  protected readonly busy = signal(false);
  protected toggleChecked(node: FileNode): void {
    const next = new Set(this.checked());
    if (!next.delete(node.id)) next.add(node.id);
    this.checked.set(next);
  }
  protected toggleAll(): void {
    this.checked.set(this.allChecked() ? new Set() : new Set(this.nodes().map(node => node.id)));
  }
  protected clearChecked(): void { this.checked.set(new Set()); }

  /** The move dialog's chosen folder (ROOT for the top level) and the items it moves. */
  protected readonly moveTarget = signal<string | null>(null);
  protected readonly moving = signal<readonly FileNode[]>([]);
  protected readonly moveProblem = computed(() => {
    const target = this.moveTarget();
    return target === null ? null : moveBlocked(target, folderTrail(this.folderRoots()[0], target), this.moving(), this.folderId() ?? ROOT);
  });
  protected readonly moveDialog = viewChild.required<TemplateRef<unknown>>("moveDialog");
  private readonly moveTree = viewChild<MatTree<FolderNode, string>>("moveTree");

  // --- folder tree --------------------------------------------------------------------------------
  /** Each listed folder's items by parent id (ROOT for the top level); a folder is listed when expanded. */
  private readonly listed = signal<ReadonlyMap<string, readonly FileNode[]>>(new Map());
  /** Folders the tree stopped reading before their last subfolder (see listFolders). */
  private readonly partial = signal<ReadonlySet<string>>(new Set());
  private readonly listing = new Set<string>();
  protected readonly folderRoots = computed(() => [folderTree(this.listed(), this.partial())]);
  protected readonly folderChildren = (node: FolderNode) => node.children ?? [];
  /** Expansion is kept by connection, Account and folder id, so it survives every rebuild of the nodes. */
  protected readonly folderKey = (node: FolderNode) => JSON.stringify([this.scope()?.connectionId, this.scope()?.accountId, node.id]);
  protected readonly trackFolder = (_: number, node: FolderNode) => this.folderKey(node);
  /** Not listed yet means it may have subfolders, so it keeps its toggle until it is listed. */
  protected readonly expandable = (node: FolderNode) => node.children === null || node.children.length > 0;
  protected readonly activeFolder = computed(() => this.folderId() ?? ROOT);
  private readonly tree = viewChild<MatTree<FolderNode, string>>("tree");

  // --- details ------------------------------------------------------------------------------------
  /** The side sheet with the selected item's details; closed until asked for, like the filter sheet. */
  protected readonly detailsOpen = signal(false);
  protected readonly selectedId = signal<string | null>(null);
  protected readonly selected = computed(() => this.nodes().find(node => node.id === this.selectedId()) ?? null);
  /** With nothing selected the sheet describes the open folder; at the root, the root itself (null). */
  protected readonly detail = computed(() => this.selected() ?? this.path().at(-1) ?? null);
  /** The folders that hold the described item, root first. */
  protected readonly location = computed(() => {
    const trail = ["Files", ...this.path().map(node => node.name)];
    return this.selected() ? trail : trail.slice(0, -1);
  });
  /**
   * Where the selected file is used (RFC 9404 Blob/lookup through files_usage), asked for when its
   * details are open and kept by node and blobId until the next refresh: new content is a new blobId.
   */
  private readonly usages = signal<ReadonlyMap<string, UsageState>>(new Map());
  protected readonly usage = computed(() => {
    const node = this.selected();
    return node && !isFolder(node) && node.blobId ? this.usages().get(`${node.id} ${node.blobId}`) ?? null : null;
  });
  protected readonly access = computed(() => {
    const node = this.detail();
    return node ? accessLabel(isFolder(node) ? node.myRights : this.path().at(-1)?.myRights) : null;
  });
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
      untracked(() => { this.selectedId.set(null); this.checked.set(new Set()); void this.load(scope, folder); });
    });
    effect(() => {
      const scope = this.scope();
      untracked(() => { this.quotas.set(null); if (scope) void invoke<Quotas>("files_quotas", scope).then(q => this.quotas.set(q)); });
    });
    effect(() => {
      const node = this.selected(), open = this.detailsOpen(), scope = this.scope();
      if (!open || !scope || !node || isFolder(node) || !node.blobId) return;
      const key = `${node.id} ${node.blobId}`;
      untracked(() => {
        if (this.usages().has(key)) return;
        this.setUsage(key, "loading");
        void invoke<Usage>("files_usage", {...scope, nodeId: node.id}).then(
          usage => { if (sameScope(scope, this.scope())) this.setUsage(key, {summary: usageSummary(usage, node.id)}); },
          error => { if (sameScope(scope, this.scope())) this.setUsage(key, {error: usageError(String(error))}); });
      });
    });
    // A new connection or Account starts a new tree.
    effect(() => {
      const scope = this.scope();
      untracked(() => { this.usages.set(new Map()); this.listed.set(new Map()); this.partial.set(new Set()); this.listing.clear(); if (scope) void this.listFolders(ROOT, scope); });
    });
    // The open folder is never hidden inside a collapsed one: list and expand each folder above it.
    effect(() => {
      const tree = this.tree(), above = [ROOT, ...this.path().slice(0, -1).map(node => node.id)], roots = this.folderRoots();
      untracked(() => {
        const byId = new Map<string, FolderNode>();
        const walk = (nodes: FolderNode[]) => nodes.forEach(node => { byId.set(node.id, node); walk(node.children ?? []); });
        walk(roots);
        for (const id of above) {
          if (!this.listed().has(id)) void this.listFolders(id);
          const node = byId.get(id);
          if (tree && node && !tree.isExpanded(node)) tree.expand(node);
        }
      });
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
      const ended = endsFolder(listing);
      this.more.set(!ended);
      // The open folder's listing is also its branch of the tree, once it holds every subfolder.
      if (holdsAllFolders(this.nodes(), ended, this.sort())) this.setListed(folder ?? ROOT, this.nodes());
      if (!append) this.path.set(folder ? await this.ancestors(scope, folder) : []);
    } catch (error) {
      if (generation === this.generation) { this.error.set(String(error)); if (!append) this.nodes.set([]); }
    } finally {
      if (generation === this.generation) this.loading.set(false);
    }
  }

  /**
   * Lists a folder for the tree, page by page until every subfolder is in (see holdsAllFolders), for
   * at most PAGES pages. Stopping there is not the end of the folder: the branch then ends in a row
   * that opens the folder, rather than leaving the folders after it silently out.
   */
  protected async listFolders(id: string, scope = this.scope()): Promise<void> {
    if (!scope || this.listing.has(id)) return;
    this.listing.add(id);
    try {
      const items: FileNode[] = [];
      let queryState: string | null = null, restarts = 0, complete = false;
      for (let page = 0; page < PAGES; page++) {
        const listing = await invoke<Listing>("files_list", {...scope, parentId: id === ROOT ? null : id, sort: this.sort(),
          position: items.length, limit: PAGE});
        // Pages from another queryState do not line up (RFC 8620 §5.5): start over, a couple of times at most.
        if (queryState !== null && listing.queryState !== queryState) {
          if (++restarts > 2) return;
          items.length = 0; queryState = null; page = -1;
          continue;
        }
        queryState = listing.queryState;
        items.push(...listing.nodes);
        if (holdsAllFolders(items, endsFolder(listing), this.sort())) { complete = true; break; }
      }
      if (sameScope(scope, this.scope())) this.setListed(id, items, complete);
    } catch {
      // The branch stays unlisted and keeps its toggle, so expanding it again retries.
    } finally {
      this.listing.delete(id);
    }
  }
  private setUsage(key: string, value: UsageState): void {
    this.usages.set(new Map(this.usages()).set(key, value));
  }
  private setListed(id: string, items: readonly FileNode[], complete = true): void {
    const next = new Map(this.listed());
    next.set(id, items.filter(isFolder));
    this.listed.set(next);
    const partial = new Set(this.partial());
    if (complete) partial.delete(id); else partial.add(id);
    this.partial.set(partial);
  }
  protected openFolder(id: string): void {
    this.drawer.set(false);
    void this.router.navigate([], {relativeTo: this.route, queryParams: id === ROOT ? {} : {folder: id}});
  }

  /** The breadcrumb from the root to the open folder, from one FileNode/get with fetchParents. */
  private async ancestors(scope: NonNullable<ReturnType<FilesPage["scope"]>>, folder: string): Promise<FileNode[]> {
    const answer = await invoke<{list: FileNode[]}>("files_get", {...scope, nodeId: folder, fetchParents: true});
    const byId = new Map(answer.list.map(node => [node.id, node]));
    const trail: FileNode[] = [];
    for (let node = byId.get(folder); node; node = node.parentId ? byId.get(node.parentId) : undefined) trail.unshift(node);
    return trail;
  }

  protected refresh(): void { this.usages.set(new Map()); void this.load(this.scope(), this.folderId()); }
  protected loadMore(): void { void this.load(this.scope(), this.folderId(), true); }
  protected open(node: FileNode): void {
    if (isFolder(node)) void this.router.navigate([], {relativeTo: this.route, queryParams: {folder: node.id}});
  }
  protected select(node: FileNode): void { this.selectedId.set(node.id); }
  protected details(node: FileNode): void { this.selectedId.set(node.id); this.detailsOpen.set(true); }
  protected readonly isFolder = isFolder;
  protected readonly kindLabel = kindLabel;
  protected readonly fileKind = fileKind;
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
  /** Moves the items into a folder picked from the tree; each item is its own request, so each can fail alone. */
  protected async move(nodes: readonly FileNode[]): Promise<void> {
    if (!nodes.length) return;
    this.moving.set(nodes);
    this.moveTarget.set(null);
    void this.listFolders(ROOT);
    const ref = this.dialog.open(this.moveDialog(), {width: "420px", autoFocus: "dialog"});
    // Open the picker where the items are: the top level and every folder down to the open one, expanded.
    ref.afterOpened().subscribe(() => this.expandTo(this.moveTree(), [ROOT, ...this.path().map(node => node.id)]));
    const target = await ref.afterClosed().toPromise() as string | undefined;
    if (target === undefined || this.moveBlocked(target)) return;
    await this.batch("Moved", nodes, node => ({kind: "move", nodeId: node.id, parentId: target === ROOT ? null : target}), "notUpdated");
    // The destination's branch of the tree is out of date now (the source is relisted by the refresh).
    if (this.listed().has(target)) void this.listFolders(target);
  }
  /** Expands these folders in a tree, listing any that have not been listed yet. */
  private expandTo(tree: MatTree<FolderNode, string> | undefined, ids: readonly string[]): void {
    const byId = new Map<string, FolderNode>();
    const walk = (nodes: FolderNode[]) => nodes.forEach(node => { byId.set(node.id, node); walk(node.children ?? []); });
    walk(this.folderRoots());
    for (const id of ids) {
      if (!this.listed().has(id)) void this.listFolders(id);
      const node = byId.get(id);
      if (tree && node && !tree.isExpanded(node)) tree.expand(node);
    }
  }
  private moveBlocked(target: string): boolean {
    return !!moveBlocked(target, folderTrail(this.folderRoots()[0], target), this.moving(), this.folderId() ?? ROOT);
  }
  protected async destroyChecked(): Promise<void> {
    const nodes = this.checkedNodes();
    if (!nodes.length) return;
    if (nodes.length === 1) return this.destroy(nodes[0]);
    const folders = nodes.filter(isFolder).length;
    this.dialogText.set({title: `Delete ${nodes.length} items?`, action: "Delete permanently",
      note: (folders ? `${folders === 1 ? "The folder" : `The ${folders} folders`} and everything in ${folders === 1 ? "it" : "them"} will be deleted too. ` : "")
        + "This cannot be undone."});
    const ok = await this.dialog.open(this.confirmDialog(), {width: "420px"}).afterClosed().toPromise();
    if (ok) await this.batch("Deleted", nodes, node => ({kind: "destroy", nodeId: node.id, removeChildren: isFolder(node)}), "notDestroyed");
  }
  /** Runs one mutation per item, one at a time, then reports once and keeps only the failed items checked. */
  private async batch(verb: string, nodes: readonly FileNode[], mutation: (node: FileNode) => object,
    key: "notUpdated" | "notDestroyed"): Promise<void> {
    const scope = this.scope();
    if (!scope) return;
    this.busy.set(true);
    const failed = new Set<string>(), reasons: string[] = [];
    for (const node of nodes) {
      try {
        const response = await invoke<Record<string, unknown>>("files_mutate", {...scope, mutation: mutation(node)});
        const failure = setFailure(response, key, node.id);
        if (failure) { failed.add(node.id); reasons.push(failure); }
      } catch (error) {
        failed.add(node.id); reasons.push(String(error));
      }
    }
    this.busy.set(false);
    if (sameScope(scope, this.scope())) this.checked.set(failed);
    this.snackBar.open(batchReport(verb, nodes.length, reasons), reasons.length ? "Dismiss" : undefined, {duration: reasons.length ? 8000 : 2500});
    this.refresh();
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
