/* ============================================================
 * lab-tooltip.js — Axismundi Tooltip lab module
 * v3.4.6
 *
 * Bucket D — theme interaction runtime, lab module only.
 * NOT promoted to baseline theme interaction layer.
 * Scope: lab/modules/tooltip/lab-tooltip-pattern.html only.
 *
 * Reimplementation of benchmark-interactions.js Beer-CSS-derived
 * tooltip functions (createTooltip, positionTooltip, enableTooltips),
 * fixing 5 issues identified in TOOLTIP-AUDIT.md §a11y risk register
 * and applying the Phase 0 decisions:
 *   - Decision B: selector narrowed to [data-tooltip], .ax-icon-button[aria-label]
 *     (.ax-button[aria-label] removed — text-button tooltips would duplicate
 *      the visible label)
 *   - Decision Y: hover show delay / touch long-press deferred to BACKLOG #16
 *
 * Five fixes vs. benchmark originals:
 *   1. aria-describedby was 0 occurrences in benchmark — now wired on
 *      show, removed on hide (critical a11y gap closed)
 *   2. Forbidden-ancestor bail-out (.prose / [contenteditable]) added on
 *      every trigger event path (Charter §5)
 *   3. Tier A / Tier B listener split — scroll/resize/keydown attach only
 *      while tooltip is visible (BEER-CSS-INTAKE rule 4 partial compliance)
 *   4. pointerout relatedTarget check — pointer moving from trigger into
 *      tooltip body does NOT trigger hide (preserves rich tooltip
 *      interaction if/when rich is wired in a later phase)
 *   5. Focus never moves into tooltip — trigger keeps focus throughout
 *
 * Out of scope at v3.4.6 (per Decision Y / BACKLOG #16):
 *   - hover show delay
 *   - touch long-press trigger
 *   - rich tooltip interactive wiring (rich variants in pattern HTML
 *     are visual specimens only at v3.4.6)
 * ============================================================ */
(function () {
  "use strict";

  // ---------- Trigger selector (Decision B) ----------
  // Order matters for text priority: data-tooltip wins over aria-label.
  var TOOLTIP_TRIGGER_SELECTOR =
    "[data-tooltip], .ax-icon-button[aria-label]";

  // ---------- Forbidden-ancestor list (Charter §5 + BEER-CSS-INTAKE rule 5) ----------
  var FORBIDDEN_ANCESTOR_SELECTOR =
    '.prose, [contenteditable=""], [contenteditable="true"]';

  // ---------- Module-scoped state ----------
  var tooltipEl = null;     // single reusable tooltip DOM element
  var activeTrigger = null; // currently-described trigger, or null
  var TOOLTIP_ID = "lab-tooltip-singleton";

  // ---------- Tier B listener refs (attached only while visible) ----------
  var scrollListener = null;
  var resizeListener = null;
  var escapeListener = null;

  // ---------- Utilities ----------
  function isInForbiddenAncestor(element) {
    if (!element || !element.closest) return false;
    return !!element.closest(FORBIDDEN_ANCESTOR_SELECTOR);
  }

  function getTooltipText(trigger) {
    // Decision B priority: data-tooltip > aria-label
    return trigger.getAttribute("data-tooltip") ||
           trigger.getAttribute("aria-label") ||
           "";
  }

  // ---------- Single-element creation (once at init) ----------
  function ensureTooltipElement() {
    if (tooltipEl) return tooltipEl;
    tooltipEl = document.createElement("span");
    tooltipEl.className = "ax-tooltip is-plain";
    tooltipEl.setAttribute("role", "tooltip");
    tooltipEl.id = TOOLTIP_ID;
    // Position-fixed + hidden by default (components.css handles visibility)
    tooltipEl.style.position = "fixed";
    document.body.appendChild(tooltipEl);
    return tooltipEl;
  }

  // ---------- Anchored positioning ----------
  function positionTooltip(trigger, tooltip) {
    var rect = trigger.getBoundingClientRect();
    var tipRect = tooltip.getBoundingClientRect();
    var gap = 8;
    var viewportGap = 16;

    // Center horizontally over the trigger; clamp inside viewport
    var left = rect.left + rect.width / 2 - tipRect.width / 2;
    left = Math.max(viewportGap,
                    Math.min(left, window.innerWidth - tipRect.width - viewportGap));

    // Prefer top placement; fall back to bottom if it would overflow viewport top
    var top = rect.top - tipRect.height - gap;
    if (top < viewportGap) {
      top = rect.bottom + gap;
    }

    tooltip.style.left = left + "px";
    tooltip.style.top = top + "px";
  }

  // ---------- Show / hide ----------
  function show(trigger) {
    if (!trigger) return;

    // Forbidden-ancestor bail-out (Charter §5)
    if (isInForbiddenAncestor(trigger)) return;

    var text = getTooltipText(trigger);
    if (!text) return;

    var tooltip = ensureTooltipElement();

    // Already showing for this exact trigger — idempotent
    if (activeTrigger === trigger && tooltip.classList.contains("is-open")) {
      return;
    }

    // If a different trigger is active, hide it first (single-tooltip invariant)
    if (activeTrigger && activeTrigger !== trigger) {
      hide(activeTrigger);
    }

    // Set content + ARIA + position + visible
    tooltip.textContent = text;
    activeTrigger = trigger;

    // aria-describedby lifecycle — defensive: only set if trigger doesn't already
    // carry a different describedby. If it does, we don't clobber; the existing
    // reference takes precedence and the tooltip just won't be announced —
    // accepted trade-off vs. data loss for external ARIA wiring.
    var existing = trigger.getAttribute("aria-describedby");
    if (!existing || existing === TOOLTIP_ID) {
      trigger.setAttribute("aria-describedby", TOOLTIP_ID);
    }

    // Show visually
    tooltip.classList.add("is-open");

    // Position (must happen after class add — reads layout)
    positionTooltip(trigger, tooltip);

    // Attach Tier B listeners (visible-scoped)
    attachVisibleListeners();
  }

  function hide(trigger) {
    if (!tooltipEl) return;
    // Allow hide(null) as "hide whatever is active"
    if (trigger && activeTrigger !== trigger) return;

    var prevTrigger = activeTrigger;

    // Visual hide first
    tooltipEl.classList.remove("is-open");

    // aria-describedby teardown — only remove if value still matches our ID
    // (defensive against external code that may have changed it)
    if (prevTrigger) {
      if (prevTrigger.getAttribute("aria-describedby") === TOOLTIP_ID) {
        prevTrigger.removeAttribute("aria-describedby");
      }
    }

    // Detach Tier B listeners
    detachVisibleListeners();

    activeTrigger = null;
  }

  // ---------- Tier B listeners (attached only while visible) ----------
  function attachVisibleListeners() {
    if (scrollListener) return; // already attached — idempotent

    scrollListener = function () {
      if (activeTrigger && tooltipEl) positionTooltip(activeTrigger, tooltipEl);
    };
    window.addEventListener("scroll", scrollListener, true);

    resizeListener = function () {
      if (activeTrigger && tooltipEl) positionTooltip(activeTrigger, tooltipEl);
    };
    window.addEventListener("resize", resizeListener);

    // Escape dismiss — plain tooltip too, defensive against stuck-visible state
    escapeListener = function (event) {
      if (event.key === "Escape" || event.key === "Esc") {
        // Don't stopPropagation — other handlers may want Escape too
        hide(null);
      }
    };
    document.addEventListener("keydown", escapeListener, true);
  }

  function detachVisibleListeners() {
    if (scrollListener) {
      window.removeEventListener("scroll", scrollListener, true);
      scrollListener = null;
    }
    if (resizeListener) {
      window.removeEventListener("resize", resizeListener);
      resizeListener = null;
    }
    if (escapeListener) {
      document.removeEventListener("keydown", escapeListener, true);
      escapeListener = null;
    }
  }

  // ---------- Tier A listeners (always-on, delegated) ----------
  function onPointerOver(event) {
    var trigger = event.target.closest(TOOLTIP_TRIGGER_SELECTOR);
    if (!trigger) return;
    show(trigger);
  }

  function onPointerOut(event) {
    var trigger = event.target.closest(TOOLTIP_TRIGGER_SELECTOR);
    if (!trigger) return;
    if (trigger !== activeTrigger) return;

    // Self-hover preservation: if pointer is moving FROM trigger INTO the
    // tooltip body, don't hide. This protects future rich-tooltip
    // interaction even though v3.4.6 doesn't wire rich tooltips.
    var related = event.relatedTarget;
    if (related && tooltipEl && tooltipEl.contains(related)) return;

    // Don't hide if pointer is still over the trigger (event bubbling from
    // child elements within the trigger fires pointerout incorrectly).
    if (related && trigger.contains(related)) return;

    hide(trigger);
  }

  function onFocusIn(event) {
    var trigger = event.target.closest(TOOLTIP_TRIGGER_SELECTOR);
    if (!trigger) return;
    show(trigger);
  }

  function onFocusOut(event) {
    var trigger = event.target.closest(TOOLTIP_TRIGGER_SELECTOR);
    if (!trigger) return;
    if (trigger !== activeTrigger) return;
    hide(trigger);
  }

  function attachAlwaysOnListeners() {
    document.addEventListener("pointerover", onPointerOver, true);
    document.addEventListener("pointerout",  onPointerOut,  true);
    document.addEventListener("focusin",     onFocusIn,     true);
    document.addEventListener("focusout",    onFocusOut,    true);
  }

  // ---------- Public API ----------
  function init() {
    ensureTooltipElement();
    attachAlwaysOnListeners();
  }

  window.labTooltip = {
    init: init,
    show: show,
    hide: hide,
    // Read-only state inspectors (for audit / debugging)
    get isVisible() { return !!(tooltipEl && tooltipEl.classList.contains("is-open")); },
    get activeTriggerId() {
      return activeTrigger && activeTrigger.id ? activeTrigger.id : null;
    },
  };

  // Auto-init on DOMContentLoaded
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
