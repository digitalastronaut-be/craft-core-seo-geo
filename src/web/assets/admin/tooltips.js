import { computePosition, flip, offset, shift } from "@floating-ui/dom";

const WRAP_SELECTOR = ".core-seo-geo-info-icon-wrap";

/**
 * @param {Element} wrap
 * @returns {HTMLElement|null}
 */
function popupFor(wrap) {
	return wrap.querySelector(".core-seo-geo-info-icon-popup");
}

/**
 * @param {Element} wrap
 * @returns {Promise<void>}
 */
async function showPopup(wrap) {
	const popup = popupFor(wrap);
	if (!popup || popup.matches(":popover-open")) return;

	popup.showPopover();

	const { x, y } = await computePosition(wrap, popup, {
		strategy: "fixed",
		placement: "top",
		middleware: [offset(8), flip(), shift({ padding: 8 })],
	});

	Object.assign(popup.style, { left: `${x}px`, top: `${y}px` });
}

/**
 * @param {Element} wrap
 * @returns {void}
 */
function hidePopup(wrap) {
	const popup = popupFor(wrap);
	if (popup?.matches(":popover-open")) popup.hidePopover();
}

document.addEventListener("mouseover", (event) => {
	const wrap = event.target.closest(WRAP_SELECTOR);
	if (!wrap || (event.relatedTarget && wrap.contains(event.relatedTarget))) return;

	showPopup(wrap);
});

document.addEventListener("mouseout", (event) => {
	const wrap = event.target.closest(WRAP_SELECTOR);
	if (!wrap || (event.relatedTarget && wrap.contains(event.relatedTarget))) return;

	hidePopup(wrap);
});

document.addEventListener("focusin", (event) => {
	const wrap = event.target.closest(WRAP_SELECTOR);
	if (wrap) showPopup(wrap);
});

document.addEventListener("focusout", (event) => {
	const wrap = event.target.closest(WRAP_SELECTOR);
	if (wrap) hidePopup(wrap);
});
