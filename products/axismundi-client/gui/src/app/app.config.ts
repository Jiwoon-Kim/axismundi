import {
  ApplicationConfig,
  inject,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners,
} from "@angular/core";
import { provideRouter } from "@angular/router";
import { MatIconRegistry } from "@angular/material/icon";

import { routes } from "./app.routes";

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    // mat-icon ligatures come from the bundled Material Symbols font (styles.css).
    provideAppInitializer(() => {
      inject(MatIconRegistry).setDefaultFontSetClass("material-symbols-outlined");
    }),
  ],
};
