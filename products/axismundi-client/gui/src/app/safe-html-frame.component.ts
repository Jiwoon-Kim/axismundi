import { Component, DestroyRef, ElementRef, afterRenderEffect, computed, inject, input, signal, untracked, viewChild } from "@angular/core";
import DOMPurify from "dompurify";
import { applyReadingColors } from "./mail-dark-text";
import { MailBodyTheme, type BodyScheme } from "./settings/mail-body-theme";

export interface SafeMarkup { fragment: DocumentFragment; images: string[]; hasCssImages: boolean }

const inlineImage = /^data:image\/(?:png|jpeg|gif|webp|avif|bmp);base64,[A-Za-z0-9+/=]+$/;

function defaultStyles(tokens: string): string {
  return `@layer app-defaults {
    html { ${tokens} background:var(--mat-sys-surface); color:var(--mat-sys-on-surface); }
    body { display:flow-root; margin:12px; font:var(--mat-sys-body-large, 16px system-ui); overflow-wrap:break-word; }
    a { color:var(--mat-sys-primary); }
    a[data-reader-button-link] { text-decoration:none; }
    img:not([width]) { max-width:100%; }
    table:not([style*="max-width"]) { max-width:100%; }
    pre { white-space:pre-wrap; }
  }`;
}

export function supportsCredentialless(): boolean {
  return "credentialless" in HTMLIFrameElement.prototype;
}

export function cleanMarkup(html: string): SafeMarkup | null {
  if (!DOMPurify.isSupported) return null;
  // Parsing into a detached template keeps resource attributes inert before sanitising.
  const template = document.createElement("template");
  template.innerHTML = html;
  const fragment = DOMPurify.sanitize(template.content, {
    RETURN_DOM_FRAGMENT: true,
    ALLOWED_TAGS: ["a", "b", "blockquote", "br", "caption", "center", "code", "col", "colgroup", "dd", "del",
      "div", "dl", "dt", "em", "h1", "h2", "h3", "h4", "h5", "h6", "hr", "i", "img", "li", "ol",
      "font", "p", "pre", "s", "small", "span", "strong", "style", "sub", "sup", "table", "tbody", "td", "tfoot", "th",
      "thead", "tr", "u", "ul"],
    ALLOWED_ATTR: ["align", "alt", "bgcolor", "border", "cellpadding", "cellspacing", "class", "color",
      "colspan", "dir", "face", "height", "href", "id", "lang", "media", "rowspan", "size", "src", "style", "title", "valign", "width"],
    ADD_URI_SAFE_ATTR: ["align", "bgcolor", "border", "cellpadding", "cellspacing", "color", "colspan",
      "face", "height", "media", "rowspan", "size", "valign", "width"],
    ALLOW_DATA_ATTR: false,
    ALLOW_ARIA_ATTR: false,
    ALLOWED_URI_REGEXP: /^(?:https?:|mailto:|cid:)/i,
  });
  if (!fragment.textContent?.trim() && !fragment.querySelector("img[src]")) return null;
  const images = [...fragment.querySelectorAll("img[src]")].map(img => img.getAttribute("src")!);
  const hasCssImages = [...fragment.querySelectorAll("style, [style]")].some(node =>
    /url\s*\(|image-set\s*\(/i.test(node.getAttribute("style") ?? node.textContent ?? ""));
  return {fragment, images, hasCssImages};
}

@Component({
  selector: "app-safe-html-frame",
  template: '<iframe #frame sandbox="allow-same-origin" referrerpolicy="no-referrer" title="Formatted content" [style.height.px]="height()" (load)="loaded()" ></iframe>',
  styles: [':host{display:block;min-inline-size:0} iframe{display:block;width:100%;border:0;background:var(--mat-sys-surface)}'],
})
export class SafeHtmlFrameComponent {
  readonly html = input.required<string>();
  readonly resolveResource = input.required<(url: string) => string | null>();
  readonly openLink = input.required<(url: string) => void>();
  readonly allowRemote = input(false);
  readonly scheme = input<BodyScheme>("light");
  private readonly theme = inject(MailBodyTheme);
  private readonly loadedDocument = signal<Document | null>(null);
  private readonly frame = viewChild.required<ElementRef<HTMLIFrameElement>>("frame");
  protected readonly height = signal(96);
  private stop?: () => void;
  private readonly markup = computed(() => cleanMarkup(this.html()));

  constructor() {
    inject(DestroyRef).onDestroy(() => this.stop?.());
    afterRenderEffect(() => {
      // The only markup sink is this sandboxed document, never the app's DOM.
      const frame = this.frame().nativeElement;
      const markup = this.markup();
      const remote = this.allowRemote() && supportsCredentialless();
      const {resolve, scheme, tokens} = untracked(() => ({
        resolve: this.resolveResource(), scheme: this.scheme(), tokens: this.theme.tokens(),
      }));
      this.loadedDocument.set(null);
      frame.toggleAttribute("credentialless", remote);
      frame.style.colorScheme = scheme;
      this.stop?.();
      const fragment = markup?.fragment.cloneNode(true) as DocumentFragment | undefined;
      for (const img of fragment?.querySelectorAll("img") ?? []) {
        const original = img.getAttribute("src") ?? "";
        const url = resolve(original);
        img.removeAttribute("src");
        if (/^cid:/i.test(original)) img.setAttribute("data-inline-src", original);
        if (url && (inlineImage.test(url) || (remote && /^https:\/\//i.test(url)))) {
          img.setAttribute("src", url);
        } else if (/^https?:\/\//i.test(original)) {
          // A sender's inline display style can override the hidden attribute.
          const blocked = document.createElement("template");
          blocked.setAttribute("data-blocked-image", "");
          img.replaceWith(blocked);
          blocked.content.append(img);
        }
      }
      for (const link of fragment?.querySelectorAll("a[href]") ?? []) {
        link.setAttribute("data-link", link.getAttribute("href")!);
        link.setAttribute("href", "#");
      }
      const serial = document.createElement("template");
      if (fragment) serial.content.append(fragment);
      const font = new URL("/assets/fonts/roboto-flex/axismundi-roboto-flex.woff2", document.baseURI);
      const csp = `default-src 'none'; img-src data:${remote ? " https:" : ""}; style-src 'unsafe-inline'; font-src ${font.href}; form-action 'none'; base-uri 'none'; frame-src 'none'`;
      frame.srcdoc = `<!doctype html><html style="color-scheme:${scheme}"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${csp}"><meta name="referrer" content="no-referrer"><style id="app-defaults">${defaultStyles(tokens)}</style><style>
        @font-face{font-family:"Roboto Flex";src:url("${font.href}") format("woff2");font-weight:100 1000;font-display:swap}
        [data-reader-dark-text]{color:var(--mat-sys-on-surface)!important}
        [data-reader-dark-text="link"]{color:var(--mat-sys-primary)!important}
        [data-reader-dark-panel]{background-color:var(--mat-sys-surface-container)!important}
      </style></head><body>${serial.innerHTML}</body></html>`;
    });
    afterRenderEffect(() => {
      const tokens = this.theme.tokens();
      const defaults = this.loadedDocument()?.head.querySelector("#app-defaults");
      const css = defaultStyles(tokens);
      if (defaults && defaults.textContent !== css) defaults.textContent = css;
    });
    afterRenderEffect(() => {
      const scheme = this.scheme();
      this.frame().nativeElement.style.colorScheme = scheme;
      this.theme.tokens();
      const doc = this.loadedDocument();
      if (!doc) return;
      doc.documentElement.style.colorScheme = scheme;
      for (const name of ["data-reader-dark-text", "data-reader-dark-panel", "data-reader-button-link"]) {
        for (const node of doc.querySelectorAll(`[${name}]`)) node.removeAttribute(name);
      }
      applyReadingColors(doc, scheme === "dark");
    });
    afterRenderEffect(() => {
      const resolve = this.resolveResource();
      for (const img of this.loadedDocument()?.querySelectorAll("img[data-inline-src]") ?? []) {
        const url = resolve(img.getAttribute("data-inline-src")!);
        if (url && inlineImage.test(url) && img.getAttribute("src") !== url) img.setAttribute("src", url);
      }
    });
  }

  protected loaded(): void {
    this.stop?.();
    const frame = this.frame().nativeElement;
    const doc = frame.contentDocument;
    if (!doc?.body || !doc.head.querySelector("#app-defaults")) return;
    this.loadedDocument.set(doc);
    const click = (event: MouseEvent) => {
      const target = event.target as Element | null;
      const link = target?.closest?.("a[data-link]");
      if (!link) return;
      event.preventDefault();
      if (event.type === "click" && event.button === 0) this.openLink()(link.getAttribute("data-link")!);
    };
    doc.addEventListener("click", click, true);
    doc.addEventListener("auxclick", click, true);
    const measure = () => {
      const body = doc.body;
      const style = doc.defaultView?.getComputedStyle(body);
      const top = body.getBoundingClientRect().top + (doc.defaultView?.scrollY ?? 0);
      this.height.set(Math.max(1, Math.ceil(top + Math.max(body.offsetHeight, body.scrollHeight)
        + (parseFloat(style?.marginBottom ?? "") || 0))));
    };
    const resize = new ResizeObserver(measure);
    resize.observe(doc.body);
    doc.addEventListener("load", measure, true);
    measure();
    this.stop = () => {
      resize.disconnect();
      doc.removeEventListener("load", measure, true);
      doc.removeEventListener("click", click, true);
      doc.removeEventListener("auxclick", click, true);
    };
  }
}
