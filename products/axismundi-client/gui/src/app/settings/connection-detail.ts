import { Component, computed, inject, signal } from "@angular/core";
import { toSignal } from "@angular/core/rxjs-interop";
import { Clipboard } from "@angular/cdk/clipboard";
import { NgTemplateOutlet } from "@angular/common";
import { ActivatedRoute, Router } from "@angular/router";
import { MatButton, MatIconButton } from "@angular/material/button";
import { MatCard, MatCardContent, MatCardHeader, MatCardTitle } from "@angular/material/card";
import { MatChip, MatChipSet } from "@angular/material/chips";
import { MatCheckbox } from "@angular/material/checkbox";
import { MatAccordion, MatExpansionPanel, MatExpansionPanelHeader, MatExpansionPanelTitle,
  MatExpansionPanelDescription } from "@angular/material/expansion";
import { MatFormField, MatLabel, MatPrefix } from "@angular/material/form-field";
import { MatIcon } from "@angular/material/icon";
import { MatInput } from "@angular/material/input";
import { MatActionList, MatListItem, MatListItemTitle, MatListItemLine } from "@angular/material/list";
import { MatSnackBar } from "@angular/material/snack-bar";
import { MatTab, MatTabGroup } from "@angular/material/tabs";
import { MatOption, MatSelect } from "@angular/material/select";
import { MatTooltip } from "@angular/material/tooltip";
import { ConnectionService } from "../connection.service";
import { type AccountView, type CapabilityRow, capabilityInfo, compareCapability, creationPermissions, isServerLevel, searchCapabilities, sessionView } from "./jmap-session-view";

const tabs = ["overview", "accounts", "capabilities", "session"] as const;

/**
 * One connection's JMAP Session, read only (docs/BRIEF-SETTINGS-JMAP-SESSION.md). It reads the
 * Session that connection already holds: opening it neither switches the app's connection nor its
 * selected Account, and nothing here makes a request.
 */
@Component({
  imports: [NgTemplateOutlet, MatButton, MatIconButton, MatCard, MatCardContent, MatCardHeader, MatCardTitle,
    MatChip, MatChipSet, MatAccordion, MatExpansionPanel, MatExpansionPanelHeader, MatExpansionPanelTitle,
    MatExpansionPanelDescription, MatFormField, MatLabel, MatPrefix, MatIcon, MatInput, MatActionList, MatListItem,
    MatListItemTitle, MatListItemLine, MatTab, MatTabGroup, MatTooltip, MatSelect, MatOption, MatCheckbox],
  templateUrl: "./connection-detail.html",
  styleUrl: "./connection-detail.css",
})
export class ConnectionDetailPage {
  private readonly connection = inject(ConnectionService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly clipboard = inject(Clipboard);
  private readonly snackBar = inject(MatSnackBar);
  private readonly params = toSignal(this.route.paramMap, {requireSync: true});
  private readonly query = toSignal(this.route.queryParamMap, {requireSync: true});

  protected readonly item = computed(() =>
    this.connection.connections().find(item => String(item.id) === this.params().get("id")) ?? null);
  protected readonly document = computed(() => this.item()?.jmap_document ?? null);
  protected readonly view = computed(() => this.document() ? sessionView(this.document()) : null);
  protected readonly sessionJson = computed(() => JSON.stringify(this.document(), null, 2));
  protected readonly tabIndex = computed(() => Math.max(0, tabs.indexOf(this.query().get("tab") as typeof tabs[number])));
  /** A capability opened from the Overview: its row starts expanded. */
  protected readonly openCapability = computed(() => this.query().get("capability"));
  /** The Account shown on the Accounts tab: a choice of this screen, not the app's selected Account. */
  protected readonly account = computed<AccountView | null>(() => {
    const accounts = this.view()?.accounts ?? [];
    return accounts.find(a => a.id === this.query().get("account")) ?? accounts[0] ?? null;
  });
  protected readonly search = signal("");
  protected readonly onlyDifferences = signal(false);
  protected readonly comparing = computed(() => this.query().has("compare"));
  protected readonly comparisonAccounts = computed(() => {
    const accounts = this.view()?.accounts ?? [];
    const ids = this.comparing() ? (this.query().get("compare") ?? "").split(",") : [this.account()?.id];
    return accounts.filter(account => ids.includes(account.id)).slice(0, 3);
  });
  protected readonly comparisonIds = computed(() => this.comparisonAccounts().map(account => account.id));
  protected readonly columnCount = computed(() => this.comparisonIds().length + (this.comparing() ? 4 : 5));
  protected readonly permissions = computed(() => creationPermissions(this.comparisonAccounts()));
  protected readonly accountPermissions = computed(() => {
    const account = this.account();
    return account ? creationPermissions([account]) : [];
  });
  protected readonly accountJson = computed(() => JSON.stringify(this.account()?.raw ?? {}, null, 2));
  protected selectComparison(ids: string[]): void {
    this.setComparison(ids.slice(0, 3).join(","));
  }
  protected toggleComparison(): void {
    this.onlyDifferences.set(false);
    this.setComparison(this.comparing() ? null : this.account()?.id ?? "");
  }
  private setComparison(compare: string | null): void {
    void this.router.navigate([], {relativeTo: this.route, queryParamsHandling: "merge",
      queryParams: {compare}, replaceUrl: true});
  }
  protected readonly serverLevel = isServerLevel;
  protected readonly groups = computed(() => {
    const view = this.view();
    if (!view) return [];
    const rows = searchCapabilities(view.capabilities, this.search());
    const ids = this.comparisonIds();
    const onlyDifferences = this.comparing() && this.onlyDifferences() && ids.length > 1;
    return view.groups.map(g => ({
      group: g.group,
      rows: g.rows.filter(row => rows.includes(row)).map(row => ({row, comparison: compareCapability(row, ids)}))
        .filter(({comparison}) => !onlyDifferences || comparison.presence || comparison.differs),
    })).filter(g => g.rows.length);
  });
  private readonly accountNames = computed(() => new Map((this.view()?.accounts ?? []).map(a => [a.id, a.name])));

  /** Rows opened on the Capabilities tab; one opened from the Overview starts open. */
  private readonly opened = signal<ReadonlySet<string>>(new Set());
  protected isOpen(urn: string): boolean { return this.opened().has(urn) || this.openCapability() === urn; }
  protected toggle(urn: string): void {
    const open = this.isOpen(urn);
    this.opened.update(set => { const next = new Set(set); if (open) next.delete(urn); else next.add(urn); return next; });
    if (open && this.openCapability() === urn) {
      void this.router.navigate([], {relativeTo: this.route, queryParamsHandling: "merge", queryParams: {capability: null}, replaceUrl: true});
    }
  }
  protected accountList(row: CapabilityRow): string { return row.accounts.map(a => a.name).join(", "); }
  protected accountName(id: string | null): string { return id ? this.accountNames().get(id) ?? id : ""; }
  protected label(urn: string): string { return capabilityInfo(urn).label; }

  protected selectTab(index: number): void {
    // The group reports the index it was given as well as clicks: the tab in the URL is already shown.
    if (index === this.tabIndex()) return;
    void this.router.navigate([], {relativeTo: this.route, queryParamsHandling: "merge",
      queryParams: {tab: tabs[index], capability: null}, replaceUrl: true});
  }
  protected showCapability(urn: string): void {
    this.search.set("");
    this.onlyDifferences.set(false);
    void this.router.navigate([], {relativeTo: this.route, queryParamsHandling: "merge",
      queryParams: {tab: "capabilities", capability: urn,
        compare: this.comparisonIds().length >= 2 ? this.query().get("compare") : null}});
  }
  protected showAccount(id: string): void {
    void this.router.navigate([], {relativeTo: this.route, queryParamsHandling: "merge", queryParams: {account: id}, replaceUrl: true});
  }
  protected copy(text: string, what: string): void {
    this.snackBar.open(this.clipboard.copy(text) ? `${what} copied` : `Could not copy ${what.toLowerCase()}`, undefined, {duration: 2500});
  }
}
