import { Component, computed, input, linkedSignal } from "@angular/core";
import { MatIcon } from "@angular/material/icon";

@Component({
  selector: "app-contact-avatar",
  imports: [MatIcon],
  template: `
    @if (source() && !failed()) {
      <img [src]="source()" alt="" referrerpolicy="no-referrer" loading="lazy" (error)="failed.set(true)">
    } @else if (icon(); as symbol) {
      <mat-icon aria-hidden="true">{{ symbol }}</mat-icon>
    } @else {
      <span aria-hidden="true">{{ initials() }}</span>
    }
  `,
  styles: [`
    :host { display: inline-grid; place-items: center; inline-size: var(--avatar-size, 32px);
      block-size: var(--avatar-size, 32px); flex: none; border-radius: 50%; overflow: hidden;
      background: var(--mat-sys-secondary-container); color: var(--mat-sys-on-secondary-container); }
    img { width: 100%; height: 100%; object-fit: cover; }
  `],
})
export class ContactAvatar {
  readonly name = input("");
  readonly kind = input("individual");
  readonly source = input<string | null | undefined>(null);
  protected readonly failed = linkedSignal(() => { this.source(); return false; });
  protected readonly icon = computed(() => {
    switch (this.kind()) {
      case "individual": return null;
      case "group": return "groups";
      case "org": return "business";
      case "location": return "place";
      case "device": return "devices";
      case "application": return "apps";
      default: return "contacts";
    }
  });
  protected readonly initials = computed(() => this.name().trim().split(/\s+/u).slice(0, 2)
    .map(word => Array.from(word)[0] ?? "").join("").toLocaleUpperCase() || "?");
}
