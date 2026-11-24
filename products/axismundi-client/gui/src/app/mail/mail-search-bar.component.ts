import { Component, ElementRef, inject, input, output } from "@angular/core";
import { MatIconButton } from "@angular/material/button";
import { MatIcon } from "@angular/material/icon";
import { MatTooltip } from "@angular/material/tooltip";

@Component({
  selector: "app-mail-search-bar",
  imports: [MatIcon, MatIconButton, MatTooltip],
  template: `
    @if (text()) {
      <button matIconButton type="button" aria-label="Exit search" matTooltip="Exit search"
              (click)="exitSearch(trigger)"><mat-icon>arrow_back</mat-icon></button>
    }
    <button type="button" #trigger class="mail-search-trigger" aria-haspopup="dialog" (click)="activate.emit(host)"
            [attr.aria-label]="text() ? 'Edit mail search: ' + text() : 'Search mail'">
      @if (!text()) { <mat-icon>search</mat-icon> }
      <span>{{ text() || "Search mail" }}</span>
    </button>
    @if (text()) {
      <button matIconButton type="button" aria-label="Clear search" matTooltip="Clear search"
              (click)="exitSearch(trigger)"><mat-icon>close</mat-icon></button>
    }
  `,
  styles: [`
    :host {
      display: flex;
      align-items: center;
      /* NON-STANDARD: 12px keeps the bar aligned with rows in the narrow list pane. */
      margin-inline: 12px;
      margin-block-end: 8px;
      padding-inline: 4px;
      block-size: 56px;
      border-radius: 9999px;
      /* NON-STANDARD: The resting bar is flat within the pane; the docked view uses level3. */
      background: var(--mat-sys-surface-container-high);
    }
    .mail-search-trigger {
      display: flex;
      align-items: center;
      /* NON-STANDARD: 12px is measured from the 24px icon, not a separate 48px touch target. */
      gap: 12px;
      flex: 1;
      align-self: stretch;
      min-inline-size: 0;
      padding-inline: 12px;
      border: 0;
      border-radius: inherit;
      background: transparent;
      color: var(--mat-sys-on-surface-variant);
      font: var(--mat-sys-body-large);
      text-align: start;
      cursor: pointer;
    }
    .mail-search-trigger mat-icon { flex: none; color: var(--mat-sys-on-surface); }
    .mail-search-trigger span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    :host(:has(.mail-search-trigger:focus-visible)) {
      outline: 2px solid var(--mat-sys-secondary);
      outline-offset: 2px;
    }
    .mail-search-trigger:focus-visible { outline: none; }
  `],
})
export class MailSearchBarComponent {
  protected readonly host = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;
  readonly text = input<string | null>(null);
  readonly activate = output<HTMLElement>();
  readonly exit = output<void>();

  protected exitSearch(trigger: HTMLButtonElement): void {
    this.exit.emit();
    trigger.focus();
  }
}
