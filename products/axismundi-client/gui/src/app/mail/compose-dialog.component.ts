import { Component, DestroyRef, computed, inject, signal } from "@angular/core";
import { FormField, form } from "@angular/forms/signals";
import { MatButton, MatIconButton } from "@angular/material/button";
import { MatIcon } from "@angular/material/icon";
import { MatProgressSpinner } from "@angular/material/progress-spinner";
import { MatTooltip } from "@angular/material/tooltip";
import { listen } from "@tauri-apps/api/event";
import { draftAttachments, fitTotal, formatSize, markMissing, picked, restored, sendBlocker, unsavedAttachments,
  type ComposeAttachment, type DraftAttachment, type PickedFile, type UploadedFile } from "./compose-attachments";
import { MAT_DIALOG_DATA, MatDialogActions, MatDialogContent, MatDialogRef, MatDialogTitle } from "@angular/material/dialog";
import { MatFormField, MatLabel } from "@angular/material/form-field";
import { MatInput } from "@angular/material/input";
import { MatOption, MatSelect } from "@angular/material/select";
import { recipientInput, replyAllRecipients, sameAddress, type ComposeAddress } from "./compose-addresses";

export interface ComposeIdentity { id: string; name: string; email: string }
export type ComposeMode = "new" | "draft" | "reply" | "replyAll" | "forward";
export interface ComposeSource {
  id: string;
  from: ComposeAddress[] | null;
  replyTo: ComposeAddress[] | null;
  to: ComposeAddress[] | null;
  cc: ComposeAddress[] | null;
  bcc: ComposeAddress[] | null;
  subject: string | null;
  body: string;
  messageId: string[] | null;
  inReplyTo: string[] | null;
  references: string[] | null;
  isDraft: boolean;
  attachments: DraftAttachment[];
}
export interface ComposeDraft {
  identityId: string;
  wildcardAddress: string | null;
  to: ComposeAddress[];
  cc: ComposeAddress[];
  bcc: ComposeAddress[];
  subject: string;
  body: string;
  inReplyTo: string[];
  references: string[];
  attachments: DraftAttachment[];
}
export interface ComposeResult {
  outcome: "saved" | "sent" | "saveRejected" | "saveUnknown" | "sendRejected" | "sendUnknown" | "missingBlobs";
  draftId: string | null;
  pendingCleanup: string[];
  reason: string | null;
  missing: string[];
}
export interface ComposeData {
  identities: ComposeIdentity[];
  mode: ComposeMode;
  source?: ComposeSource;
  write: (draft: ComposeDraft, previous: string[], send: boolean) => Promise<ComposeResult>;
  discard: (ids: string[]) => Promise<boolean>;
  /** Opens the OS file picker; the files come back as handles. */
  pick: () => Promise<PickedFile[]>;
  upload: (handle: string) => Promise<UploadedFile>;
  release: (handles: string[]) => Promise<void>;
  /** The Account's maxSizeAttachmentsPerEmail, or null when the server does not say. */
  totalLimit: () => Promise<number | null>;
}

@Component({
  selector: "app-compose-dialog",
  imports: [FormField, MatButton, MatDialogActions, MatDialogContent, MatDialogTitle, MatFormField,
    MatIcon, MatIconButton, MatInput, MatLabel, MatOption, MatProgressSpinner, MatSelect, MatTooltip],
  templateUrl: "./compose-dialog.component.html",
  styleUrl: "./compose-dialog.component.css",
})
export class ComposeDialogComponent {
  protected readonly data = inject<ComposeData>(MAT_DIALOG_DATA);
  private readonly dialog = inject<MatDialogRef<ComposeDialogComponent, boolean>>(MatDialogRef);
  protected readonly title = {new: "New message", draft: "Edit draft", reply: "Reply",
    replyAll: "Reply all", forward: "Forward"}[this.data.mode];
  protected readonly identity = signal(this.data.identities.find(choice =>
    [...(this.data.source?.to ?? []), ...(this.data.source?.cc ?? [])].some(a => sameAddress(a.email, choice.email))) ?? this.data.identities[0]);
  private nextKey = 0;
  protected readonly attachments = signal<ComposeAttachment[]>(
    this.data.mode === "draft" ? (this.data.source?.attachments ?? []).map(a => restored(a, `k${this.nextKey++}`)) : []);
  protected readonly blocker = computed(() => sendBlocker(this.attachments()));
  protected readonly uploading = computed(() => this.attachments().filter(item => item.state === "uploading").length);
  protected readonly formatSize = formatSize;
  /** Blob ids in the last draft the server confirmed; a reopened draft's attachments are saved already. */
  private readonly savedBlobs = signal(new Set(this.attachments().flatMap(item => item.blobId ? [item.blobId] : [])));
  protected readonly unsaved = computed(() => unsavedAttachments(this.attachments(), this.savedBlobs()));
  /** True from the moment a send starts: the attachment list it carries is fixed. */
  private readonly sending = signal(false);
  private readonly model = signal(this.initial());
  protected readonly fields = form(this.model);
  protected readonly busy = signal(false);
  protected readonly locked = signal(false);
  protected readonly notice = signal("");
  protected readonly confirmClose = signal(false);
  protected readonly confirmDiscard = signal(false);
  private timer?: ReturnType<typeof setTimeout>;
  private latest: string[] = this.data.mode === "draft" && this.data.source ? [this.data.source.id] : [];
  private clean = this.data.mode === "draft" || this.data.mode === "new" ? this.snapshot() : "";
  private changedWhileBusy = false;

  constructor() {
    inject(DestroyRef).onDestroy(() => clearTimeout(this.timer));
    const escape = this.dialog.keydownEvents().subscribe(event => {
      if (event.key === "Escape") { event.preventDefault(); void this.close(); }
    });
    inject(DestroyRef).onDestroy(() => escape.unsubscribe());
    // A file dropped on the window is registered natively; it arrives here as a handle.
    const unlisten = listen<PickedFile[]>("compose-files-dropped", event => this.add(event.payload));
    inject(DestroyRef).onDestroy(() => {
      void unlisten.then(stop => stop());
      const handles = this.attachments().flatMap(item => item.handle ? [item.handle] : []);
      if (handles.length) void this.data.release(handles);
    });
  }

  /** Only finished uploads are part of what a save would write; an upload in flight is not. */
  private snapshot(): string {
    return JSON.stringify([this.identity().id, this.model(), draftAttachments(this.attachments()).map(a => a.blobId)]);
  }

  protected async attach(): Promise<void> {
    if (this.locked() || this.busy()) return;
    try { this.add(await this.data.pick()); } catch { this.notice.set("Could not open the file picker."); }
  }

  private async add(files: PickedFile[]): Promise<void> {
    if (!files.length) return;
    // A send carries the list it started with; a file dropped meanwhile would show but not go out.
    if (this.sending() || this.locked() || this.confirmClose() || this.confirmDiscard()) {
      void this.data.release(files.map(file => file.handle));
      if (this.sending()) this.notice.set("The message is being sent. Add files after it is done.");
      return;
    }
    let total: number | null = null;
    try { total = await this.data.totalLimit(); } catch { total = null; }
    const {accepted, refused} = fitTotal(this.attachments(), files, total);
    if (refused.length) {
      void this.data.release(refused.map(file => file.handle));
      this.notice.set(refused.length === 1
        ? `"${refused[0].name}" would make the attachments larger than the server accepts for one message.`
        : `${refused.length} files would make the attachments larger than the server accepts for one message.`);
    }
    const added = accepted.map(file => picked(file, `k${this.nextKey++}`));
    this.attachments.update(items => [...items, ...added]);
    for (const item of added) void this.upload(item.key);
  }

  private async upload(key: string): Promise<void> {
    const item = this.attachments().find(entry => entry.key === key);
    if (!item?.handle) return;
    this.attachments.update(items => items.map(entry => entry.key === key ? {...entry, state: "uploading", error: null} : entry));
    try {
      const uploaded = await this.data.upload(item.handle);
      // A removed attachment's late answer is dropped; the server cleans up the unreferenced blob.
      this.attachments.update(items => items.map(entry => entry.key === key
        ? {...entry, state: "uploaded", blobId: uploaded.blobId, size: uploaded.size, type: uploaded.type} : entry));
      this.queue();
    } catch (error) {
      this.attachments.update(items => items.map(entry => entry.key === key
        ? {...entry, state: "failed", error: typeof error === "string" ? error : "Upload failed."} : entry));
    }
  }

  /** A retry counts toward the per-message total like a new file: the others may have grown meanwhile. */
  protected async retry(key: string): Promise<void> {
    const item = this.attachments().find(entry => entry.key === key);
    if (!item?.handle || this.sending()) return;
    let total: number | null = null;
    try { total = await this.data.totalLimit(); } catch { total = null; }
    const others = this.attachments().filter(entry => entry.key !== key);
    const file = {handle: item.handle, name: item.name, size: item.size ?? 0, type: item.type};
    if (fitTotal(others, [file], total).refused.length) {
      this.attachments.update(items => items.map(entry => entry.key === key
        ? {...entry, error: "This file would make the attachments larger than the server accepts for one message."} : entry));
      return;
    }
    void this.upload(key);
  }

  protected remove(key: string): void {
    const item = this.attachments().find(entry => entry.key === key);
    this.attachments.update(items => items.filter(entry => entry.key !== key));
    if (item?.handle) void this.data.release([item.handle]);
    this.queue();
  }

  private initial() {
    const source = this.data.source;
    const mode = this.data.mode;
    const empty = {to: "", cc: "", bcc: "", subject: "", body: "", wildcard: ""};
    if (!source) return empty;
    const addresses = (values: ComposeAddress[] | null) => (values ?? []).map(a => a.email).join(", ");
    if (mode === "draft") {
      const from = source.from?.[0]?.email ?? "";
      const exact = this.data.identities.find(i => sameAddress(i.email, from));
      const wildcard = this.data.identities.find(i => i.email.startsWith("*@") &&
        sameAddress(i.email.slice(1), from.slice(from.lastIndexOf("@"))));
      if (exact || wildcard) this.identity.set((exact ?? wildcard)!);
      return {to: addresses(source.to), cc: addresses(source.cc), bcc: addresses(source.bcc),
        subject: source.subject ?? "", body: source.body, wildcard: wildcard && !exact ? from : ""};
    }
    const prefix = mode === "forward" ? "Fwd:" : "Re:";
    const subject = source.subject ?? "";
    const text = source.body.split(/\r?\n/).map(line => "> " + line).join("\n");
    const from = addresses(source.from);
    const body = mode === "forward"
      ? `\n\n--- Forwarded message ---\nFrom: ${from}\nTo: ${addresses(source.to)}\nSubject: ${subject}\n\n${text}`
      : `\n\n${from}:\n${text}`;
    const reply = source.replyTo?.length ? source.replyTo : source.from ?? [];
    const recipients = mode === "replyAll"
      ? replyAllRecipients(reply, source.to ?? [], source.cc ?? [], this.identity().email) : {to: reply, cc: []};
    return {...empty, subject: subject.toLowerCase().startsWith(prefix.toLowerCase()) ? subject : `${prefix} ${subject}`, body,
      to: mode === "forward" ? "" : addresses(recipients.to), cc: addresses(recipients.cc)};
  }

  protected choose(identity: ComposeIdentity): void {
    const prior = this.data.mode === "replyAll" ? this.initial() : null;
    this.identity.set(identity);
    if (prior) {
      const updated = this.initial();
      this.model.update(value => ({
        ...value,
        to: value.to === prior.to ? updated.to : value.to,
        cc: value.cc === prior.cc ? updated.cc : value.cc,
      }));
    }
    this.queue();
  }

  protected queue(): void {
    clearTimeout(this.timer);
    if (this.locked() || this.confirmClose() || this.confirmDiscard()) return;
    if (this.busy()) { this.changedWhileBusy = true; return; }
    this.timer = setTimeout(() => {
      if (this.snapshot() !== this.clean) void this.persist(false);
    }, 1800);
  }

  protected async close(): Promise<void> {
    if (this.busy()) return;
    if (this.confirmClose() || this.confirmDiscard()) { this.keepWriting(); return; }
    if (this.locked() || (this.snapshot() === this.clean && !this.unsaved())) { this.dialog.close(false); return; }
    // An attachment still uploading or failed cannot be saved now: ask rather than drop it.
    if (this.attachments().some(item => item.state !== "uploaded")) { clearTimeout(this.timer); this.confirmClose.set(true); return; }
    // A save can finish while another upload lands; save again until nothing is left unsaved.
    for (let round = 0; round < 3; round++) {
      if (!(await this.persist(false))) break;
      if (this.snapshot() === this.clean && !this.unsaved()) { this.dialog.close(false); return; }
    }
    this.confirmClose.set(true);
  }

  protected closeAnyway(): void { this.dialog.close(false); }

  protected askDiscard(): void {
    clearTimeout(this.timer);
    this.confirmDiscard.set(true);
  }

  protected keepWriting(): void {
    this.confirmDiscard.set(false);
    this.confirmClose.set(false);
    this.queue();
  }

  protected async discard(): Promise<void> {
    if (this.busy() || this.locked()) return;
    this.busy.set(true);
    try {
      if (!this.latest.length || await this.data.discard(this.latest)) {
        this.dialog.close(false);
        return;
      }
      this.notice.set("Could not confirm the discard. Check Drafts.");
    } catch {
      this.notice.set("Could not confirm the discard. Check Drafts.");
    } finally {
      this.busy.set(false);
      this.confirmDiscard.set(false);
    }
  }

  protected async persist(send: boolean): Promise<boolean> {
    if (this.busy() || this.locked()) return false;
    clearTimeout(this.timer);
    const model = this.model();
    const rawTo = recipientInput(model.to), rawCc = recipientInput(model.cc), bcc = recipientInput(model.bcc);
    if (!rawTo || !rawCc || !bcc) {
      this.notice.set("Enter valid email addresses separated by commas.");
      return false;
    }
    const from = this.identity().email.startsWith("*@") ? model.wildcard.trim() : this.identity().email;
    const accepted = (a: ComposeAddress) => this.data.mode !== "replyAll" || !sameAddress(a.email, from);
    const to = rawTo.filter(accepted), cc = rawCc.filter(accepted);
    const recipients = [...to, ...cc, ...bcc];
    if (send && !recipients.length) { this.notice.set("Add at least one recipient."); return false; }
    const blocker = send ? this.blocker() : null;
    if (blocker) { this.notice.set(blocker); return false; }
    const submitted = this.snapshot();
    const attachments = draftAttachments(this.attachments());
    this.busy.set(true);
    if (send) this.sending.set(true);
    this.notice.set("");
    try {
      const source = this.data.source;
      const replying = this.data.mode === "reply" || this.data.mode === "replyAll";
      const mid = source?.messageId?.slice(0, 1) ?? [];
      const result = await this.data.write({
        identityId: this.identity().id, wildcardAddress: this.identity().email.startsWith("*@") ? model.wildcard.trim() : null,
        to, cc, bcc, subject: model.subject, body: model.body,
        inReplyTo: replying ? mid : this.data.mode === "draft" ? source?.inReplyTo ?? [] : [],
        references: replying ? [...new Set([...(source?.references ?? []), ...mid])]
          : this.data.mode === "draft" ? source?.references ?? [] : [],
        attachments,
      }, this.latest, send);
      if (result.draftId) {
        this.latest = [result.draftId, ...result.pendingCleanup];
        this.clean = submitted;
        this.savedBlobs.set(new Set(attachments.map(a => a.blobId)));
      }
      switch (result.outcome) {
        case "sent": this.dialog.close(true); return true;
        case "saved": return true;
        case "saveUnknown": this.latest = []; this.notice.set("Could not confirm the save. Check Drafts."); return false;
        case "missingBlobs":
          this.attachments.update(items => markMissing(items, result.missing));
          this.notice.set("Some attachments are no longer on the server. Upload them again or remove them.");
          return false;
        case "sendUnknown":
          this.locked.set(true);
          this.notice.set("Could not confirm whether this message was sent. Check Sent and the recipient before taking any action.");
          return false;
        default: this.notice.set(result.reason ?? "The server did not allow this change."); return false;
      }
    } catch {
      if (send) this.locked.set(true);
      this.notice.set(send ? "Could not confirm the operation. Check Sent and Drafts." : "Could not save the draft.");
      return false;
    } finally {
      this.busy.set(false);
      this.sending.set(false);
      if (this.changedWhileBusy) { this.changedWhileBusy = false; this.queue(); }
    }
  }
}

