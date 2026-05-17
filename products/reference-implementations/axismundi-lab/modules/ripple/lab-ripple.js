/*
 * Axismundi — lab-ripple.js
 * v3.5.6 — Ripple v2 Contract + data-ax-ripple Opt-In
 *
 * This module implements the v2 ripple provider contract documented in
 * docs/RIPPLE-V2-AUDIT.md. It is Axismundi-native code aligned with the
 * Material Web ripple contract shape, but it does not import <md-ripple>
 * or @material/web.
 *
 * Stable public contract:
 *   [data-ax-ripple]                 bounded ripple (default)
 *   [data-ax-ripple="bounded"]       bounded ripple
 *   [data-ax-ripple="unbounded"]     centered/unbounded ripple
 *   window.axRipple.attach(control, options?)
 *   window.axRipple.detach(control)
 *   window.axRipple.refresh(root?)
 *
 * Transitional compatibility:
 *   The legacy v3.3.3 HOST_SELECTOR allowlist is still attached during
 *   refresh() so existing lab specimens continue to work. This is migration
 *   compatibility, not the stable authoring API.
 *
 * Loaded by: lab-ripple-pattern.html ONLY.
 * NOT loaded by main style-guide.html.
 */

(function () {
  "use strict";

  const HOST_SELECTOR = [
    ".ax-button",
    ".ax-icon-button",
    ".chip",
    ".ax-menu__item",
    ".nav-bar__item",
    ".nav-rail__item",
    "[role='tab']"
  ].join(",");

  const OPT_IN_SELECTOR = "[data-ax-ripple]";
  const FORBIDDEN_ANCESTORS = ".prose, .wp-block-post-content, .entry-content, [contenteditable]";
  const REDUCED_MOTION = !!(
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  );

  const attached = new WeakMap();

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  function toElement(control) {
    return control && control.nodeType === 1 ? control : null;
  }

  function isDisabled(el) {
    return (
      el.disabled === true ||
      el.getAttribute("aria-disabled") === "true" ||
      el.hasAttribute("disabled")
    );
  }

  function isForbidden(el) {
    return !!(el && el.closest && el.closest(FORBIDDEN_ANCESTORS));
  }

  function normalizeMode(value) {
    return value === "unbounded" ? "unbounded" : "bounded";
  }

  function modeFromControl(control, options) {
    if (options && options.mode) return normalizeMode(options.mode);
    if (control.hasAttribute("data-ax-ripple")) {
      return normalizeMode(control.getAttribute("data-ax-ripple"));
    }
    return "bounded";
  }

  function providerClasses(mode) {
    return [
      "ax-ripple-host",
      mode === "unbounded" ? "ax-ripple-host--unbounded" : "ax-ripple-host--bounded"
    ];
  }

  function applyProviderClasses(control, mode) {
    providerClasses(mode).forEach((className) => control.classList.add(className));
  }

  function removeProviderClasses(control) {
    control.classList.remove(
      "ax-ripple-host",
      "ax-ripple-host--bounded",
      "ax-ripple-host--unbounded"
    );
  }

  function createRipple(control, event, mode) {
    const rect = control.getBoundingClientRect();
    const max = Math.max(rect.width, rect.height);
    const size = mode === "unbounded" ? max * 2 : max;
    const x = mode === "unbounded"
      ? (rect.width - size) / 2
      : event.clientX - rect.left - size / 2;
    const y = mode === "unbounded"
      ? (rect.height - size) / 2
      : event.clientY - rect.top - size / 2;

    const ripple = document.createElement("span");
    ripple.className = "ax-ripple";
    ripple.setAttribute("aria-hidden", "true");
    ripple.style.setProperty("--ax-ripple-size", size + "px");
    ripple.style.setProperty("--ax-ripple-x", x + "px");
    ripple.style.setProperty("--ax-ripple-y", y + "px");

    let removed = false;
    const remove = () => {
      if (removed) return;
      removed = true;
      ripple.remove();
    };

    ripple.addEventListener("animationend", remove, { once: true });
    ripple.addEventListener("transitionend", remove, { once: true });
    window.setTimeout(remove, REDUCED_MOTION ? 500 : 900);

    control.appendChild(ripple);

    if (REDUCED_MOTION) {
      requestAnimationFrame(() => {
        ripple.classList.add("is-fading");
      });
    }
  }

  function onPointerDown(event) {
    if (event.button && event.button !== 0) return;

    const record = attached.get(event.currentTarget);
    if (!record) return;

    const control = record.control;
    if (isDisabled(control) || isForbidden(event.target) || isForbidden(control)) return;

    createRipple(control, event, record.mode);
  }

  /**
   * Attach ripple behavior to a single control.
   *
   * @param {Element} control
   * @param {{ mode?: "bounded" | "unbounded", legacy?: boolean }} [options]
   * @returns {Element|null}
   */
  function attach(control, options) {
    const el = toElement(control);
    if (!el || isForbidden(el)) return null;

    const mode = modeFromControl(el, options);
    const existing = attached.get(el);

    if (existing) {
      existing.mode = mode;
      existing.legacy = !!(options && options.legacy);
      removeProviderClasses(el);
      applyProviderClasses(el, mode);
      return el;
    }

    const record = {
      control: el,
      mode,
      legacy: !!(options && options.legacy),
      listener: onPointerDown
    };

    attached.set(el, record);
    applyProviderClasses(el, mode);
    el.addEventListener("pointerdown", record.listener, { passive: true });
    return el;
  }

  /**
   * Detach ripple behavior from a single control.
   *
   * @param {Element} control
   * @returns {boolean}
   */
  function detach(control) {
    const el = toElement(control);
    if (!el) return false;

    const record = attached.get(el);
    if (!record) return false;

    el.removeEventListener("pointerdown", record.listener);
    removeProviderClasses(el);
    attached.delete(el);
    return true;
  }

  /**
   * Attach ripple behavior to declarative hosts under root.
   *
   * @param {ParentNode} [root=document]
   * @returns {Element[]}
   */
  function refresh(root) {
    const scope = root && root.querySelectorAll ? root : document;
    const controls = [];
    const seen = new Set();

    scope.querySelectorAll(OPT_IN_SELECTOR).forEach((control) => {
      if (seen.has(control)) return;
      seen.add(control);
      const attachedControl = attach(control);
      if (attachedControl) controls.push(attachedControl);
    });

    scope.querySelectorAll(HOST_SELECTOR).forEach((control) => {
      if (seen.has(control)) return;
      seen.add(control);
      const attachedControl = attach(control, { legacy: true });
      if (attachedControl) controls.push(attachedControl);
    });

    return controls;
  }

  window.axRipple = {
    attach,
    detach,
    refresh
  };

  onReady(() => {
    const controls = refresh(document);
    if (window.console && window.console.debug) {
      window.console.debug(
        "[lab-ripple v3.5.6] ready — controls=" +
          controls.length +
          " reduced-motion=" +
          REDUCED_MOTION
      );
    }
  });
})();
