/*
 * Axismundi — lab-carousel.js
 * v3.3.2 — Carousel Lab Module (extraction)
 *
 * EXTRACTED from `scripts/benchmark-interactions.js`:
 *   - qs / qsa / onReady utility functions (L9-18 in original)
 *   - clamp() helper (L458 in original)
 *   - enableMaterialYouSliders() (L462-749 in original)
 *
 * The original section in benchmark-interactions.js remains in
 * place with an EXTRACTED comment marker (v3.3.2 promotion-policy
 * audit trail).
 *
 * Lineage: Material You Slider demo. See compare/Material You
 * Slider.html for the visual pattern reference. Axismundi tokens
 * and component classes were already mature when benchmark was
 * built, so this layer's selectors / state machine / motion
 * curves are more refined than the original reference.
 *
 * Loaded by: lab-carousel-pattern.html ONLY.
 * NOT loaded by main style-guide.html — lab-internal isolation.
 *
 * Functional contract (high level):
 *   - Multi / Hero / Uncontained layout modes (M3 spec)
 *   - 1..5 slides-per-view in Multi mode
 *   - Optional Compact (smaller item size profile)
 *   - Pointer drag, keyboard arrow nav, ResizeObserver/resize
 *   - Material You "morphing" item shape (small ↔ large between
 *     scroll positions), via scroll-snap + transform
 *
 * Promotion criteria — see docs/CAROUSEL-AUDIT.md.
 * Ontology + theme/plugin territory — see docs/CAROUSEL-ONTOLOGY-CHECK.md.
 * Visual QA checklist — see docs/CAROUSEL-VISUAL-QA.md.
 */

(function () {
  // --- Shared utilities (copied from benchmark-interactions.js) ---
  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const reducedMotionQuery = window.matchMedia
    ? window.matchMedia("(prefers-reduced-motion: reduce)")
    : null;


  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }

  function enableMaterialYouSliders() {
    qsa("[data-material-slider]").forEach((demo) => {
      const viewport = qs(".ax-material-slider", demo);
      const track = qs(".ax-material-slider__track", demo);
      const slides = qsa(".ax-material-slide", demo);
      if (!viewport || !track || !slides.length || demo.dataset.materialReady === "true") return;
      demo.dataset.materialReady = "true";

      const state = {
        index: 0,
        slidesPerView: 2,
        layout: "multi",
        centered: false,
        reducedMotion: Boolean(reducedMotionQuery && reducedMotionQuery.matches),
        slideSize: 0,
        slideSizes: [],
        slideOffsets: [],
        gap: 16,
        dragStartX: 0,
        dragStartOffset: 0,
        dragOffset: 0,
        dragging: false
      };

      const prevButton = qs("[data-material-prev]", demo);
      const nextButton = qs("[data-material-next]", demo);
      const centeredToggle = qs("[data-material-centered]", demo);
      const layoutButtons = qsa("[data-material-layout]", demo);
      const spvButtons = qsa("[data-material-spv]", demo);
      const dots = qs(".ax-material-slider-demo__dots", demo);
      const sizeClasses = [
        "ax-carousel__item--large",
        "ax-carousel__item--medium",
        "ax-carousel__item--small"
      ];
      const profiles = {
        1: ["large", "small"],
        2: ["large", "medium", "small"],
        3: ["large", "large", "medium", "small"],
        4: ["large", "large", "medium", "medium", "small"],
        5: ["large", "large", "large", "medium", "medium", "small"]
      };
      const weights = { large: 1, medium: 0.72, small: 0.28 };
      const layoutClasses = [
        "is-layout-multi",
        "is-layout-hero",
        "is-layout-uncontained",
        "ax-carousel--hero",
        "ax-carousel--uncontained"
      ];

      qsa("img", viewport).forEach((image) => {
        image.draggable = false;
      });

      if (dots && !dots.children.length) {
        slides.forEach((_, index) => {
          const dot = document.createElement("button");
          dot.type = "button";
          dot.className = "ax-material-slider-demo__dot";
          dot.setAttribute("aria-label", `Go to slide ${index + 1}`);
          dot.addEventListener("click", () => {
            state.index = index;
            render();
          });
          dots.appendChild(dot);
        });
      }

      function maxIndex() {
        return Math.max(0, slides.length - 1);
      }

      function targetOffset(index = state.index) {
        const offset = state.slideOffsets[index] || 0;
        if (state.centered) {
          const size = state.slideSizes[index] || state.slideSize;
          return viewport.clientWidth / 2 - size / 2 - offset;
        }
        return -offset;
      }

      function applyLayoutClasses() {
        viewport.classList.remove(...layoutClasses);
        viewport.classList.add("is-enhanced");
        viewport.classList.add(`is-layout-${state.layout}`);
        viewport.classList.toggle("is-reduced-motion", state.reducedMotion);
        if (state.layout === "hero") viewport.classList.add("ax-carousel--hero");
        if (state.layout === "uncontained") viewport.classList.add("ax-carousel--uncontained");
      }

      function currentProfile(perView) {
        if (state.layout === "hero") return ["large", "small", "small", "small"];
        if (state.layout === "uncontained") {
          return Array.from({ length: Math.max(5, perView + 2) }, () => "medium");
        }
        return profiles[perView] || profiles[2];
      }

      function fixedSizeForRole(role, width) {
        if (state.layout === "hero") {
          if (role === "large") return clamp(width * 0.8, 280, 480);
          return 80;
        }
        if (state.layout === "uncontained") {
          return role === "small" ? 56 : role === "large" ? 280 : 200;
        }
        return null;
      }

      function measure() {
        applyLayoutClasses();
        const trackStyle = window.getComputedStyle(track);
        state.gap = parseFloat(trackStyle.columnGap || trackStyle.gap) || 0;
        const paddingInline =
          (parseFloat(trackStyle.paddingLeft) || 0) +
          (parseFloat(trackStyle.paddingRight) || 0);
        const width = viewport.clientWidth;
        const perView = clamp(state.slidesPerView, 1, 5);
        const profile = currentProfile(perView);
        const totalWeight = profile.reduce((sum, role) => sum + weights[role], 0);
        const usableWidth = Math.max(0, width - paddingInline - state.gap * (profile.length - 1));
        const unit = Math.max(56, usableWidth / totalWeight);
        let offset = 0;

        state.slideSizes = [];
        state.slideOffsets = [];

        slides.forEach((slide, index) => {
          const slot = index - state.index;
          const role = slot >= 0 && slot < profile.length ? profile[slot] : "small";
          const fixedSize = fixedSizeForRole(role, usableWidth);
          const size = fixedSize || Math.max(role === "small" ? 56 : 112, unit * weights[role]);

          slide.classList.remove(...sizeClasses);
          slide.classList.add(`ax-carousel__item--${role}`);
          slide.dataset.materialRole = role;
          slide.style.setProperty("--ax-material-slide-size", size + "px");

          state.slideSizes[index] = size;
          state.slideOffsets[index] = offset;
          offset += size + state.gap;
        });
        state.slideSize = state.slideSizes[state.index] || unit;
      }

      function materialShape(progress, role) {
        const perView = state.slidesPerView;
        const profile = currentProfile(perView);
        let scale = 0;
        let opacity = 0;
        let shift = 0;

        if (progress <= 0) {
          scale = 1 + progress;
          opacity = Math.pow(Math.max(scale, 0), 4);
        } else if (progress < profile.length) {
          scale = 1;
          opacity = role === "small" ? 0 : 1;
        } else {
          const tailProgress = clamp((profile.length + 1) - progress, 0, 1);
          scale = 0.28 * tailProgress;
          opacity = 0;
          shift = -Math.max(0, progress - profile.length) * (state.slideSize + state.gap);
        }

        scale = clamp(scale, 0.00001, 1);
        return { scale, opacity: clamp(opacity, 0, 1), shift };
      }

      function render(offsetOverride) {
        measure();
        state.index = clamp(state.index, 0, maxIndex());
        const offset = typeof offsetOverride === "number" ? offsetOverride : targetOffset();
        track.style.transform = `translate3d(${offset}px, 0, 0)`;

        const activeSize = state.slideSizes[state.index] || state.slideSize || 1;
        const virtualIndex = state.index + ((targetOffset() - offset) / (activeSize + state.gap));

        slides.forEach((slide, index) => {
          const progress = index - virtualIndex;
          const role = slide.dataset.materialRole || "small";
          const { scale, opacity, shift } = materialShape(progress, role);
          const imageScale = 1 + (1 - scale) * 0.25;

          slide.classList.toggle("is-active", index === state.index);
          slide.style.setProperty("--ax-material-scale", String(scale));
          slide.style.setProperty("--ax-material-opacity", String(opacity));
          slide.style.setProperty("--ax-material-image-scale", String(imageScale));
          slide.style.setProperty("--ax-material-shift", shift.toFixed(2) + "px");
        });

        qsa(".ax-material-slider-demo__dot", dots || demo).forEach((dot, index) => {
          dot.classList.toggle("is-selected", index === state.index);
          dot.setAttribute("aria-current", index === state.index ? "true" : "false");
        });

        spvButtons.forEach((button) => {
          const selected = Number(button.dataset.materialSpv) === state.slidesPerView;
          button.classList.toggle("is-selected", selected);
          button.setAttribute("aria-pressed", selected ? "true" : "false");
        });

        layoutButtons.forEach((button) => {
          const selected = button.dataset.materialLayout === state.layout;
          button.classList.toggle("is-selected", selected);
          button.setAttribute("aria-pressed", selected ? "true" : "false");
        });

        if (prevButton) prevButton.disabled = state.index === 0;
        if (nextButton) nextButton.disabled = state.index === maxIndex();
      }

      function go(delta) {
        state.index = clamp(state.index + delta, 0, maxIndex());
        render();
      }

      spvButtons.forEach((button) => {
        button.addEventListener("click", () => {
          state.slidesPerView = clamp(Number(button.dataset.materialSpv) || 2, 1, 5);
          render();
        });
      });

      layoutButtons.forEach((button) => {
        button.addEventListener("click", () => {
          state.layout = button.dataset.materialLayout || "multi";
          state.index = 0;
          render();
        });
      });

      centeredToggle && centeredToggle.addEventListener("change", () => {
        state.centered = centeredToggle.checked;
        render();
      });

      prevButton && prevButton.addEventListener("click", () => go(-1));
      nextButton && nextButton.addEventListener("click", () => go(1));

      viewport.addEventListener("keydown", (event) => {
        if (event.key === "ArrowLeft") {
          event.preventDefault();
          go(-1);
        }
        if (event.key === "ArrowRight") {
          event.preventDefault();
          go(1);
        }
        if (event.key === "Home") {
          event.preventDefault();
          state.index = 0;
          render();
        }
        if (event.key === "End") {
          event.preventDefault();
          state.index = maxIndex();
          render();
        }
      });

      viewport.addEventListener("pointerdown", (event) => {
        if (event.button && event.button !== 0) return;
        event.preventDefault();
        state.dragging = true;
        state.dragStartX = event.clientX;
        state.dragStartOffset = targetOffset();
        state.dragOffset = state.dragStartOffset;
        viewport.classList.add("is-dragging");
        viewport.setPointerCapture(event.pointerId);
      });

      viewport.addEventListener("pointermove", (event) => {
        if (!state.dragging) return;
        event.preventDefault();
        state.dragOffset = state.dragStartOffset + event.clientX - state.dragStartX;
        render(state.dragOffset);
      });

      function endDrag(event) {
        if (!state.dragging) return;
        state.dragging = false;
        viewport.classList.remove("is-dragging");
        if (viewport.hasPointerCapture(event.pointerId)) viewport.releasePointerCapture(event.pointerId);

        const step = state.slideSize + state.gap;
        const delta = step ? Math.round((state.dragStartOffset - state.dragOffset) / step) : 0;
        state.index = clamp(state.index + delta, 0, maxIndex());
        render();
      }

      viewport.addEventListener("pointerup", endDrag);
      viewport.addEventListener("pointercancel", endDrag);
      viewport.addEventListener("dragstart", (event) => event.preventDefault());

      window.addEventListener("resize", render);
      if (reducedMotionQuery) {
        const syncReducedMotion = (event) => {
          state.reducedMotion = Boolean(event.matches);
          render();
        };
        if (reducedMotionQuery.addEventListener) {
          reducedMotionQuery.addEventListener("change", syncReducedMotion);
        } else if (reducedMotionQuery.addListener) {
          reducedMotionQuery.addListener(syncReducedMotion);
        }
      }
      render();
    });
  }

  // --- Bootstrap ---
  // Auto-init on DOMContentLoaded if this script is loaded. Pattern
  // matches benchmark-interactions.js bootstrap so the module's
  // behavior is consistent whether it's used standalone (via
  // lab-carousel-pattern.html) or eventually merged into a larger
  // bootstrap entry point (post-v3.3.2).
  onReady(() => {
    enableMaterialYouSliders();
  });
})();
