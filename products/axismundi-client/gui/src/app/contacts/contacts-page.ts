import { DOCUMENT, NgTemplateOutlet } from "@angular/common";
import { afterNextRender, Component, computed, DestroyRef, effect, inject, Injector, LOCALE_ID, signal, TemplateRef, untracked, viewChild } from "@angular/core";
import { BreakpointObserver } from "@angular/cdk/layout";
import { map } from "rxjs";
import { form, FormField } from "@angular/forms/signals";
import { MatButton, MatFabButton, MatIconButton } from "@angular/material/button";
import { MatCard } from "@angular/material/card";
import { MatDialog } from "@angular/material/dialog";
import { MatMenu, MatMenuItem, MatMenuTrigger } from "@angular/material/menu";
import { MatChip, MatChipRemove, MatChipSet, MatChipGrid, MatChipRow, MatChipInput } from "@angular/material/chips";
import { MatCheckbox } from "@angular/material/checkbox";
import { MatRadioButton, MatRadioGroup } from "@angular/material/radio";
import { MatDatepicker, MatDatepickerInput, MatDatepickerToggle } from "@angular/material/datepicker";
import { provideNativeDateAdapter } from "@angular/material/core";
import { MatFormField, MatLabel, MatSuffix } from "@angular/material/form-field";
import { MatInput } from "@angular/material/input";
import { MatSidenav, MatSidenavContainer, MatSidenavContent } from "@angular/material/sidenav";
import { MatIcon } from "@angular/material/icon";
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
import { SearchDialogComponent, SearchDialogData } from "../shell/search-dialog.component";
import { Chip, contactFilter, FilterKey, filterFields, SortKey, sortFields } from "./contact-query";

interface ContactDocument { card: Card; row: ContactRow | null; state: string | null }

@Component({
  providers: [provideNativeDateAdapter()],
  imports: [ContactAvatar, FormField, MailSearchBarComponent, MatButton, MatCard, MatFabButton, MatIcon, MatIconButton,
    MatMenu, MatMenuItem, MatMenuTrigger, MatChip, MatChipRemove, MatChipSet,
    MatTableModule, RouterLink, NgTemplateOutlet, MatSidenav, MatSidenavContainer,
    MatSidenavContent, MatFormField, MatLabel, MatSuffix, MatInput,
    MatToolbar, MatTooltip, MatChipGrid, MatChipRow, MatChipInput, MatCheckbox, MatRadioButton, MatRadioGroup,
    MatDatepicker, MatDatepickerInput, MatDatepickerToggle],
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
  private readonly booksReady = signal(false);
  protected readonly orderedBooks = computed(() => sortBooks(this.books(), this.collator));
  protected readonly bookId = computed(() => {
    const requested = this.params().get("book");
    return this.books().find(book => book.id === requested)?.id ?? null;
  });
  protected readonly selectedBook = computed(() => {
    const books = this.chips().filter(chip => chip.key === "inAddressBook");
    return books.length === 1 ? this.books().find(book => book.id === books[0].value) : undefined;
  });
  protected readonly rows = signal<ContactRow[]>([]);
  protected readonly sortFields = sortFields;
  protected readonly sortKeys = Object.keys(sortFields) as SortKey[];
  protected readonly sortKey = signal<SortKey>("created");
  protected readonly ascending = signal(false);
  protected readonly filterFields = filterFields;
  /** Date conditions live in the side sheet; the search view's input chips take the typed ones. */
  protected readonly dateKeys: FilterKey[] = ["createdAfter", "createdBefore", "updatedAfter", "updatedBefore"];
  protected readonly filterKeys = (Object.keys(filterFields) as FilterKey[])
    .filter(key => key !== "inAddressBook" && key !== "kind" && !this.dateKeys.includes(key));
  protected readonly filtersOpen = signal(false);
  protected readonly kindLabels: Record<string, string> = {individual: "Person", group: "Group", org: "Organization",
    location: "Location", device: "Device", application: "Application"};
  protected readonly filterKey = signal<FilterKey>("email");
  protected readonly kinds = ["individual", "group", "org", "location", "device", "application"];
  protected readonly inputChips = computed(() => this.draft().filter(c => this.filterKeys.includes(c.key)));
  protected readonly sheetFiltered = computed(() => this.chips().some(c => !this.filterKeys.includes(c.key)));
  protected readonly allContacts = computed(() => !this.chips().some(c => c.key === "inAddressBook"));
  protected readonly draft = signal<Chip[]>([]);
  private readonly applied = signal<Chip[] | null>(null);
  protected readonly chips = computed<Chip[]>(() => {
    const book = this.bookId();
    return this.applied() ?? (book ? [{key: "inAddressBook", value: book}] : []);
  });
  protected readonly columns = ["name", "email", "phone", "organization"];
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
  private queryKey = "";
  private editBase: ContactDocument | null = null;
  private entryIds: string[] = [];

  constructor() {
    effect(() => this.scaffold.appBar.set({leading: this.appBarLeading() ?? null, search: this.appBarSearch() ?? null}));
    inject(DestroyRef).onDestroy(() => this.scaffold.appBar.set(null));
    effect(() => {
      const scope = this.scope();
      this.books.set([]);
      this.booksReady.set(false);
      this.rows.set([]);
      this.applied.set(null);
      this.search.set("");
      this.editing.set(null);
      if (scope) untracked(() => { void this.refresh(); });
    });
    effect(() => {
      const scope = this.scope(), filter = contactFilter(this.search(), this.chips());
      const sort = [{property: this.sortKey(), isAscending: this.ascending()}];
      this.revision();
      const generation = ++this.listGeneration;
      if (!scope || !this.booksReady()) {
        this.rows.set([]);
        this.busy.set(false);
        return;
      }
      // A different query empties the table at once: rows from the previous filter or order must not
      // be opened under the new chips. A reload of the same query (after a save or sync) keeps them.
      const key = JSON.stringify([scope, filter, sort]);
      if (key !== this.queryKey) { this.queryKey = key; this.rows.set([]); }
      this.busy.set(true);
      this.error.set("");
      void invoke<ContactRow[]>("contacts_query", {...scope, filter, sort})
        .then(rows => { if (generation === this.listGeneration) this.rows.set(rows); })
        .catch(error => {
          if (generation === this.listGeneration) { this.rows.set([]); this.error.set(queryError(String(error), sortFields[sort[0].property])); }
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
    catch { if (scope === this.scope()) this.cacheNotice.set("Sync unavailable."); }
    if (scope !== this.scope()) return;
    try {
      const books = await invoke<Book[]>("contacts_books", scope);
      if (scope !== this.scope()) return;
      this.books.set(books);
      this.booksReady.set(true);
      this.revision.update(n => n + 1);
    } catch (error) { if (scope === this.scope()) this.cacheNotice.set(String(error)); }
  }

  protected chipValue(chip: Chip): string {
    if (chip.key === "inAddressBook") return this.books().find(b => b.id === chip.value)?.name ?? chip.value;
    if (chip.key === "kind") return this.kindLabels[chip.value] ?? chip.value;
    return this.dateKeys.includes(chip.key) ? (this.dateOf(chip.key)?.toLocaleDateString() ?? chip.value) : chip.value;
  }
  protected removeChip(chip: Chip): void {
    this.applied.set(this.chips().filter(c => c.key !== chip.key || c.value !== chip.value));
  }
  protected removeDraft(chip: Chip): void { this.draft.update(chips => chips.filter(c => c !== chip)); }
  protected has(key: FilterKey, value: string): boolean { return this.chips().some(c => c.key === key && c.value === value); }
  /** A side-sheet checkbox adds or removes one value; values of one field combine with OR (contact-query.ts). */
  protected toggle(key: FilterKey, value: string, on: boolean): void {
    const rest = this.chips().filter(c => c.key !== key || c.value !== value);
    this.applied.set(on ? [...rest, {key, value}] : rest);
  }
  protected dateOf(key: FilterKey): Date | null {
    const value = this.chips().find(c => c.key === key)?.value;
    return value ? new Date(value) : null;
  }
  /**
   * A picked day is the instant it begins where the person is, sent as a UTCDate: "after 27 Nov" in
   * Seoul is 2026-11-26T15:00:00Z, not 27 Nov 00:00 UTC (which would skip that morning).
   */
  protected setDate(key: FilterKey, value: Date | null): void {
    const rest = this.chips().filter(c => c.key !== key);
    if (!value || !Number.isFinite(value.valueOf())) { this.applied.set(rest); return; }
    const start = new Date(value.getFullYear(), value.getMonth(), value.getDate());
    this.applied.set([...rest, {key, value: start.toISOString().replace(".000Z", "Z")}]);
  }
  protected clearSheet(): void { this.applied.set(this.chips().filter(c => this.filterKeys.includes(c.key))); }
  protected addFilter(value: string): void {
    const key = this.filterKey();
    if (!value.trim()) return;
    this.draft.update(chips => chips.some(c => c.key === key && c.value === value.trim())
      ? chips : [...chips, {key, value: value.trim()}]);
  }
  protected searchDialog(template: TemplateRef<unknown>, anchor?: HTMLElement): void {
    if (this.editing() || this.saving()) return;
    const scope = this.scope();
    // Reopening the applied search restores all of it. A new search starts from every address book
    // (the brief's rule): the list's address book is not carried in where the dialog would not show it.
    const reopening = !!this.search() || this.chips().some(c => this.filterKeys.includes(c.key));
    this.draft.set(reopening ? this.chips() : this.chips().filter(c => c.key !== "inAddressBook"));
    this.filterKey.set("email");
    const compact = !this.expanded();
    const rect = anchor?.getBoundingClientRect();
    const width = compact ? "100vw" : rect ? `${rect.width}px` : "560px";
    this.dialog.open<SearchDialogComponent, SearchDialogData, string>(SearchDialogComponent, {
      width, maxWidth: compact ? width : "calc(100vw - 16px)", autoFocus: "input",
      ariaLabel: "Search contacts", ariaModal: true,
      height: compact ? "100dvh" : undefined,
      maxHeight: compact ? "100dvh" : rect ? `calc(100dvh - ${rect.top + 8}px)` : undefined,
      position: compact ? {top: "0", left: "0"} : rect ? {top: `${rect.top}px`, left: `${rect.left}px`} : undefined,
      panelClass: compact ? "mail-search-fullscreen" : "search-with-filters",
      data: {text: this.search(), compact, label: "Search contacts", filters: template},
    }).afterClosed().subscribe(text => {
      if (text === undefined || scope !== this.scope()) return;
      this.applied.set(this.draft());
      this.applySearch(text);
    });
  }
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
  protected chooseBook(book: string | null): void {
    this.drawer.set(false);
    if (book !== this.bookId()) this.rows.set([]);
    this.editing.set(null);
    this.applied.set(null);
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
        const bookId = this.selectedBook()?.id ?? this.orderedBooks().find(book => book.isDefault)?.id ?? this.orderedBooks()[0]?.id;
        const {id} = await invoke<{id: string}>("contacts_create", {...scope, bookId, card: newCard(edits)});
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

/** A server refusal says what it refused; the query is not changed to something it accepts. */
function queryError(message: string, sortLabel: string): string {
  if (message.includes("unsupportedSort")) return `This server cannot sort contacts by ${sortLabel}.`;
  if (message.includes("unsupportedFilter")) return "This server does not support one of the filters.";
  return message;
}

function localizedValues(value: unknown, path = ""): {path: string; value: string}[] {
  if (value !== null && typeof value === "object") {
    return Object.entries(value).flatMap(([key, child]) => localizedValues(child, path ? path + " / " + key : key));
  }
  return [{path, value: value === null ? "Removed" : String(value)}];
}
