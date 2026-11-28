import { Component, computed, input, output } from "@angular/core";
import { MatButton, MatIconButton } from "@angular/material/button";
import { MatIcon } from "@angular/material/icon";
import { MatProgressBar } from "@angular/material/progress-bar";
import { MatTooltip } from "@angular/material/tooltip";
import { type QueueItem, summary } from "./upload-queue";

/**
 * The upload list a domain screen shows: name, size, state and what can be done next. It knows no
 * protocol — the screen decides what "saved" means and reports it as a QueueState.
 */
@Component({
  selector: "app-upload-queue",
  imports: [MatButton, MatIconButton, MatIcon, MatProgressBar, MatTooltip],
  template: `
    @if (items().length) {
      <section class="queue" aria-label="Uploads">
        <header>
          <span role="status">{{ heading() }}</span>
          <button matButton type="button" [disabled]="!totals().done" (click)="clear.emit()">Clear finished</button>
        </header>
        <ul>
          @for (item of items(); track item.key) {
            <li [class]="item.state">
              <mat-icon aria-hidden="true">{{ icon(item) }}</mat-icon>
              <div class="text">
                <span class="name">{{ item.file.name }}</span>
                <span class="detail">{{ size(item.file.size) }}{{ item.message ? " · " + item.message : "" }}</span>
                @if (item.state === "uploading") { <mat-progress-bar mode="indeterminate" aria-label="Uploading" /> }
              </div>
              <span class="actions">
                @switch (item.state) {
                  @case ("conflict") {
                    <button matButton type="button" (click)="resolve.emit({key: item.key, choice: 'replace'})">Replace</button>
                    <button matButton type="button" (click)="resolve.emit({key: item.key, choice: 'rename'})">Keep both</button>
                  }
                  @case ("failed") {
                    <button matButton type="button" (click)="retry.emit(item.key)">Retry</button>
                  }
                }
                @if (item.state !== "uploading") {
                  <button matIconButton type="button" [attr.aria-label]="'Remove ' + item.file.name" matTooltip="Remove from list"
                    (click)="remove.emit(item.key)"><mat-icon>close</mat-icon></button>
                }
              </span>
            </li>
          }
        </ul>
      </section>
    }
  `,
  styles: [`
    .queue { border-block-start: 1px solid var(--mat-sys-outline-variant); background: var(--mat-sys-surface-container-low); }
    header { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 4px 8px 0 16px;
      font: var(--mat-sys-title-small); }
    ul { list-style: none; margin: 0; padding: 0 0 8px; max-height: 14rem; overflow: auto; }
    li { display: flex; align-items: center; gap: 12px; padding: 6px 8px 6px 16px; }
    .text { display: grid; flex: 1; min-width: 0; gap: 2px; }
    .name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .detail { font: var(--mat-sys-body-small); color: var(--mat-sys-on-surface-variant); overflow-wrap: anywhere; }
    .actions { display: flex; align-items: center; gap: 4px; flex: none; }
    .failed mat-icon, .conflict mat-icon { color: var(--mat-sys-error); }
    .unknown mat-icon, .unconfirmed mat-icon { color: var(--mat-sys-tertiary); }
    .done mat-icon { color: var(--mat-sys-primary); }
  `],
})
export class UploadQueueComponent {
  readonly items = input.required<QueueItem[]>();
  readonly retry = output<string>();
  readonly remove = output<string>();
  readonly resolve = output<{key: string; choice: "replace" | "rename"}>();
  readonly clear = output<void>();
  protected readonly totals = computed(() => summary(this.items()));
  protected readonly heading = computed(() => {
    const {active, attention, done} = this.totals();
    return [active && `${active} uploading`, attention && `${attention} need attention`, done && `${done} done`].filter(Boolean).join(" · ");
  });
  protected icon(item: QueueItem): string {
    return {uploading: "upload", done: "check_circle", unconfirmed: "help", conflict: "content_copy", failed: "error", unknown: "help"}[item.state];
  }
  protected size(n: number): string {
    const units = ["B", "KB", "MB", "GB"]; let value = n, unit = 0;
    while (value >= 1000 && unit < units.length - 1) { value /= 1000; unit++; }
    return `${unit === 0 || value >= 100 ? Math.round(value) : value.toFixed(1)} ${units[unit]}`;
  }
}
