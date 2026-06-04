(() => {
  const TEMPLATE_SELECTOR = ".wp-block-comment-template";
  const ITEM_SELECTOR = ":scope > .omph-comment-item";
  const AVATAR_SELECTOR = ":scope > .wp-block-avatar";
  const SVG_NS = "http://www.w3.org/2000/svg";

  const makeSvg = (width, height) => {
    const svg = document.createElementNS(SVG_NS, "svg");
    svg.setAttribute("class", "omph-comment-connectors");
    svg.setAttribute("aria-hidden", "true");
    svg.setAttribute("focusable", "false");
    svg.setAttribute("width", String(width));
    svg.setAttribute("height", String(height));
    svg.setAttribute("viewBox", `0 0 ${width} ${height}`);
    return svg;
  };

  const makePath = (d) => {
    const path = document.createElementNS(SVG_NS, "path");
    path.setAttribute("d", d);
    path.setAttribute("class", "omph-comment-connector");
    return path;
  };

  const roundedConnector = (railX, startY, childX, childY) => {
    const direction = childX >= railX ? 1 : -1;
    const radius = Math.min(10, Math.abs(childX - railX) / 2, Math.abs(childY - startY) / 2);
    const curveStartY = childY - radius;
    const curveEndX = railX + (radius * direction);

    if (radius <= 0) {
      return `M ${railX} ${startY} V ${childY} H ${childX}`;
    }

    return [
      `M ${railX} ${startY}`,
      `V ${curveStartY}`,
      `Q ${railX} ${childY} ${curveEndX} ${childY}`,
      `H ${childX}`,
    ].join(" ");
  };

  const directChildren = (ol) => Array.from(ol.children).filter((child) => child.tagName === "LI");

  const avatarFor = (li) => li.querySelector(ITEM_SELECTOR)?.querySelector(AVATAR_SELECTOR);

  const renderTemplate = (template) => {
    const previous = template.querySelector(":scope > .omph-comment-connectors");
    if (previous) {
      previous.remove();
    }

    const templateRect = template.getBoundingClientRect();
    const width = Math.max(template.scrollWidth, Math.ceil(templateRect.width));
    const height = Math.max(template.scrollHeight, Math.ceil(templateRect.height));
    const svg = makeSvg(width, height);
    let pathCount = 0;

    for (const list of template.querySelectorAll("ol")) {
      const parentLi = list.parentElement?.closest("li");
      const parentAvatar = parentLi ? avatarFor(parentLi) : null;
      if (!parentAvatar) {
        continue;
      }

      const parentRect = parentAvatar.getBoundingClientRect();
      const railX = Math.round(parentRect.left - templateRect.left + (parentRect.width / 2));
      const startY = Math.round(parentRect.bottom - templateRect.top);

      for (const childLi of directChildren(list)) {
        const childAvatar = avatarFor(childLi);
        if (!childAvatar) {
          continue;
        }

        const childRect = childAvatar.getBoundingClientRect();
        const childX = Math.round(childRect.left - templateRect.left);
        const childY = Math.round(childRect.top - templateRect.top + (childRect.height / 2));

        if (childY <= startY) {
          continue;
        }

        svg.appendChild(makePath(roundedConnector(railX, startY, childX, childY)));
        pathCount += 1;
      }
    }

    if (pathCount > 0) {
      template.classList.add("has-omph-comment-connectors");
      template.prepend(svg);
    } else {
      template.classList.remove("has-omph-comment-connectors");
    }
  };

  const renderAll = () => {
    document.querySelectorAll(TEMPLATE_SELECTOR).forEach(renderTemplate);
  };

  const disableCoreReplyMove = () => {
    document.addEventListener("click", (event) => {
      const replyLink = event.target.closest(".wp-block-comment-reply-link a");
      if (!replyLink) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
    }, true);
  };

  const scheduleRender = () => {
    let frame = 0;
    return () => {
      if (frame) {
        cancelAnimationFrame(frame);
      }
      frame = requestAnimationFrame(() => {
        frame = 0;
        renderAll();
      });
    };
  };

  const init = () => {
    if (!document.querySelector(TEMPLATE_SELECTOR)) {
      return;
    }

    const rerender = scheduleRender();
    disableCoreReplyMove();
    rerender();

    window.addEventListener("resize", rerender, { passive: true });
    window.addEventListener("load", rerender, { once: true });

    if ("ResizeObserver" in window) {
      const observer = new ResizeObserver(rerender);
      document.querySelectorAll(TEMPLATE_SELECTOR).forEach((template) => observer.observe(template));
    }
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
