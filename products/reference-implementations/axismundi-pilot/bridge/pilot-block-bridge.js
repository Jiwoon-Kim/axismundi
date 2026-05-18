/*
 * Axismundi Pilot — pilot-block-bridge.js
 * v3.6.0 Phase 2E
 *
 * Minimal WordPress core block runtime bridge.
 * This is not a custom block and does not promote a new component. It adds
 * Ripple v2-like progressive enhancement to core/button links that already
 * carry Axismundi M3 block styles.
 */

(function () {
  "use strict";

  const BUTTON_SELECTOR = ".wp-block-button__link";
  const REDUCED_MOTION = !!(
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  );

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  function isDisabled(button) {
    return (
      button.getAttribute("aria-disabled") === "true" ||
      button.classList.contains("is-disabled")
    );
  }

  function createRipple(button, event) {
    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
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

    button.appendChild(ripple);

    if (REDUCED_MOTION) {
      requestAnimationFrame(() => {
        ripple.classList.add("is-fading");
      });
    }
  }

  function attachButton(button) {
    if (!button || button.dataset.axPilotRippleAttached === "true") return;

    button.dataset.axRipple = "bounded";
    button.dataset.axPilotRippleAttached = "true";
    button.addEventListener("pointerdown", (event) => {
      if (event.button && event.button !== 0) return;
      if (isDisabled(button)) return;
      createRipple(button, event);
    }, { passive: true });
  }

  onReady(() => {
    document.querySelectorAll(BUTTON_SELECTOR).forEach(attachButton);
  });
})();
