import { Component, DestroyRef, ElementRef, afterRenderEffect, computed, effect, inject, input, linkedSignal, resource, signal, untracked, viewChild, viewChildren } from "@angular/core";
import { toSignal } from "@angular/core/rxjs-interop";
import { BreakpointObserver } from "@angular/cdk/layout";
import { CdkContextMenuTrigger, CdkMenu, CdkMenuItem } from "@angular/cdk/menu";
import { MatButton, MatIconButton, MatIconAnchor } from "@angular/material/button";
import { MatIcon } from "@angular/material/icon";
import { MatMenu, MatMenuItem, MatMenuTrigger } from "@angular/material/menu";
import { MatBadge } from "@angular/material/badge";
import { MatCheckbox } from "@angular/material/checkbox";
import { MatDialog } from "@angular/material/dialog";
import { MatProgressSpinner } from "@angular/material/progress-spinner";
import { MatSnackBar } from "@angular/material/snack-bar";
import { MatSidenav, MatSidenavContainer, MatSidenavContent } from "@angular/material/sidenav";
import { MatTooltip } from "@angular/material/tooltip";
import { MatTree, MatTreeNode, MatTreeNodeDef, MatTreeNodeToggle } from "@angular/material/tree";
import { Router, RouterLink } from "@angular/router";
import { map } from "rxjs";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";
import { MailDetailComponent } from "./mail-detail.component";
import { MailActions, type Aspect, type Keyword } from "./mail-actions";
import { MailMoveMenuComponent } from "./mail-move-menu.component";
import { mailboxIcon, moveOptions } from "./mail-moves";
import { type MailboxNode, ancestorsOf, mailboxTree } from "./mail-tree";
import { ListView, type MailboxRow, rowLabel } from "./mail-rows";
import type { WriteResult } from "./mail-updates";
import { indicatorDelay, isRefreshShortcut, manualRefreshAction, pullProgress, reloadRevision } from "./mail-refresh";
import { MailboxDialogComponent, type MailboxDialogData } from "./mailbox-dialog.component";
import { allowsMailboxAction, mailboxParents, parentAllowed, type MailboxChange, type MailboxResult } from "./mailbox-management";
import { acceptsPage, canRequestPage, reachedPageEnd } from "./mail-paging";

/**
 * M3 window size classes (Material 3, Layout › Breakpoints). CDK's `Breakpoints` are the older
 * 600/960/1280/1920 bands, so the queries are given here.
 */
const LARGE = "(min-width: 1200px)";
const EXPANDED = "(min-width: 840px)";

/**
 * Mailboxes, one window of messages and the reading pane for the Account selected in the app bar.
 * The window size class sets how many panes show (docs/DESIGN-MAIL-GUI.md); inside the list, row
 * layout follows the list's own width through a container query. The selection lives in the URL
 * (`/mail?mailbox=…&email=…`), so one route keeps the list loaded while messages open.
 */
@Component({
  selector: "app-mail-page",
  imports: [MailDetailComponent, MailMoveMenuComponent, MatBadge, MatIcon, MatIconAnchor, MatIconButton, MatSidenav,
    MatSidenavContainer, MatSidenavContent, MatTooltip, MatCheckbox, MatMenu, MatMenuItem, MatMenuTrigger, MatTree, MatTreeNode,
    MatTreeNodeDef, MatTreeNodeToggle, RouterLink, MatButton, MatProgressSpinner, CdkContextMenuTrigger, CdkMenu, CdkMenuItem],
  host: {"(window:keydown)": "refreshShortcut($event)"},
  templateUrl: "./mail-page.component.html",
  styleUrl: "./mail-page.component.css",
})
export class MailPageComponent {
  protected readonly connection = inject(ConnectionService);
  protected readonly actions = inject(MailActions);
  private readonly router = inject(Router);
  private readonly snackBar = inject(MatSnackBar);
  private readonly dialogs = inject(MatDialog);
  private readonly mailboxRows = viewChildren("mailboxRow", {read: ElementRef<HTMLElement>});
  private readonly drawerToggle = viewChild("drawerToggle", {read: ElementRef<HTMLButtonElement>});
  private readonly mailboxNew = viewChild("mailboxNew", {read: ElementRef<HTMLButtonElement>});
  private readonly mailboxFocus = signal<string | null>(null);
  private mailboxPress: {id: number; x: number; y: number; timer: ReturnType<typeof setTimeout>} | null = null;
  private mailboxPressOpened = false;

  /** Query parameters, bound by the router. */
  readonly mailbox = input<string>();
  readonly email = input<string>();

  private readonly window = toSignal(
    inject(BreakpointObserver).observe([LARGE, EXPANDED]).pipe(map(state => state.breakpoints)),
    {requireSync: true},
  );
  /** ≥1200: mailboxes, list and detail side by side. */
  protected readonly large = computed(() => this.window()[LARGE]);
  /** ≥840: list beside detail. Below that, one pane at a time. */
  protected readonly twoPanes = computed(() => this.window()[EXPANDED]);
  protected readonly drawerOpen = signal(false);

  protected readonly view = resource({
    params: () => {
      const connectionId = this.connection.connected()?.id;
      const accountId = this.connection.accountId();
      return connectionId === undefined || !accountId
        ? undefined
        : {connectionId, accountId, mailboxId: this.mailbox() ?? null, rows: "messages"};
    },
    loader: async ({params}) => {
      const from = this.actions.mark();
      const view = await invoke<ListView>("mail_sync", params);
      this.loadedFrom.set(from);
      return view;
    },
  });
  protected readonly listing = linkedSignal<{scope: string; value: ListView | null}, ListView | null>({
    source: () => ({scope: this.scope(), value: this.view.hasValue() ? this.view.value() : null}),
    computation: (next, previous) =>
      next.value ?? (previous?.source.scope === next.scope ? previous.value : null),
  });
  private readonly listRoot = viewChild("messageList", {read: ElementRef<HTMLElement>});
  private readonly pageSentinel = viewChild("pageSentinel", {read: ElementRef<HTMLElement>});
  private readonly page = linkedSignal<string, object | null>({source: () => this.scope(), computation: () => null});
  protected readonly pageSpinner = linkedSignal<string, boolean>({source: () => this.scope(), computation: () => false});
  protected readonly pageFailed = linkedSignal<string, boolean>({source: () => this.scope(), computation: () => false});
  private readonly exhaustedListing = linkedSignal<string, ListView | null>({source: () => this.scope(), computation: () => null});
  private readonly queuedRefresh = linkedSignal<string, boolean>({source: () => this.scope(), computation: () => false});
  private alive = true;
  protected readonly canPage = computed(() => {
    const current = this.listing();
    return !!current && this.view.hasValue() &&
      canRequestPage(current, !!this.page() || this.view.isLoading() || this.refreshing(), this.exhaustedListing() === current);
  });

  protected async loadNextPage(retry = false): Promise<void> {
    if (!this.canPage() || (this.pageFailed() && !retry)) return;
    const current = this.listing();
    const connectionId = this.connection.connected()?.id;
    if (!current || connectionId === undefined) return;
    const scope = this.scope();
    const request = {};
    const applicable = () => this.alive && acceptsPage(this.page(), request, this.scope(), scope);
    this.page.set(request);
    this.pageFailed.set(false);
    let shownAt: number | null = null;
    const timer = setTimeout(() => {
      if (!applicable()) return;
      shownAt = performance.now();
      this.pageSpinner.set(true);
    }, indicatorDelay(false, 0));
    let failed = false;
    try {
      const next = await invoke<ListView>("mail_more", {
        connectionId, accountId: current.accountId, mailboxId: current.mailboxId, rows: "messages",
      });
      if (!applicable()) return;
      if (next.accountId !== current.accountId || next.mailboxId !== current.mailboxId) {
        failed = true;
      } else {
        // The cache returned the entire extended prefix. Reuse the resource so the next
        // sync and selection pruning both see the same authoritative listing.
        this.view.set(next);
        this.exhaustedListing.set(reachedPageEnd(current.rows.length, next) ? next : null);
        failed = next.hasMore === true && next.rows.length <= current.rows.length;
      }
    } catch {
      failed = true;
    } finally {
      clearTimeout(timer);
      if (applicable()) {
        if (shownAt !== null) {
          const remaining = indicatorDelay(true, performance.now() - shownAt);
          if (remaining) await new Promise<void>(resolve => setTimeout(resolve, remaining));
        }
        if (applicable()) {
          this.pageSpinner.set(false);
          this.pageFailed.set(failed);
          this.page.set(null);
          if (this.queuedRefresh()) {
            this.queuedRefresh.set(false);
            this.refresh();
          }
        }
      }
    }
  }
  private readonly manual = signal<{scope: string; started: number} | null>(null);
  private readonly shownAt = signal<number | null>(null);
  protected readonly refreshing = computed(() => this.manual()?.scope === this.scope());
  protected readonly refreshVisible = computed(() => this.refreshing() && this.shownAt() !== null);
  protected readonly initialLoading = computed(() => this.view.isLoading() && !this.listing() && !this.refreshing());
  private readonly initialShown = signal<{scope: string; at: number} | null>(null);
  protected readonly initialVisible = computed(() => this.initialShown()?.scope === this.scope() && !this.refreshing());
  protected readonly pull = signal(0);
  private touch: {id: number; x: number; y: number; scope: string} | null = null;

  protected refresh(): void {
    if (!this.connection.account()) return;
    if (this.page()) {
      this.queuedRefresh.set(true);
      return;
    }
    const action = manualRefreshAction(this.refreshing(), this.view.isLoading());
    if (action === "skip" || (action === "reload" && !this.view.reload())) return;
    this.shownAt.set(null);
    this.pageFailed.set(false);
    this.manual.set({scope: this.scope(), started: performance.now()});
  }

  protected refreshShortcut(event: KeyboardEvent): void {
    if (!isRefreshShortcut(event)) return;
    event.preventDefault();
    if (!event.repeat) this.refresh();
  }

  protected pullStart(event: TouchEvent): void {
    this.pullEnd();
    const list = event.currentTarget as HTMLElement;
    if (event.touches.length !== 1 || list.scrollTop > 0 || !this.listing() || this.view.isLoading() || this.refreshing()) return;
    const touch = event.touches[0];
    this.touch = {id: touch.identifier, x: touch.clientX, y: touch.clientY, scope: this.scope()};
  }

  protected pullMove(event: TouchEvent): void {
    const start = this.touch;
    if (!start) return;
    const touch = event.touches[0];
    if (event.touches.length !== 1 || touch.identifier !== start.id || start.scope !== this.scope() ||
        (event.currentTarget as HTMLElement).scrollTop > 0 || !event.cancelable) {
      this.pullEnd();
      return;
    }
    const dx = touch.clientX - start.x;
    const dy = touch.clientY - start.y;
    const progress = pullProgress(dx, dy);
    if (progress > 0) event.preventDefault();
    else if (Math.max(Math.abs(dx), Math.abs(dy)) > 8) this.pullEnd();
    this.pull.set(progress);
  }

  protected pullEnd(commit = false): void {
    const trigger = commit && this.touch?.scope === this.scope() && this.pull() === 1;
    this.touch = null;
    this.pull.set(0);
    if (trigger) this.refresh();
  }
  /** When the rows on screen started loading; values the server confirmed after that show instead. */
  private readonly loadedFrom = signal(0);

  protected starred(accountId: string, id: string, viewFlagged: boolean | null): boolean {
    return this.actions.shown(accountId, id, "flagged", viewFlagged, this.loadedFrom()) === true;
  }

  protected unread(accountId: string, id: string, viewUnread: boolean): boolean {
    return this.actions.shown(accountId, id, "seen", !viewUnread, this.loadedFrom()) === false;
  }

  /**
   * The Account's mailboxes. Opening another mailbox gives the listing new params, and while that
   * loads it has no value; the list is held over from the last load of the same Account meanwhile,
   * so the tree keeps its rows (and the keyboard focus on one of them) instead of emptying and
   * rebuilding. Another Account or connection starts empty.
   */
  protected readonly mailboxes = linkedSignal<{scope: string; boxes: MailboxRow[] | null}, MailboxRow[]>({
    source: () => ({
      scope: JSON.stringify([this.connection.connected()?.id, this.connection.accountId()]),
      boxes: this.view.hasValue() ? this.view.value().mailboxes : null,
    }),
    computation: (next, previous) =>
      next.boxes ?? (previous && previous.source.scope === next.scope ? previous.value : []),
  });
  /** The mailboxes nested by parentId (see `mailboxTree`). */
  protected readonly mailboxNodes = computed(() => mailboxTree(this.mailboxes()));
  protected readonly parentOptions = computed(() => mailboxParents(this.mailboxNodes()));
  protected readonly topLevelAllowed = computed(() => allowsMailboxAction(this.listing()?.mayCreateTopLevelMailbox));
  protected readonly canCreateMailbox = computed(() => {
    if (!this.listing()) return false;
    const max = this.listing()?.maxMailboxDepth ?? null;
    const top = this.topLevelAllowed();
    return parentAllowed(undefined, top, max) || this.parentOptions().some(parent => parentAllowed(parent, top, max));
  });

  protected mailboxDialog(kind: MailboxChange["kind"], target?: MailboxRow, parent?: string): void {
    const connectionId = this.connection.connected()?.id;
    const accountId = this.connection.accountId();
    if (connectionId === undefined || !accountId) return;
    const active = () => this.connection.connected()?.id === connectionId && this.connection.accountId() === accountId;
    const listing = this.listing();
    const origin = this.scope();
    this.dialogs.open<MailboxDialogComponent, MailboxDialogData, MailboxResult>(
      MailboxDialogComponent, {
        width: "440px",
        maxWidth: "calc(100vw - 32px)",
        autoFocus: kind === "destroy" ? "first-tabbable" : "input",
        data: {
          kind, target, parent, parents: this.parentOptions(), topLevel: this.topLevelAllowed(),
          usedRoles: new Set(this.mailboxes().flatMap(box => box.role ? [box.role] : [])),
          maxDepth: listing?.maxMailboxDepth ?? null, maxName: listing?.maxSizeMailboxName ?? null,
          write: change => active()
            ? invoke<MailboxResult>("mail_write_mailbox", {connectionId, accountId, change})
            : Promise.reject(),
        },
      },
    ).afterClosed().subscribe(result => {
      if (!active()) return;
      if (target && this.scope() === origin) this.mailboxFocus.set(target.id);
      if (!result) return;
      if (result.status === "unknown") {
        this.snackBar.open("Mailbox change could not be verified. Check the list before trying again.", "Dismiss", {duration: 6500});
      }
      if (result.status === "confirmed" && this.scope() === origin) {
        if (kind === "create") {
          this.mailboxFocus.set(result.id);
          this.openMailbox(result.id);
        }
        if (kind === "destroy") {
          const survivors = this.mailboxes().filter(box => box.id !== result.id);
          const inbox = survivors.find(box => box.role === "inbox");
          const destination = this.selectedMailbox() === result.id ? (inbox ?? survivors[0])?.id : this.selectedMailbox();
          this.mailboxFocus.set(destination ?? "");
          if (this.selectedMailbox() === result.id) {
            void this.router.navigate(["/mail"], {queryParams: {mailbox: inbox ? null : destination}});
          }
        }
      }
      this.actions.changed.update(count => count + 1);
    });
  }

  protected mailboxContextKey(event: KeyboardEvent, row: HTMLElement): void {
    if (!(event.key === "ContextMenu" || (event.shiftKey && event.key === "F10"))) return;
    event.preventDefault();
    event.stopPropagation();
    const rect = row.getBoundingClientRect();
    this.openMailboxContext(row, rect.left + 16, rect.bottom);
  }

  private openMailboxContext(row: HTMLElement, clientX: number, clientY: number): void {
    row.dispatchEvent(new MouseEvent("contextmenu", {bubbles: true, cancelable: true, clientX, clientY}));
  }

  protected mailboxPressStart(event: PointerEvent, row: HTMLElement): void {
    this.mailboxPressEnd();
    this.mailboxPressOpened = false;
    if (event.pointerType !== "touch" || !event.isPrimary) return;
    const {pointerId: id, clientX: x, clientY: y} = event;
    const timer = setTimeout(() => {
      this.mailboxPress = null;
      if (!row.isConnected) return;
      this.mailboxPressOpened = true;
      this.openMailboxContext(row, x, y);
    }, 500);
    this.mailboxPress = {id, x, y, timer};
  }

  protected mailboxPressMove(event: PointerEvent): void {
    const press = this.mailboxPress;
    if (press && event.pointerId === press.id && Math.hypot(event.clientX - press.x, event.clientY - press.y) > 8) {
      this.mailboxPressEnd();
    }
  }

  protected mailboxPressEnd(): void {
    if (this.mailboxPress) clearTimeout(this.mailboxPress.timer);
    this.mailboxPress = null;
  }

  protected mailboxRowClick(event: MouseEvent, id: string): void {
    if (this.mailboxPressOpened) {
      this.mailboxPressOpened = false;
      event.preventDefault();
      return;
    }
    this.openMailbox(id);
  }

  protected restoreMailboxMenuFocus(row: HTMLElement): void {
    queueMicrotask(() => {
      if (row.isConnected && !this.dialogs.openDialogs.length &&
          (document.activeElement === document.body || row.matches(":focus-within"))) row.focus();
    });
  }
  protected readonly childrenOf = (node: MailboxNode) => node.children;
  /**
   * Expansion is kept by connection, Account and mailbox id, so it survives every reload that rebuilds
   * the nodes — and a mailbox id that repeats in another Account or login is not opened by it.
   */
  protected readonly keyOf = (node: MailboxNode) =>
    JSON.stringify([this.connection.connected()?.id, this.connection.accountId(), node.box.id]);
  /**
   * Rows are kept by mailbox id too: every listing reload rebuilds the nodes, and without this the
   * tree would recreate each row and drop keyboard focus from the one the user is on.
   */
  protected readonly trackById = (_: number, node: MailboxNode) => this.keyOf(node);
  protected readonly icon = mailboxIcon;
  /** M3 large badge: at most four characters, so counts above 999 read "999+". */
  protected badgeLabel(count: number | null): string {
    return !count ? "" : count > 999 ? "999+" : String(count);
  }
  private readonly treeRef = viewChild<MatTree<MailboxNode, string>>("tree");

  protected openMailbox(id: string): void {
    this.drawerOpen.set(false);
    // The open mailbox again: stay put (no reload, selection kept) — unless a message is open, which
    // goes back to the list as Gmail does.
    if ((id === this.selectedMailbox() || id === this.mailbox()) && !this.email()) return;
    void this.router.navigate(["/mail"], {queryParams: {mailbox: id}});
  }
  protected readonly selectedMailbox = computed(() => this.listing()?.mailboxId ?? null);
  protected readonly mailboxName = computed(
    () => this.mailboxes().find(box => box.id === this.selectedMailbox())?.name ?? "Mail");
  /**
   * The listing's rows, less any message the server confirmed has left this mailbox since the rows
   * loaded: a confirmed move shows at once, and the reload it triggers then takes over.
   */
  protected readonly rows = computed(() => {
    const listing = this.listing();
    if (!listing) return [];
    const {accountId, mailboxId, rows} = listing;
    return rows
      .filter(row => row.kind !== "message" || this.actions.shownIn(accountId, row.id, mailboxId, true, this.loadedFrom()))
      .map(rowLabel);
  });
  /** Move to Trash and Move to… as this listing offers them (see `moveOptions`). */
  protected readonly moves = computed(() => moveOptions(this.mailboxes(), this.selectedMailbox()));
  /**
   * Selected message ids. Selection is local to this list: it empties when the connection, Account
   * or mailbox changes, and an id whose row is gone from the listing drops out. It never decides
   * whether a write is allowed — MailActions does (an unknown outcome stays blocked whatever the
   * selection does).
   */
  protected readonly selected = signal<ReadonlySet<string>>(new Set());
  protected readonly eligible = computed(() => this.rows().filter(row => row.kind === "message").map(row => row.id));
  protected readonly bulkBusy = signal(false);
  /** Selection mode: checkboxes and the bulk toolbar show only while it is on. */
  protected readonly selecting = signal(false);
  /** Not-applied ids from the last bulk action, shown on their rows until selection or the next action. */
  private readonly refused = signal<ReadonlySet<string>>(new Set());
  protected readonly bulkCommands = [
    {keyword: "seen", on: true, icon: "mark_email_read", filled: false, label: "Mark as read", check: "Check read status"},
    {keyword: "seen", on: false, icon: "mark_email_unread", filled: false, label: "Mark as unread", check: "Check read status"},
    {keyword: "flagged", on: true, icon: "star", filled: true, label: "Star", check: "Check star status"},
    {keyword: "flagged", on: false, icon: "star", filled: false, label: "Remove star", check: "Check star status"},
  ] as const;

  protected select(id: string): void {
    if (this.bulkBusy()) return;
    const next = new Set(this.selected());
    if (!next.delete(id)) next.add(id);
    this.selected.set(next);
    this.refused.set(new Set());
  }

  protected endSelection(): void {
    if (this.bulkBusy()) return;
    this.selecting.set(false);
    this.selected.set(new Set());
    this.refused.set(new Set());
  }

  protected selectAll(checked: boolean): void {
    if (this.bulkBusy()) return;
    this.selected.set(checked ? new Set(this.eligible()) : new Set());
    this.refused.set(new Set());
  }

  protected refusal(id: string): string | undefined {
    return this.refused().has(id) ? "The server did not apply the last change." : undefined;
  }

  /** Whether any selected message is blocked for this aspect: the button then checks instead. */
  protected checkSelection(accountId: string, aspect: Aspect): boolean {
    return [...this.selected()].some(id => this.actions.unverified(accountId, id, aspect));
  }

  protected applySelection(accountId: string, keyword: Keyword, on: boolean): Promise<void> {
    return this.forSelection(ids => this.actions.setKeywords(accountId, ids, keyword, on), false);
  }

  protected moveSelection(accountId: string, to: MailboxRow | null): Promise<void> {
    const from = this.selectedMailbox();
    if (!to || !from) return Promise.resolve();
    return this.forSelection(ids => this.actions.move(accountId, ids, from, to), true);
  }

  /** One message from its row: out of the mailbox this list shows. */
  protected async moveRow(accountId: string, id: string, to: MailboxRow | null): Promise<void> {
    const from = this.selectedMailbox();
    if (!to || !from) return;
    const scope = this.scope();
    const result = await this.actions.move(accountId, [id], from, to);
    if (this.scope() === scope) this.leaveIfMoved(result);
  }

  /**
   * Which connection, Account and mailbox the screen shows. An answer that arrives after it changed
   * belongs to a screen that is gone: it must not close the reading pane or touch the selection here.
   */
  private scope(): string {
    return JSON.stringify([this.connection.connected()?.id, this.connection.accountId(), this.mailbox() ?? null]);
  }

  /** The open message moved out of this listing: back to the list, as Gmail does. Moves only. */
  private leaveIfMoved(result: WriteResult | null): void {
    const open = this.email();
    if (open && result?.settled?.confirmed.includes(open)) {
      void this.router.navigate(["/mail"], {queryParams: {mailbox: this.mailbox() ?? null}});
    }
  }

  /**
   * Sends one action for every selected message, in list order. Blocked ones are held back and
   * checked by MailActions, not here. Confirmed ids leave the selection; everything else stays.
   */
  private async forSelection(run: (ids: readonly string[]) => Promise<WriteResult | null>, moves: boolean): Promise<void> {
    if (this.bulkBusy()) return;
    const ids = this.eligible().filter(id => this.selected().has(id));
    if (!ids.length) return;
    const scope = this.scope();
    this.bulkBusy.set(true);
    this.refused.set(new Set());
    try {
      const result = await run(ids);
      // The selection was emptied when the scope changed; this answer is not about the new one.
      if (!result || this.scope() !== scope) return;
      if (moves) this.leaveIfMoved(result);
      const confirmed = new Set(result.settled?.confirmed ?? []);
      this.selected.update(set => new Set([...set].filter(id => !confirmed.has(id))));
      this.refused.set(new Set(result.settled?.notApplied ?? []));
    } finally {
      this.bulkBusy.set(false);
    }
  }

  private readonly time = new Intl.DateTimeFormat(undefined, {timeStyle: "short"});
  private readonly day = new Intl.DateTimeFormat(undefined, {month: "short", day: "numeric"});
  /** Today's mail shows its time, older mail its date. */
  protected listDate(iso: string): string {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return iso;
    return date.toDateString() === new Date().toDateString() ? this.time.format(date) : this.day.format(date);
  }

  constructor() {
    inject(DestroyRef).onDestroy(() => this.mailboxPressEnd());
    inject(DestroyRef).onDestroy(() => { this.alive = false; });
    afterRenderEffect(onCleanup => {
      const root = this.listRoot()?.nativeElement;
      const sentinel = this.pageSentinel()?.nativeElement;
      if (!root || !sentinel || !this.canPage() || this.pageFailed()) return;
      const observer = new IntersectionObserver(entries => {
        if (sentinel.isConnected && entries.some(entry => entry.isIntersecting)) void this.loadNextPage();
      }, {root, rootMargin: `0px 0px ${root.clientHeight}px 0px`});
      observer.observe(sentinel);
      onCleanup(() => observer.disconnect());
    });
    afterRenderEffect({write: () => {
      const id = this.mailboxFocus();
      if (id === null) return;
      const row = this.mailboxRows().find(ref => ref.nativeElement.dataset["mailboxId"] === id)?.nativeElement;
      const target = this.large() || this.drawerOpen()
        ? id ? row : this.mailboxNew()?.nativeElement
        : this.drawerToggle()?.nativeElement;
      if (!target) return;
      target.focus();
      this.mailboxFocus.set(null);
    }});
    effect(onCleanup => {
      const scope = this.scope();
      const loading = this.initialLoading();
      const shown = this.initialShown();
      const current = shown?.scope === scope ? shown : null;
      if (loading === !!current) return;
      const timer = setTimeout(() => {
        if (this.scope() !== scope || this.initialLoading() !== loading) return;
        this.initialShown.set(loading ? {scope, at: performance.now()} : null);
      }, current ? indicatorDelay(true, performance.now() - current.at) : indicatorDelay(false, 0));
      onCleanup(() => clearTimeout(timer));
    });
    effect(onCleanup => {
      const request = this.manual();
      if (!request) return;
      if (request.scope !== this.scope()) {
        this.manual.set(null);
        return;
      }
      const loading = this.view.isLoading();
      const shown = this.shownAt();
      if (loading && shown !== null) return;
      const delay = loading
        ? indicatorDelay(false, performance.now() - request.started)
        : shown === null ? 0 : indicatorDelay(true, performance.now() - shown);
      const timer = setTimeout(() => {
        if (this.manual() !== request || request.scope !== this.scope() || this.view.isLoading() !== loading) return;
        if (loading) this.shownAt.set(performance.now());
        else {
          this.manual.set(null);
          if (this.view.error()) this.snackBar.open("Could not refresh mail", "Dismiss", {duration: 5000});
        }
      }, delay);
      onCleanup(() => clearTimeout(timer));
    });
    // The open mailbox is never left hidden inside a collapsed parent.
    effect(() => {
      const tree = this.treeRef();
      const roots = this.mailboxNodes();
      const ancestors = ancestorsOf(this.mailboxes(), this.selectedMailbox());
      if (!tree || !ancestors.length) return;
      const byId = new Map<string, MailboxNode>();
      const walk = (nodes: MailboxNode[]) => nodes.forEach(node => { byId.set(node.box.id, node); walk(node.children); });
      walk(roots);
      untracked(() => {
        for (const id of ancestors) {
          const node = byId.get(id);
          if (node) tree.expand(node);
        }
      });
    });
    // Selection belongs to one connection, Account and mailbox.
    effect(() => {
      this.connection.connected();
      this.connection.accountId();
      this.mailbox();
      untracked(() => {
        this.pullEnd();
        this.selecting.set(false);
        this.selected.set(new Set());
        this.refused.set(new Set());
      });
    });
    // Rows that left the listing leave the selection.
    effect(() => {
      if (!this.view.hasValue()) return;
      const present = new Set(this.eligible());
      const selected = untracked(this.selected);
      if ([...selected].some(id => !present.has(id))) {
        untracked(() => this.selected.set(new Set([...selected].filter(id => present.has(id)))));
      }
    });
    // A confirmed change syncs the listing again: deltas only, with mailbox counts. reload() keeps
    // the rows on screen while it runs.
    let seen = this.actions.changed();
    effect(() => {
      const requested = this.actions.changed();
      const count = reloadRevision(seen, requested, requested !== seen && (this.view.isLoading() || !!this.page()));
      if (count !== seen) {
        seen = count;
        untracked(() => this.view.reload());
      }
    });
    // Mailbox and message ids belong to one Account: switching Accounts clears the selection.
    let previous: string | null | undefined;
    effect(() => {
      const accountId = this.connection.accountId();
      if (previous !== undefined && previous !== accountId) void this.router.navigate(["/mail"]);
      previous = accountId;
    });
  }
}
