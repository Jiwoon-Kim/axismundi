import { DOCUMENT } from "@angular/common";
import { DestroyRef, Service, computed, inject, signal } from "@angular/core";
import { readAppearance } from "./appearance";

export type BodyScheme = "light" | "dark";

@Service()
export class MailBodyTheme {
  private readonly root = inject(DOCUMENT).documentElement;
  private readonly media = matchMedia("(prefers-color-scheme: dark)");
  private readonly settings = signal(readAppearance(JSON.stringify(this.root.dataset)));
  private readonly systemDark = signal(this.media.matches);
  readonly scheme = computed<BodyScheme>(() => {
    const {brightness, mailBodyTheme} = this.settings();
    if (mailBodyTheme !== "app") return mailBodyTheme;
    return brightness === "system" ? (this.systemDark() ? "dark" : "light") : brightness;
  });
  readonly tokens = computed(() => {
    this.settings();
    const style = getComputedStyle(this.root);
    return ["surface", "surface-container", "on-surface", "primary", "body-large"].map(name => {
      const token = `--mat-sys-${name}`;
      return `${token}:${style.getPropertyValue(token)};`;
    }).join("");
  });

  constructor() {
    const observer = new MutationObserver(() => this.settings.set(readAppearance(JSON.stringify(this.root.dataset))));
    observer.observe(this.root, {attributes: true, attributeFilter: [
      "data-brightness", "data-contrast", "data-accent", "data-mail-body-theme",
    ]});
    const change = (event: MediaQueryListEvent) => this.systemDark.set(event.matches);
    this.media.addEventListener("change", change);
    inject(DestroyRef).onDestroy(() => {
      observer.disconnect();
      this.media.removeEventListener("change", change);
    });
  }
}
