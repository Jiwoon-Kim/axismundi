import { Component, input, output, viewChild } from "@angular/core";
import { MatIcon } from "@angular/material/icon";
import { MatMenu, MatMenuItem } from "@angular/material/menu";
import { mailboxIcon } from "./mail-moves";
import type { MailboxRow } from "./mail-rows";

/**
 * The "Move to…" destination list, shared by the reading pane, a list row and the selection toolbar.
 * A trigger opens it with `[matMenuTriggerFor]="ref.menu()"`; a parent mat-menu item may nest it.
 */
@Component({
  selector: "app-mail-move-menu",
  exportAs: "moveMenu",
  imports: [MatIcon, MatMenu, MatMenuItem],
  template: `
    <mat-menu #menu="matMenu" aria-label="Move to">
      @for (box of targets(); track box.id) {
        <button mat-menu-item type="button" (click)="chosen.emit(box)">
          <mat-icon>{{ icon(box) }}</mat-icon><span>{{ box.name || "(unnamed)" }}</span>
        </button>
      }
    </mat-menu>
  `,
})
export class MailMoveMenuComponent {
  readonly targets = input.required<readonly MailboxRow[]>();
  readonly chosen = output<MailboxRow>();
  readonly menu = viewChild.required<MatMenu>("menu");
  protected readonly icon = mailboxIcon;
}
