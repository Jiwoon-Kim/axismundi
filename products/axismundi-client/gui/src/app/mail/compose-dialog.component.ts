import { Component, DestroyRef, inject, signal } from "@angular/core";
import { FormField, form } from "@angular/forms/signals";
import { MatButton } from "@angular/material/button";
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
}
export interface ComposeResult {
  outcome: "saved" | "sent" | "saveRejected" | "saveUnknown" | "sendRejected" | "sendUnknown";
  draftId: string | null;
  pendingCleanup: string[];
  reason: string | null;
}
export interface ComposeData {
  identities: ComposeIdentity[];
  mode: ComposeMode;
  source?: ComposeSource;
  write: (draft: ComposeDraft, previous: string[], send: boolean) => Promise<ComposeResult>;
  discard: (ids: string[]) => Promise<boolean>;
}

@Component({
  selector: "app-compose-dialog",
  imports: [FormField, MatButton, MatDialogActions, MatDialogContent, MatDialogTitle, MatFormField,
    MatInput, MatLabel, MatOption, MatSelect],
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
  }

  private snapshot(): string { return JSON.stringify([this.identity().id, this.model()]); }

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
    if (this.locked() || this.snapshot() === this.clean) { this.dialog.close(false); return; }
    if (await this.persist(false)) this.dialog.close(false);
    else this.confirmClose.set(true);
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
    const submitted = this.snapshot();
    this.busy.set(true);
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
      }, this.latest, send);
      if (result.draftId) {
        this.latest = [result.draftId, ...result.pendingCleanup];
        this.clean = submitted;
      }
      switch (result.outcome) {
        case "sent": this.dialog.close(true); return true;
        case "saved": return true;
        case "saveUnknown": this.latest = []; this.notice.set("Could not confirm the save. Check Drafts."); return false;
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
      if (this.changedWhileBusy) { this.changedWhileBusy = false; this.queue(); }
    }
  }
}

