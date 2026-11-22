import { Component } from "@angular/core";
import { RouterLink, RouterLinkActive, RouterOutlet } from "@angular/router";
import { MatTabLink, MatTabNav, MatTabNavPanel } from "@angular/material/tabs";

@Component({
  imports: [RouterLink, RouterLinkActive, RouterOutlet, MatTabLink, MatTabNav, MatTabNavPanel],
  templateUrl: "./settings-page.html",
  styleUrl: "./settings-page.css",
})
export class SettingsPage {}
