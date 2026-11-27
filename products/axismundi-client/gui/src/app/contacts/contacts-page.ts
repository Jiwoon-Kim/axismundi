import { DOCUMENT, NgTemplateOutlet } from "@angular/common";
import { afterNextRender, Component, computed, DestroyRef, effect, inject, Injector, LOCALE_ID, signal, TemplateRef, untracked, viewChild } from "@angular/core";
import { BreakpointObserver } from "@angular/cdk/layout";
import { map } from "rxjs";
import { form, FormField } from "@angular/forms/signals";
import { MatButton, MatFabButton, MatIconButton } from "@angular/material/button";
import { MatCard } from "@angular/material/card";
import { MatDialog, MatDialogActions, MatDialogContent, MatDialogTitle } from "@angular/material/dialog";
import { MatFormField, MatLabel } from "@angular/material/form-field";
import { MatInput } from "@angular/material/input";
import { MatSidenav, MatSidenavContainer, MatSidenavContent } from "@angular/material/sidenav";
import { MatIcon } from "@angular/material/icon";
import { MatSort, MatSortHeader, Sort } from "@angular/material/sort";
import { MatTableModule } from "@angular/material/table";
import { MatToolbar } from "@angular/material/toolbar";
import { MatTooltip } from "@angular/material/tooltip";
import { ActivatedRoute, Router, RouterLink } from "@angular/router";
import { toSignal } from "@angular/core/rxjs-interop";
import { invoke } from "@tauri-apps/api/core";
import { ConnectionService } from "../connection.service";
import { Book, Card, CONTACTS, ContactRow, detailFields, editsFor, fields, newCard, record, sortBooks } from "./contact-model";
import { ContactAvatar } from "./contact-avatar";
import { MailSearchBarComponent } from "../mail/mail-search-bar.component";
import { EXPANDED, Scaffold } from "../shell/scaffold";

interface ContactDocument { card: Card; row: ContactRow | null; state: string | null }

@Component({
  imports: [ContactAvatar, FormField, MailSearchBarComponent, MatButton, MatCard, MatFabButton, MatIcon, MatIconButton,
    MatSort, MatSortHeader, MatTableModule, RouterLink, NgTemplateOutlet, MatSidenav, MatSidenavContainer,
    MatSidenavContent, MatDialogActions, MatDialogContent, MatDialogTitle, MatFormField, MatLabel, MatInput,
    MatToolbar, MatTooltip],
  templateUrl: "./contacts-page.html",
  styleUrl: "./contacts-page.css",
})
export class ContactsPage {
  private readonly connection = inject(ConnectionService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly document = inject(DOCUMENT);
  private readonly injector = inject(Injector);
  private readonly scaffold = inject(Scaffold);
  private readonly dialog = inject(MatDialog);
  private readonly appBarSearch = viewChild<TemplateRef<unknown>>("appBarSearch");
  private readonly appBarLeading = viewChild<TemplateRef<unknown>>("appBarLeading");
  protected readonly windowSearch = computed(() => this.scaffold.layout().appBarScope === "window");
  protected readonly listScrolled = signal(false);
  protected readonly expanded = toSignal(inject(BreakpointObserver).observe(EXPANDED).pipe(map(state => state.matches)), {requireSync: true});
  protected readonly drawer = signal(false);
  private readonly collator = new Intl.Collator(inject(LOCALE_ID), {sensitivity: "base", numeric: true});
  private readonly params = toSignal(this.route.queryParamMap, {requireSync: true});
  private readonly scope = computed(() => {
    const connection = this.connection.connected();
    if (!connection) return null;
    const accounts = this.connection.accounts().filter(account => account.capabilities.includes(CONTACTS));
    const account = accounts.find(item => item.id === this.connection.accountId()) ??
      accounts.find(item => item.id === connection.jmap_primary_accounts?.[CONTACTS]) ?? accounts[0];
    return account ? {connectionId: connection.id, accountId: account.id} : null;
  }, {equal: (a, b) => a?.connectionId === b?.connectionId && a?.accountId === b?.accountId});
  protected readonly cardId = computed(() => this.params().get("card"));
  protected readonly books = signal<Book[]>([]);
  protected readonly orderedBooks = computed(() => sortBooks(this.books(), this.collator));
  protected readonly bookId = computed(() => {
    const requested = this.params().get("book");
    return this.books().find(book => book.id === requested)?.id ??
      this.orderedBooks().find(book => book.isDefault)?.id ??
      this.orderedBooks()[0]?.id ?? null;
  });
  protected readonly selectedBook = computed(() => this.books().find(book => book.id === this.bookId()));
  protected readonly rows = signal<ContactRow[]>([]);
  protected readonly direction = signal<"asc" | "desc">("asc");
  protected readonly sorted = computed(() => [...this.rows()].sort((a, b) =>
    (this.direction() === "desc" ? -1 : 1) *
    this.collator.compare(a.sortKey ?? a.name ?? a.email ?? "", b.sortKey ?? b.name ?? b.email ?? "")));
  protected readonly columns = ["name", "email", "phone", "organization"];
  protected readonly query = signal("");
  protected readonly search = signal("");
  protected readonly busy = signal(false);
  protected readonly error = signal("");
  protected readonly cacheNotice = signal("");
  protected readonly detailError = signal("");
  private readonly loaded = signal<ContactDocument | null>(null);
  protected readonly card = computed(() => this.loaded()?.card ?? null);
  protected readonly selected = computed(() => this.loaded()?.row);
  protected readonly memberCount = computed(() =>
    Object.values(record(this.card()?.["members"])).filter(value => value === true).length);
  protected readonly detail = computed(() => detailFields(this.card() ?? {}));
  protected readonly languages = computed(() => Object.entries(record(this.card()?.["localizations"]))
    .map(([language, patch]) => [language, localizedValues(patch)] as const));
  protected readonly toggleDrawer = (open: boolean) => !open;
  protected readonly editing = signal<"new" | "edit" | null>(null);
  protected readonly editor = signal(fields({}));
  protected readonly values = signal<Record<string, string>>({});
  protected readonly form = form(this.values);
  protected readonly saving = signal(false);
  protected readonly saveError = signal("");
  protected readonly conflict = computed(() => this.saveError().includes("stateMismatch"));
  protected readonly readOnly = computed(() => this.connection.accounts().find(a => a.id === this.scope()?.accountId)?.isReadOnly === true);
  protected readonly paneOpen = computed(() => !!this.cardId() || this.editing() === "new");
  private readonly revision = signal(0);
  private listGeneration = 0;
  private editBase: ContactDocument | null = null;
  private entryIds: string[] = [];

  constructor() {
    effect(() => this.scaffold.appBar.set({leading: this.appBarLeading() ?? null, search: this.appBarSearch() ?? null}));
    inject(DestroyRef).onDestroy(() => this.scaffold.appBar.set(null));
    effect(() => {
      const scope = this.scope();
      this.books.set([]);
      this.rows.set([]);
      this.query.set("");
      this.search.set("");
      this.editing.set(null);
      if (scope) untracked(() => { void this.refresh(); });
    });
    effect(() => {
      const scope = this.scope(), bookId = this.bookId(), text = this.search();
      this.revision();
      const generation = ++this.listGeneration;
      if (!scope || (!text && !bookId)) {
        this.rows.set([]);
        this.busy.set(false);
        return;
      }
      this.busy.set(true);
      this.error.set("");
      void invoke<ContactRow[]>(text ? "contacts_search" : "contacts_list", {...scope, bookId, text})
        .then(rows => { if (generation === this.listGeneration) this.rows.set(rows); })
        .catch(error => {
          if (generation === this.listGeneration) { this.rows.set([]); this.error.set(String(error)); }
        }).finally(() => { if (generation === this.listGeneration) this.busy.set(false); });
    });
    let previousCard: string | null = null;
    effect(onCleanup => {
      const scope = this.scope(), cardId = this.cardId();
      this.revision();
      if (cardId !== previousCard) {
        this.focus(cardId ? "contact-back" : `contact-row-${previousCard}`);
        previousCard = cardId;
      }
      this.loaded.set(null);
      this.detailError.set("");
      if (!scope || !cardId) return;
      let current = true;
      onCleanup(() => { current = false; });
      void invoke<ContactDocument>("contacts_card", {...scope, cardId})
        .then(result => { if (current) this.loaded.set(result); })
        .catch(error => { if (current) this.detailError.set(String(error)); });
    });
  }

  protected async refresh(): Promise<void> {
    const scope = this.scope();
    if (!scope) return;
    this.cacheNotice.set("");
    try { await invoke("contacts_sync", scope); }
    catch { if (scope === this.scope()) this.cacheNotice.set("Sync unavailable. Showing cached contacts."); }
    if (scope !== this.scope()) return;
    try {
      const books = await invoke<Book[]>("contacts_books", scope);
      if (scope !== this.scope()) return;
      this.books.set(books);
      this.revision.update(n => n + 1);
    } catch (error) { if (scope === this.scope()) this.cacheNotice.set(String(error)); }
  }

  protected sort(change: Sort): void { this.direction.set(change.direction === "desc" ? "desc" : "asc"); }
  protected input(event: Event): void { this.query.set((event.target as HTMLInputElement).value); }
  protected searchDialog(template: TemplateRef<unknown>): void {
    if (this.editing() || this.saving()) return;
    this.query.set(this.search());
    this.dialog.open(template, {width: "520px"});
  }
  protected closeSearch(): void { this.dialog.openDialogs.at(-1)?.close(); }
  protected applySearch(text: string): void {
    if (this.editing() || this.saving()) return;
    this.search.set(text);
    if (this.cardId()) {
      void this.router.navigate([], {relativeTo: this.route, queryParamsHandling: "merge", queryParams: {card: null}});
    }
  }
  protected async open(id: string): Promise<void> {
    await this.router.navigate([], {relativeTo: this.route, queryParamsHandling: "merge", queryParams: {card: id}});
  }
  protected async back(): Promise<void> {
    const id = this.cardId();
    this.editing.set(null);
    await this.router.navigate([], {relativeTo: this.route, queryParamsHandling: "merge", queryParams: {card: null}});
    if (!id) this.focus("contact-add");
  }
  private focus(id: string): void {
    afterNextRender(() => this.document.getElementById(id)?.focus({preventScroll: true}), {injector: this.injector});
  }
  protected chooseBook(book: string): void {
    this.drawer.set(false);
    if (book !== this.bookId()) this.rows.set([]);
    this.editing.set(null);
    this.query.set("");
    this.search.set("");
    void this.router.navigate([], {relativeTo: this.route, queryParams: {book}});
  }
  protected edit(mode: "new" | "edit"): void {
    this.editBase = mode === "edit" ? this.loaded() : null;
    this.entryIds = [];
    const editor = fields(this.editBase?.card ?? {});
    this.values.set(Object.fromEntries(editor.map(field => [field.key, field.original])));
    this.editor.set(editor);
    this.saveError.set("");
    this.editing.set(mode);
    this.focus("contact-first-field");
  }
  protected cancelEdit(): void {
    const create = this.editing() === "new";
    this.editing.set(null);
    this.focus(create ? "contact-add" : "contact-edit");
  }
  protected async save(): Promise<void> {
    const scope = this.scope(), mode = this.editing(), cardId = this.cardId();
    if (!scope || !mode || this.saving() || this.readOnly()) return;
    const baseCard = this.editBase?.card;
    if (mode === "edit" && !baseCard) return;
    let nextId = 0;
    const edits = editsFor(baseCard ?? {}, this.editor(), this.values(), () => {
      const i = nextId++;
      return this.entryIds[i] ??= crypto.randomUUID();
    });
    if (!edits.length) { this.cancelEdit(); return; }
    this.saving.set(true);
    this.saveError.set("");
    try {
      if (mode === "new") {
        const {id} = await invoke<{id: string}>("contacts_create", {...scope, bookId: this.bookId(), card: newCard(edits)});
        if (scope !== this.scope()) return;
        this.editing.set(null);
        await this.open(id);
        await this.refresh();
      } else {
        const result = await invoke<ContactDocument>("contacts_update", {...scope, cardId, baseCard, edits});
        if (scope !== this.scope() || cardId !== this.cardId()) return;
        this.loaded.set(result);
        this.editing.set(null);
        this.revision.update(n => n + 1);
        this.focus("contact-edit");
      }
    } catch (error) { if (scope === this.scope()) this.saveError.set(String(error)); }
    finally { this.saving.set(false); }
  }

  protected async reloadContact(): Promise<void> {
    const scope = this.scope(), cardId = this.cardId();
    if (!scope || !cardId || this.saving()) return;
    this.saving.set(true);
    try {
      const result = await invoke<ContactDocument>("contacts_card", {...scope, cardId, reload: true});
      if (scope !== this.scope() || cardId !== this.cardId()) return;
      this.loaded.set(result);
      this.editing.set(null);
      this.revision.update(n => n + 1);
      this.focus("contact-edit");
    } catch (error) { if (scope === this.scope()) this.saveError.set(String(error)); }
    finally { this.saving.set(false); }
  }
}

function localizedValues(value: unknown, path = ""): {path: string; value: string}[] {
  if (value !== null && typeof value === "object") {
    return Object.entries(value).flatMap(([key, child]) => localizedValues(child, path ? path + " / " + key : key));
  }
  return [{path, value: value === null ? "Removed" : String(value)}];
}
