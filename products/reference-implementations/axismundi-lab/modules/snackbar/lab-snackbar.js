/* ============================================================
 * lab-snackbar.js — Axismundi Snackbar Runtime Module
 * v3.4.10
 *
 * Bucket D — theme interaction runtime, lab module only.
 *
 * Scope: window.labSnackbar.show() / dismiss() / dismissAll() API.
 *        Auto-init creates the live region + host wrapper on
 *        DOMContentLoaded.
 *
 * Public API:
 *   window.labSnackbar.show(message, options)
 *   window.labSnackbar.dismiss()
 *   window.labSnackbar.dismissAll()
 *
 * Hard rules (audit §5.2) enforced by this code:
 *   1. Visible snackbar is NEVER given aria-hidden.
 *   2. Timeout pauses while pointer hovers or keyboard focus is
 *      inside the snackbar.
 *   3. Action and close are real <button> elements.
 *   4. role="alert" is not used; live region uses role="status"
 *      + aria-live="polite" + aria-atomic="true".
 *   5. Live region announces text-only feedback (no nested buttons).
 *
 * Forbidden-ancestor policy (audit §7):
 *   show() rejects when the optional `trigger` element provided
 *   (or document.activeElement at call time) is inside a forbidden
 *   ancestor surface (.prose / WordPress block content /
 *   contenteditable). The snackbar host itself is always at
 *   <body> scope.
 *
 * Audit: modules/snackbar/docs/SNACKBAR-RUNTIME-AUDIT.md
 * ============================================================ */
(function () {
  "use strict";

  // ---------- Shared helpers ----------
  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  const FORBIDDEN_ANCESTOR_SELECTOR =
    '.prose, .wp-block-post-content, .entry-content, ' +
    '[contenteditable=""], [contenteditable="true"]';

  function isInForbiddenAncestor(el) {
    if (!el || !el.closest) return false;
    return !!el.closest(FORBIDDEN_ANCESTOR_SELECTOR);
  }

  // ---------- LiveRegion (single stable instance) ----------
  // Single hidden live region with role="status", aria-live="polite",
  // aria-atomic="true". Created once at init, reused for every
  // snackbar announcement. Text-only — never contains buttons.
  // (Hard rule 5)

  const LiveRegion = (function () {
    let regionEl = null;

    function ensure() {
      if (regionEl) return regionEl;
      regionEl = document.createElement("div");
      regionEl.className = "lab-snackbar-live";
      regionEl.setAttribute("role", "status");
      regionEl.setAttribute("aria-live", "polite");
      regionEl.setAttribute("aria-atomic", "true");
      document.body.appendChild(regionEl);
      return regionEl;
    }

    function announce(text) {
      const region = ensure();
      // Force a reset cycle to ensure repeat announcements of the
      // same text still fire on assistive tech.
      region.textContent = "";
      // requestAnimationFrame is enough; some screen readers need
      // one paint between clears and the new text.
      requestAnimationFrame(function () {
        region.textContent = text;
      });
    }

    function clear() {
      if (regionEl) regionEl.textContent = "";
    }

    return { ensure, announce, clear };
  })();

  // ---------- TimeoutController ----------
  // Per-snackbar timeout with hover/focus pause support.
  // (Hard rule 2)

  function TimeoutController(duration, onTimeout) {
    let remaining = duration;
    let startedAt = 0;
    let handle = null;
    let paused = true;

    function start() {
      if (duration <= 0) return; // persistent
      paused = false;
      startedAt = Date.now();
      handle = window.setTimeout(function () {
        handle = null;
        onTimeout();
      }, remaining);
    }

    function pause() {
      if (paused || duration <= 0) return;
      if (handle != null) {
        window.clearTimeout(handle);
        handle = null;
      }
      remaining = Math.max(0, remaining - (Date.now() - startedAt));
      paused = true;
    }

    function resume() {
      if (!paused || duration <= 0) return;
      start();
    }

    function cancel() {
      if (handle != null) {
        window.clearTimeout(handle);
        handle = null;
      }
      paused = true;
    }

    return { start, pause, resume, cancel };
  }

  // ---------- Snackbar DOM builder ----------
  // Builds the visible snackbar element. Returns the root <div>.
  // Hard rule 1: NEVER sets aria-hidden on the root.

  function buildSnackbar(opts) {
    const root = document.createElement("div");
    root.className = "snackbar lab-snackbar";
    // No aria-hidden. (Hard rule 1)

    const label = document.createElement("span");
    label.className = "snackbar__label";
    label.textContent = opts.message;
    root.appendChild(label);

    if (opts.actionText) {
      const actionBtn = document.createElement("button");
      actionBtn.type = "button";
      actionBtn.className = "snackbar__action";
      actionBtn.textContent = opts.actionText;
      root.appendChild(actionBtn);
      root._actionBtn = actionBtn;
    }

    if (opts.close) {
      const closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "snackbar__close";
      closeBtn.setAttribute("aria-label", opts.closeLabel || "Close");

      const icon = document.createElement("span");
      icon.className = "material-symbols-rounded notranslate";
      icon.setAttribute("translate", "no");
      icon.setAttribute("aria-hidden", "true");
      icon.setAttribute("draggable", "false");
      icon.textContent = "close";
      closeBtn.appendChild(icon);

      root.appendChild(closeBtn);
      root._closeBtn = closeBtn;
    }

    return root;
  }

  // ---------- SnackbarQueue (FIFO, single-active) ----------
  // (Hard rule from §4.1: one snackbar at a time, additional snackbars
  //  queue and surface in FIFO order)

  const SnackbarQueue = (function () {
    let host = null;            // The <body>-scope host wrapper.
    let queue = [];             // Pending snackbar options.
    let active = null;          // { root, timeout, opts } currently visible.

    function ensureHost() {
      if (host) return host;
      host = document.createElement("div");
      host.className = "lab-snackbar-host";
      document.body.appendChild(host);
      return host;
    }

    function enqueue(opts) {
      queue.push(opts);
      if (!active) showNext();
    }

    function showNext() {
      if (active) return;
      const opts = queue.shift();
      if (!opts) return;

      const root = buildSnackbar(opts);
      ensureHost().appendChild(root);

      // Force a paint so the .is-open class triggers a transition
      // rather than rendering in the open state.
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          root.classList.add("is-open");
        });
      });

      LiveRegion.announce(opts.message);

      // Wire action and close.
      if (root._actionBtn) {
        root._actionBtn.addEventListener("click", function (evt) {
          if (typeof opts.onAction === "function") {
            try { opts.onAction(evt); } catch (e) { /* swallow */ }
          }
          dismissCurrent();
        });
      }
      if (root._closeBtn) {
        root._closeBtn.addEventListener("click", function () {
          dismissCurrent();
        });
      }

      // Set up timeout with hover/focus pause.
      const timeout = TimeoutController(
        resolveTimeout(opts),
        dismissCurrent
      );
      timeout.start();

      // Pause on hover/focus, resume on leave (audit §4.2, Hard rule 2).
      root.addEventListener("pointerenter", timeout.pause);
      root.addEventListener("pointerleave", timeout.resume);
      root.addEventListener("focusin", timeout.pause);
      root.addEventListener("focusout", function (evt) {
        // Only resume if focus actually left the snackbar subtree.
        if (!root.contains(evt.relatedTarget)) {
          timeout.resume();
        }
      });

      active = { root: root, timeout: timeout, opts: opts };
    }

    function dismissCurrent() {
      if (!active) return;
      const root = active.root;
      const timeout = active.timeout;
      active = null;

      timeout.cancel();
      root.classList.remove("is-open");
      root.classList.add("is-leaving");

      // Wait for exit transition (or fallback at 400ms).
      let removed = false;
      function cleanup() {
        if (removed) return;
        removed = true;
        if (root.parentNode) root.parentNode.removeChild(root);
        // Show next from queue after cleanup.
        showNext();
      }
      root.addEventListener("transitionend", cleanup, { once: true });
      window.setTimeout(cleanup, 400);
    }

    function dismissAll() {
      queue = [];
      dismissCurrent();
    }

    return {
      enqueue: enqueue,
      dismissCurrent: dismissCurrent,
      dismissAll: dismissAll
    };
  })();

  // ---------- Default timeout resolver ----------
  // Per audit §4.2: omitted timeout follows actionText/close presence;
  // explicit timeout (including 0) overrides.

  function resolveTimeout(opts) {
    if (typeof opts.timeout === "number") return opts.timeout;
    if (opts.close) return 0;          // persistent with explicit close
    if (opts.actionText) return 7000;  // with action
    return 5000;                       // message-only
  }

  // ---------- Public API ----------

  function show(message, options) {
    options = options || {};

    // Forbidden-ancestor trigger check (audit §7).
    const trigger = options.trigger || document.activeElement;
    if (trigger && isInForbiddenAncestor(trigger)) {
      // Reject: do not show snackbar when invoked from forbidden surface.
      return false;
    }

    SnackbarQueue.enqueue({
      message: String(message != null ? message : ""),
      actionText: options.actionText || null,
      onAction: typeof options.onAction === "function" ? options.onAction : null,
      close: !!options.close,
      closeLabel: options.closeLabel || null,
      timeout: options.timeout
    });
    return true;
  }

  function dismiss() {
    SnackbarQueue.dismissCurrent();
  }

  function dismissAll() {
    SnackbarQueue.dismissAll();
  }

  window.labSnackbar = {
    show: show,
    dismiss: dismiss,
    dismissAll: dismissAll
  };

  // ---------- Bootstrap ----------
  // Ensure live region exists at <body> scope on ready, so that the
  // first call to show() doesn't have to allocate it lazily.

  onReady(function () {
    // Forbidden-ancestor bail-out for the entire module is moot at
    // <body> scope (body is never inside .prose). But we still
    // ensure the host + live region creation happens cleanly.
    LiveRegion.ensure();
  });
})();
