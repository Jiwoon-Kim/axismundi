import { Component, inject, signal } from "@angular/core";
import { MatIconButton } from "@angular/material/button";
import { MAT_DIALOG_DATA, MatDialogRef } from "@angular/material/dialog";
import { MatIcon } from "@angular/material/icon";
import { MatTooltip } from "@angular/material/tooltip";

export interface SearchDialogData { text: string; compact: boolean }

@Component({
  selector: "app-search-dialog",
  imports: [MatIcon, MatIconButton, MatTooltip],
  template: `
    <form class="search-form" [class.compact]="data.compact" role="search" (submit)="submit($event)">
      <button matIconButton type="button" matTooltip="Back" aria-label="Back" (click)="close()"><mat-icon>arrow_back</mat-icon></button>
      <input #query type="search" [value]="text()" (input)="text.set(query.value)"
             (keydown.enter)="submit($event)"
             placeholder="Search mail" aria-label="Search mail" autocomplete="off" enterkeyhint="search" />
      <button matIconButton type="button" matTooltip="Clear search" aria-label="Clear search" [disabled]="!text()"
              (click)="text.set(''); query.focus()"><mat-icon>close</mat-icon></button>
    </form>
  `,
  styles: [`
    .search-form { display: flex; align-items: center; gap: 4px; padding-inline: 4px; block-size: 56px;
      box-sizing: border-box; background: var(--mat-sys-surface-container-high); }
    .search-form.compact { border-block-end: 1px solid var(--mat-sys-outline); }
    input { flex: 1; min-inline-size: 0; min-block-size: 40px; padding-inline: 8px; border: 0; outline: none;
      background: transparent; color: var(--mat-sys-on-surface); font: var(--mat-sys-body-large); }
    input::placeholder { color: var(--mat-sys-on-surface-variant); }
    input::-webkit-search-cancel-button { display: none; }
  `],
})
export class SearchDialogComponent {
  protected readonly data = inject<SearchDialogData>(MAT_DIALOG_DATA);
  private readonly dialog = inject<MatDialogRef<SearchDialogComponent, string>>(MatDialogRef);
  protected readonly text = signal(this.data.text);

  protected submit(event: Event): void {
    if (event instanceof KeyboardEvent && event.isComposing) return;
    event.preventDefault();
    const text = this.text().trim();
    if (text) this.dialog.close(text);
  }

  protected close(): void { this.dialog.close(); }
}
