import { Component, ElementRef, TemplateRef, computed, effect, inject, input, linkedSignal, signal, viewChildren } from "@angular/core";
import { NgTemplateOutlet } from "@angular/common";
import { MatButton, MatIconButton } from "@angular/material/button";
import { MatDialog, MatDialogActions, MatDialogClose, MatDialogContent, MatDialogTitle } from "@angular/material/dialog";
import { MatTooltip } from "@angular/material/tooltip";
import { MatIcon } from "@angular/material/icon";
import { invoke } from "@tauri-apps/api/core";
import { openUrl } from "@tauri-apps/plugin-opener";
import { SafeHtmlFrameComponent, cleanMarkup, supportsCredentialless } from "../safe-html-frame.component";
import type { BodyScheme } from "../settings/mail-body-theme";
import { type Attachment, type Body, chooseBodyParts } from "./mail-detail";
import { cidReference, contentId, externalLink, remoteImage } from "./mail-html";

const RASTER = new Set(["image/png", "image/jpeg", "image/gif", "image/webp", "image/avif", "image/bmp"]);
const IMAGE_LIMIT = 2 * 1024 * 1024;

@Component({
  selector: "app-mail-body",
  imports: [SafeHtmlFrameComponent, MatButton, MatIconButton, MatIcon, MatTooltip, NgTemplateOutlet,
    MatDialogActions, MatDialogClose, MatDialogContent, MatDialogTitle],
  templateUrl: "./mail-body.component.html",
  styles: [`
    :host { display: block; min-inline-size: 0; }
    .body { margin: 16px 0; white-space: pre-wrap; overflow-wrap: anywhere; font: var(--mat-sys-body-large); }
    .notice { padding: 8px 12px; border-radius: var(--mat-sys-corner-small); background: var(--mat-sys-surface-container-high);
      color: var(--mat-sys-on-surface-variant); font: var(--mat-sys-body-small); }
    .attachment-section { margin-inline: -24px; padding: 12px 24px; border-block-end: 1px solid var(--mat-sys-outline-variant); }
    .attachment-section h3 { display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
      margin: 0 0 10px; font: var(--mat-sys-title-small); }
    .attachment-section h3 span, .attachment-size { color: var(--mat-sys-on-surface-variant); font: var(--mat-sys-body-small); }
    .attachments { display: flex; flex-wrap: wrap; gap: 8px; padding: 0; margin: 0; list-style: none; }
    .attachment-card { display: flex; align-items: center; gap: 10px; box-sizing: border-box;
      inline-size: min(100%, 272px); min-block-size: 56px; padding: 7px 8px;
      border: 1px solid var(--mat-sys-outline-variant); border-radius: var(--mat-sys-corner-small);
      background: var(--mat-sys-surface-container-low); }
    .attachment-type { display: grid; place-items: center; flex: none; inline-size: 40px; block-size: 40px;
      border-radius: var(--mat-sys-corner-small); background: var(--mat-sys-secondary-container); color: var(--mat-sys-on-secondary-container); }
    .attachment-meta { display: grid; min-inline-size: 0; gap: 2px; font: var(--mat-sys-body-medium); }
    .attachment-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .attachment-meta { flex: 1; }
    .attachment-main { display: flex; align-items: center; flex: 1; min-inline-size: 0; gap: 8px; }
    button.attachment-main { background: none; border: 0; padding: 0; text-align: start; color: inherit; cursor: pointer; }
    .attachment-thumb { flex: none; inline-size: 40px; block-size: 40px; object-fit: cover; border-radius: var(--mat-sys-corner-small); }
    .preview-image { display: block; max-width: 100%; max-height: 70vh; object-fit: contain; }
    .remote-banner { display: flex; align-items: flex-start; gap: 12px; margin: 0 -24px;
      padding: 12px 24px; border-block-end: 1px solid var(--mat-sys-outline-variant);
      background: var(--mat-sys-surface-container); font: var(--mat-sys-body-medium); }
    .remote-banner > mat-icon { padding: 8px; border-radius: 50%; background: var(--mat-sys-secondary-container);
      color: var(--mat-sys-on-secondary-container); flex: none; }
    .remote-banner strong { display: block; margin-block-end: 4px; }
  `],
})
export class MailBodyComponent {
  readonly accountId = input.required<string>();
  readonly connectionId = input.required<number>();
  readonly emailId = input.required<string>();
  readonly body = input.required<Body>();
  readonly attachments = input.required<Attachment[]>();
  readonly scheme = input.required<BodyScheme>();
  protected readonly canShowRemote = supportsCredentialless();
  private readonly dialog = inject(MatDialog);
  private readonly cards = viewChildren<ElementRef<HTMLElement>>("card");
  private readonly scope = computed(() => JSON.stringify([this.connectionId(), this.accountId(), this.emailId()]));
  protected readonly allowRemote = linkedSignal({source: this.scope, computation: () => false});
  protected readonly linkFailed = linkedSignal({source: this.scope, computation: () => false});
  protected readonly thumbnails = linkedSignal({
    source: this.scope, computation: () => new Map<string, string | null>(),
  });
  protected readonly exporting = linkedSignal({
    source: this.scope, computation: () => new Set<string>(),
  });
  protected readonly exportStatus = linkedSignal({source: this.scope, computation: () => ""});
  protected readonly actualSize = signal(false);
  private readonly downloaded = signal<{scope: string; data: Map<string, string>} | null>(null);
  private readonly cleaned = computed(() => new Map(this.body().parts.filter(part => part.kind === "html")
    .map(part => [part.value, cleanMarkup(part.value)])));
  protected readonly parts = computed(() => chooseBodyParts(this.body(), value => !!this.cleaned().get(value)));
  private readonly imageRefs = computed(() => this.parts().flatMap(part =>
    part.kind === "html" ? this.cleaned().get(part.value)?.images ?? [] : []));
  protected readonly hasRemote = computed(() => this.imageRefs().some(url => !!remoteImage(url)) ||
    this.parts().some(part => part.kind === "html" && this.cleaned().get(part.value)?.hasCssImages));
  private readonly referenced = computed(() => {
    const ids = new Set(this.imageRefs().map(cidReference).filter(id => id !== null));
    const candidates = new Map<string, Attachment | null>();
    for (const part of this.attachments()) {
      if (!part.cid) continue;
      const cid = contentId(part.cid);
      if (ids.has(cid)) candidates.set(cid, candidates.has(cid) ? null : part);
    }
    return [...candidates.values()].filter(part => part !== null);
  });
  protected readonly visibleAttachments = computed(() => {
    const inline = this.referenced();
    return this.attachments().filter(part => !inline.includes(part) || !part.type || !RASTER.has(part.type));
  });
  protected readonly attachmentTotal = computed(() => {
    const items = this.visibleAttachments();
    return items.length && items.every(part => part.size !== null)
      ? this.formatSize(items.reduce((total, part) => total + part.size!, 0)) : null;
  });

  protected formatSize(bytes: number): string {
    const units = ["B", "KB", "MB", "GB", "TB"];
    let unit = 0;
    while (bytes >= 1024 && unit < units.length - 1) {
      bytes /= 1024;
      unit++;
    }
    return `${new Intl.NumberFormat(undefined, {maximumFractionDigits: unit === 0 ? 0 : 1}).format(bytes)} ${units[unit]}`;
  }

  protected attachmentIcon(type: string | null): string {
    if (type?.startsWith("image/")) return "image";
    if (type === "application/pdf") return "picture_as_pdf";
    return "draft";
  }

  protected canPreview(part: Attachment): boolean {
    return !!part.partId && !!part.type && RASTER.has(part.type) && part.size !== null && part.size <= 10_000_000;
  }

  protected canOpen(part: Attachment): boolean {
    const ext = /\.([^.]+)$/.exec(part.name ?? "")?.[1].toLowerCase();
    const allowed: Record<string, string[]> = {
      "application/pdf": ["pdf"], "text/plain": ["txt"], "image/jpeg": ["jpg", "jpeg"], "image/png": ["png"],
      "image/gif": ["gif"], "image/webp": ["webp"], "image/avif": ["avif"], "image/bmp": ["bmp"],
    };
    return !!part.partId && !!ext && !!part.type && !!allowed[part.type]?.includes(ext);
  }

  private partArgs(partId: string) {
    return {connectionId: this.connectionId(), accountId: this.accountId(), emailId: this.emailId(), partId};
  }

  protected preview(part: Attachment, template: TemplateRef<unknown>): void {
    const url = this.thumbnails().get(part.partId!);
    if (!url) return;
    this.actualSize.set(false);
    this.dialog.open(template, {data: {url, name: part.name || "Attachment"}, maxWidth: "95vw"});
  }

  protected async exportPart(part: Attachment, open: boolean): Promise<void> {
    const id = part.partId;
    if (!id || this.exporting().has(id)) return;
    const scope = this.scope();
    this.exportStatus.set("");
    this.exporting.update(busy => new Set(busy).add(id));
    try {
      const saved = await invoke<boolean>("mail_attachment_export", {...this.partArgs(id), open});
      if (scope === this.scope() && saved) this.exportStatus.set(open ? "Attachment opened." : "Attachment saved.");
    } catch (error) {
      if (scope === this.scope()) this.exportStatus.set(String(error));
    } finally {
      if (scope === this.scope()) this.exporting.update(busy => new Set([...busy].filter(key => key !== id)));
    }
  }

  protected readonly resolveResource = computed(() => {
    const stored = this.downloaded(), allowed = this.allowRemote();
    const inline = stored?.scope === this.scope() ? stored.data : null;
    return (url: string) => {
      const cid = cidReference(url);
      return cid ? inline?.get(cid) ?? null : allowed ? remoteImage(url) : null;
    };
  });

  protected readonly openLink = (url: string) => {
    const allowed = externalLink(url);
    if (allowed) void openUrl(allowed).catch(() => this.linkFailed.set(true));
  };

  constructor() {
    effect(onCleanup => {
      const scope = this.scope(), items = this.visibleAttachments(), cards = this.cards();
      this.thumbnails.set(new Map());
      const urls: string[] = [];
      let canceled = false;
      const observer = new IntersectionObserver(entries => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          observer.unobserve(entry.target);
          const part = items[cards.findIndex(card => card.nativeElement === entry.target)];
          if (!part || !this.canPreview(part)) continue;
          const id = part.partId!;
          void invoke<ArrayBuffer>("mail_part_bytes", {...this.partArgs(id), preview: true})
            .then(bytes => {
              if (canceled || scope !== this.scope()) return;
              const url = URL.createObjectURL(new Blob([bytes], {type: part.type!}));
              urls.push(url);
              this.thumbnails.update(old => new Map(old).set(id, url));
            }).catch(() => {
              if (!canceled && scope === this.scope()) this.thumbnails.update(old => new Map(old).set(id, null));
            });
        }
      });
      cards.forEach(card => observer.observe(card.nativeElement));
      onCleanup(() => {
        canceled = true;
        observer.disconnect();
        urls.forEach(url => URL.revokeObjectURL(url));
      });
    });
    effect(onCleanup => {
      const scope = this.scope();
      const params = {connectionId: this.connectionId(), accountId: this.accountId(), emailId: this.emailId()};
      const parts = this.referenced().filter(part => part.partId && part.type && RASTER.has(part.type)
        && part.size !== null && part.size <= IMAGE_LIMIT).slice(0, 16);
      let canceled = false;
      onCleanup(() => { canceled = true; });
      this.downloaded.set(null);
      if (!parts.length) return;
      void (async () => {
        const data = new Map<string, string>();
        let remaining = 8 * 1024 * 1024;
        for (const part of parts) {
          if (canceled) return;
          if (part.size! > remaining) continue;
          try {
            const bytes = new Uint8Array(await invoke<ArrayBuffer>("mail_part_bytes", {...params, partId: part.partId}));
            if (!bytes.length || bytes.length > IMAGE_LIMIT || bytes.length > remaining) continue;
            remaining -= bytes.length;
            let raw = "";
            for (let start = 0; start < bytes.length; start += 8192) raw += String.fromCharCode(...bytes.subarray(start, start + 8192));
            data.set(contentId(part.cid!), `data:${part.type};base64,${btoa(raw)}`);
          } catch { /* Keep the image's alt text when this part is unavailable. */ }
        }
        if (!canceled) this.downloaded.set({scope, data});
      })();
    });
  }
}
