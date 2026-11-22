import { DOCUMENT } from "@angular/common";
import { Component, inject, signal } from "@angular/core";
import { MatRadioButton, MatRadioGroup } from "@angular/material/radio";
import { APPEARANCE_KEY, appearanceAttributes, brightnessOptions, contrastOptions, presets, readAppearance, type Appearance } from "./appearance";

@Component({
  imports: [MatRadioButton, MatRadioGroup],
  templateUrl: "./appearance-page.html",
  styleUrl: "./appearance-page.css",
})
export class AppearancePage {
  private readonly document = inject(DOCUMENT);
  protected readonly brightnessOptions = brightnessOptions;
  protected readonly contrastOptions = contrastOptions;
  protected readonly presets = presets;
  protected readonly appearance = signal(readAppearance(JSON.stringify(this.document.documentElement.dataset)));
  protected readonly storageFailed = signal(false);

  protected change<K extends keyof Appearance>(key: K, value: Appearance[K]): void {
    const next = {...this.appearance(), [key]: value};
    this.appearance.set(next);
    Object.assign(this.document.documentElement.dataset, appearanceAttributes(next));
    try {
      localStorage.setItem(APPEARANCE_KEY, JSON.stringify(next));
      this.storageFailed.set(false);
    } catch {
      this.storageFailed.set(true);
    }
  }
}
