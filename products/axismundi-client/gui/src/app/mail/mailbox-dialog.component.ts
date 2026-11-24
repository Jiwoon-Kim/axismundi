import { Component, computed, inject, signal } from "@angular/core";
import { FormField, form, readonly, required } from "@angular/forms/signals";
import { MatButton } from "@angular/material/button";
import { MAT_DIALOG_DATA, MatDialogActions, MatDialogContent, MatDialogRef, MatDialogTitle } from "@angular/material/dialog";
import { MatFormField, MatHint, MatLabel } from "@angular/material/form-field";
import { MatInput } from "@angular/material/input";
import { MatOption, MatSelect } from "@angular/material/select";
import { MAILBOX_ROLES, type MailboxChange, type MailboxResult, type ParentOption, mailboxNameError, parentAllowed } from "./mailbox-management";

export interface MailboxDialogData {
  kind: MailboxChange["kind"];
  target?: {id: string; name: string};
  parent?: string;
  parents: ParentOption[];
  usedRoles: ReadonlySet<string>;
  topLevel: boolean;
  maxDepth: number | null;
  maxName: number | null;
  write: (change: MailboxChange) => Promise<MailboxResult>;
}

@Component({
  selector: "app-mailbox-dialog",
  imports: [FormField, MatButton, MatDialogActions, MatDialogContent, MatDialogTitle,
    MatFormField, MatHint, MatInput, MatLabel, MatOption, MatSelect],
  templateUrl: "./mailbox-dialog.component.html",
  styles: [`mat-form-field { display: block; } p[role=alert] { color: var(--mat-sys-error); overflow-wrap: anywhere; } details { margin-block: 8px; } summary { cursor: pointer; margin-block-end: 8px; }`],
})
export class MailboxDialogComponent {
  protected readonly data: MailboxDialogData = inject(MAT_DIALOG_DATA);
  private readonly dialog = inject<MatDialogRef<MailboxDialogComponent, MailboxResult>>(MatDialogRef);
  private readonly model = signal({name: this.data.kind === "rename" ? this.data.target!.name : ""});
  protected readonly busy = signal(false);
  protected readonly fields = form(this.model, p => {
    required(p.name);
    readonly(p.name, () => this.busy());
  });
  protected readonly parents = this.data.parents;
  protected readonly parent = signal(this.data.parent ?? (this.data.topLevel ? "" : undefined));
  protected readonly role = signal("");
  protected readonly roles = MAILBOX_ROLES;
  protected readonly error = signal<{message: string; properties: string[]} | null>(null);
  protected readonly nameError = computed(() => mailboxNameError(this.model().name, this.data.maxName));
  protected readonly validParent = computed(() => {
    const id = this.parent();
    const parent = this.parents.find(option => option.id === id);
    return (id === "" || !!parent) && parentAllowed(parent, this.data.topLevel, this.data.maxDepth);
  });
  protected readonly title = {create: "New mailbox", rename: "Rename mailbox", destroy: "Delete mailbox"}[this.data.kind];
  protected readonly action = {create: "Create", rename: "Rename", destroy: "Delete"}[this.data.kind];

  protected parentDisabled(parent: ParentOption): boolean {
    return !parentAllowed(parent, this.data.topLevel, this.data.maxDepth);
  }

  protected cancel(): void { this.dialog.close(); }

  protected async submit(event: Event): Promise<void> {
    event.preventDefault();
    const kind = this.data.kind;
    if (this.busy() || (kind !== "destroy" && this.nameError()) || (kind === "create" && !this.validParent())) return;
    const name = this.model().name.trim();
    const change: MailboxChange = kind === "create"
      ? {kind, name, parent: this.parent() || null, role: this.role() || null}
      : kind === "rename" ? {kind, name, id: this.data.target!.id} : {kind, id: this.data.target!.id};
    this.error.set(null);
    this.busy.set(true);
    this.dialog.disableClose = true;
    try {
      const result = await this.data.write(change);
      if (result.status === "refused") this.error.set(result);
      else if (result.status === "unchanged") this.error.set({message: "The change was not applied. You can try again.", properties: []});
      else this.dialog.close(result);
    } catch {
      this.error.set({message: "Could not change the mailbox.", properties: []});
    } finally {
      this.busy.set(false);
      this.dialog.disableClose = false;
    }
  }
}
