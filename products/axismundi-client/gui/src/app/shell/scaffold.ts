import { DOCUMENT } from "@angular/common";
import { DestroyRef, Service, type TemplateRef, computed, inject, signal } from "@angular/core";
import { toSignal } from "@angular/core/rxjs-interop";
import { BreakpointObserver } from "@angular/cdk/layout";
import { map } from "rxjs";
import { readAppearance } from "../settings/appearance";
import { scaffoldLayout } from "./scaffold-layout";

/** M3 expanded window size class. */
export const EXPANDED = "(min-width: 840px)";

/** What the routed screen puts into the window-wide bar: a leading button and its search bar. */
export interface AppBarContent { leading: TemplateRef<unknown> | null; search: TemplateRef<unknown> | null }

/**
 * The shell's layout: the interface layout setting (a root data attribute, as the other appearance
 * settings) and the window size class. A screen hands its bar content here; the shell draws it in the
 * window bar only when the bar spans the window, so one search bar is ever shown.
 */
@Service()
export class Scaffold {
  private readonly root = inject(DOCUMENT).documentElement;
  private readonly setting = signal(readAppearance(JSON.stringify(this.root.dataset)).interfaceLayout);
  private readonly expanded = toSignal(inject(BreakpointObserver).observe(EXPANDED).pipe(map(state => state.matches)),
    {requireSync: true});
  readonly layout = computed(() => scaffoldLayout(this.setting(), this.expanded()));
  readonly appBar = signal<AppBarContent | null>(null);

  constructor() {
    const observer = new MutationObserver(() =>
      this.setting.set(readAppearance(JSON.stringify(this.root.dataset)).interfaceLayout));
    observer.observe(this.root, {attributes: true, attributeFilter: ["data-interface-layout"]});
    inject(DestroyRef).onDestroy(() => observer.disconnect());
  }
}
