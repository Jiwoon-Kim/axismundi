/* ============================================================
 * lab-popover.js — Axismundi Popover/Menu lab module
 * v3.4.5
 *
 * Bucket D — theme interaction runtime, lab module only.
 * NOT promoted to baseline theme interaction layer.
 * Scope: lab/modules/popover/lab-popover-pattern.html only.
 *
 * Reimplementation of benchmark-interactions.js Beer-CSS-derived
 * menu/popover functions (makeMenu, positionMenu, openBenchmarkMenu,
 * closeBenchmarkMenu, enableAnchoredMenuDemos, enableSplitButtonMenus),
 * fixing 9 issues identified in POPOVER-AUDIT.md §"Inventory":
 *   - global always-on listeners → open-scoped attach/detach
 *   - missing forbidden-ancestor bail-out (.prose, [contenteditable])
 *   - incomplete focus restoration (Escape only) → universal
 *   - click → pointerdown for outside dismiss
 *   - rAF-deferred outside-listener attach (no stopPropagation reliance)
 *   - inline SVG chevron → Material Symbols icon font
 *   - styleguide-specific selectors → declarative [data-popover-trigger]
 *   - missing activeElement capture
 *   - inconsistent ARIA triad → enforced on every open
 * ============================================================ */
(function () {
  "use strict";

  // ---------- Forbidden-ancestor list (Charter §5 + BEER-CSS-INTAKE rule 5) ----------
  var FORBIDDEN_ANCESTOR_SELECTOR =
    '.prose, [contenteditable=""], [contenteditable="true"]';

  // ---------- Module-scoped open state (single source of truth) ----------
  var openMenu = null;
  var openTrigger = null;
  var previousFocus = null;

  // ---------- Module-scoped listener refs (so we can detach exactly what we attached) ----------
  var outsidePointerListener = null;
  var escapeKeyListener = null;
  var resizeListener = null;
  var scrollListener = null;

  // ---------- Utilities ----------
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  /**
   * Forbidden-ancestor bail-out. Returns true if the element sits inside
   * a federation-content / editor-content surface and must not trigger
   * theme interaction.
   */
  function isInForbiddenAncestor(element) {
    if (!element || !element.closest) return false;
    return !!element.closest(FORBIDDEN_ANCESTOR_SELECTOR);
  }

  // ---------- Anchored positioning ----------
  /**
   * Position `menu` anchored to `trigger`. Chooses top or bottom placement
   * based on viewport space; clamps horizontally inside the viewport with
   * a 16px gap. Preserves the trigger's right edge as the menu's right edge
   * (matches M3 "anchored to trailing corner" pattern).
   */
  function positionMenu(trigger, menu) {
    var rect = trigger.getBoundingClientRect();
    var menuRect = menu.getBoundingClientRect();
    var gap = 8;
    var viewportGap = 16;
    var opensUp = rect.bottom + gap + menuRect.height > window.innerHeight;

    var left = rect.right - menuRect.width;
    left = Math.max(viewportGap, Math.min(left, window.innerWidth - menuRect.width - viewportGap));

    var top = opensUp
      ? Math.max(viewportGap, rect.top - gap - menuRect.height)
      : Math.min(rect.bottom + gap, window.innerHeight - menuRect.height - viewportGap);

    menu.style.left = left + "px";
    menu.style.top = top + "px";
    menu.dataset.placement = opensUp ? "top" : "bottom";
  }

  // ---------- Open / close ----------
  function close() {
    if (!openMenu) return;

    // 1. Visual close
    openMenu.classList.remove("is-open");

    // 2. ARIA sync on trigger
    if (openTrigger) {
      openTrigger.setAttribute("aria-expanded", "false");
      openTrigger.classList.remove("is-selected");
    }

    // 3. Detach all open-scoped listeners
    detachOpenListeners();

    // 4. Focus restoration — universal (not just Escape)
    //    Prefer the trigger as restoration target (M3 menu-button pattern);
    //    fall back to whatever was focused before open.
    var restoreTarget = openTrigger || previousFocus;

    // 5. Clear state
    openMenu = null;
    openTrigger = null;
    var captured = previousFocus;
    previousFocus = null;

    // 6. Restore focus (defer to allow visual close to commit first)
    if (restoreTarget && typeof restoreTarget.focus === "function") {
      try { restoreTarget.focus({ preventScroll: true }); } catch (e) { /* noop */ }
    } else if (captured && typeof captured.focus === "function") {
      try { captured.focus({ preventScroll: true }); } catch (e) { /* noop */ }
    }
  }

  function open(trigger, menu) {
    if (!trigger || !menu) return;

    // Close any prior menu first (single-open invariant)
    if (openMenu && openMenu !== menu) close();
    if (openMenu === menu) return; // idempotent

    // Capture pre-open focus BEFORE we move it
    previousFocus = document.activeElement;

    // Establish state
    openMenu = menu;
    openTrigger = trigger;

    // ARIA sync — enforced every open
    trigger.setAttribute("aria-expanded", "true");
    trigger.classList.add("is-selected");
    if (menu.id && !trigger.getAttribute("aria-controls")) {
      trigger.setAttribute("aria-controls", menu.id);
    }

    // Visual open (must be before positionMenu — positioning reads layout)
    menu.classList.add("is-open");
    positionMenu(trigger, menu);

    // Move focus to first non-disabled menuitem
    var firstItem = qs('.ax-menu__item:not(:disabled), [role="menuitem"]:not(:disabled)', menu);
    if (firstItem) {
      try { firstItem.focus({ preventScroll: true }); } catch (e) { /* noop */ }
    }

    // Attach open-scoped listeners on the NEXT animation frame.
    // This is the rAF-deferred-attach pattern: the pointerdown that
    // triggered open has already propagated by the time these listeners
    // exist, so it cannot also be the dismiss event.
    requestAnimationFrame(function () {
      attachOpenListeners();
    });
  }

  // ---------- Open-scoped listener attach / detach ----------
  function attachOpenListeners() {
    // Outside-pointerdown dismiss. pointerdown fires earlier than click
    // and is more reliable across input types (touch, mouse, pen).
    outsidePointerListener = function (event) {
      if (!openMenu) return;
      if (openMenu.contains(event.target)) return;
      if (openTrigger && openTrigger.contains(event.target)) return;
      close();
    };
    document.addEventListener("pointerdown", outsidePointerListener, true);

    // Single-step Escape dismiss. Different from search-expansion's
    // two-step Escape — popover/menu is a transient command surface.
    escapeKeyListener = function (event) {
      if (event.key === "Escape" || event.key === "Esc") {
        event.stopPropagation();
        close();
      }
    };
    document.addEventListener("keydown", escapeKeyListener, true);

    // Reposition on viewport changes (only while open)
    resizeListener = function () {
      if (openMenu && openTrigger) positionMenu(openTrigger, openMenu);
    };
    window.addEventListener("resize", resizeListener);

    scrollListener = function () {
      if (openMenu && openTrigger) positionMenu(openTrigger, openMenu);
    };
    window.addEventListener("scroll", scrollListener, true);
  }

  function detachOpenListeners() {
    if (outsidePointerListener) {
      document.removeEventListener("pointerdown", outsidePointerListener, true);
      outsidePointerListener = null;
    }
    if (escapeKeyListener) {
      document.removeEventListener("keydown", escapeKeyListener, true);
      escapeKeyListener = null;
    }
    if (resizeListener) {
      window.removeEventListener("resize", resizeListener);
      resizeListener = null;
    }
    if (scrollListener) {
      window.removeEventListener("scroll", scrollListener, true);
      scrollListener = null;
    }
  }

  // ---------- Keyboard navigation within an open menu ----------
  function onMenuKeydown(event) {
    if (!openMenu || event.currentTarget !== openMenu) return;
    var items = qsa('.ax-menu__item:not(:disabled), [role="menuitem"]:not(:disabled)', openMenu);
    if (items.length === 0) return;
    var activeIdx = items.indexOf(document.activeElement);

    if (event.key === "ArrowDown") {
      event.preventDefault();
      var nextIdx = activeIdx < 0 ? 0 : (activeIdx + 1) % items.length;
      items[nextIdx].focus({ preventScroll: true });
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      var prevIdx = activeIdx <= 0 ? items.length - 1 : activeIdx - 1;
      items[prevIdx].focus({ preventScroll: true });
    } else if (event.key === "Home") {
      event.preventDefault();
      items[0].focus({ preventScroll: true });
    } else if (event.key === "End") {
      event.preventDefault();
      items[items.length - 1].focus({ preventScroll: true });
    } else if (event.key === "Tab") {
      // Tab dismisses the menu (M3 pattern — menus are transient)
      close();
    }
  }

  // ---------- Public API: wire all [data-popover-trigger] within root ----------
  /**
   * Wire all popover triggers within `root` (default: document).
   * Idempotent — re-running on the same root is safe.
   */
  function init(root) {
    root = root || document;
    var triggers = qsa('[data-popover-trigger]', root);

    triggers.forEach(function (trigger) {
      if (trigger.dataset.popoverWired === "true") return; // idempotent guard
      trigger.dataset.popoverWired = "true";

      var menuId = trigger.getAttribute("aria-controls");
      if (!menuId) return; // malformed trigger — no target
      var menu = document.getElementById(menuId);
      if (!menu) return;

      // Enforce ARIA triad on the trigger
      if (!trigger.getAttribute("aria-haspopup")) {
        trigger.setAttribute("aria-haspopup", "menu");
      }
      if (!trigger.getAttribute("aria-expanded")) {
        trigger.setAttribute("aria-expanded", "false");
      }

      // Enforce role on the menu
      if (!menu.getAttribute("role")) {
        menu.setAttribute("role", "menu");
      }

      // Click toggle (with forbidden-ancestor bail-out)
      trigger.addEventListener("click", function (event) {
        if (isInForbiddenAncestor(trigger)) return; // §5 bail-out
        event.preventDefault();
        // No stopPropagation — outside-listener is rAF-deferred, won't catch this event
        if (openMenu === menu) {
          close();
        } else {
          open(trigger, menu);
        }
      });

      // Keyboard activation (Space/Enter on button → same as click,
      // already handled by the click event the browser synthesizes for buttons).
      // ArrowDown on trigger → open + focus first item.
      trigger.addEventListener("keydown", function (event) {
        if (isInForbiddenAncestor(trigger)) return;
        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
          event.preventDefault();
          if (openMenu !== menu) open(trigger, menu);
        }
      });

      // Wire keyboard navigation on the menu itself (once per menu)
      if (!menu.dataset.popoverKeyboardWired) {
        menu.dataset.popoverKeyboardWired = "true";
        menu.addEventListener("keydown", onMenuKeydown);

        // Menu item activation: clicking an item closes the menu and
        // restores focus. The activation handler is up to the caller
        // (data-action, href, etc.) — we only handle dismiss.
        menu.addEventListener("click", function (event) {
          var item = event.target.closest('.ax-menu__item, [role="menuitem"]');
          if (!item || item.disabled) return;
          // Defer close to after the activation handler runs
          requestAnimationFrame(close);
        });
      }
    });
  }

  // ---------- Expose ----------
  window.labPopover = {
    init: init,
    close: close,
    // Read-only state inspectors (for audit / debugging)
    get isOpen() { return openMenu !== null; },
    get openMenuId() { return openMenu ? openMenu.id : null; },
  };

  // Auto-init if the pattern HTML signals readiness
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () { init(); });
  } else {
    init();
  }
})();
