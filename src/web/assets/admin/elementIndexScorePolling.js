const POLL_INTERVAL_MS = 2000;
const PENDING_CLASS = "core-seo-geo-score-chip--pending";

/**
 * One shared poll for however many element index chips need watching, rather than each chip
 * polling on its own -- an index page can show up to 100 rows, and 100 independent per-chip polls
 * every couple seconds (the entry edit sidebar pane's own approach, fine for a single field) would
 * mean 100 requests every tick almost all the time, for a state that's idle outside of a
 * recalculation. This keeps one set of "elementId:siteId:fieldId" refs to watch, sends one
 * request per tick listing just those, and swaps in only the matching chip(s) by `id` -- never a
 * row or table reload.
 *
 * `watchedRefs` isn't just "whatever's currently showing `--pending` in the DOM": a chip a bulk
 * action just queued a recalculation for doesn't show pending yet either (Craft's element index
 * doesn't re-render rows after a bulk action completes -- see `RecalculateSeoScore::getTriggerHtml()`),
 * so that action instead calls `coreSeoGeoWatchScoreChips()` directly with the refs it just
 * queued, straight from the (not-yet-pending) chips already in the DOM. A ref stays watched until
 * a poll's response for it no longer carries `--pending`, at which point it's dropped -- so the
 * watch list (and the request) shrinks back down once everything's finished, rather than growing
 * forever.
 *
 * @type {Set<string>}
 */
const watchedRefs = new Set();

/**
 * @param {DOMStringMap} dataset
 * @returns {string|null}
 */
function refFromDataset({ elementId, siteId, fieldId }) {
	return elementId && siteId && fieldId ? `${elementId}:${siteId}:${fieldId}` : null;
}

/**
 * @param {string} ref
 * @returns {string}
 */
function chipIdForRef(ref) {
	const [elementId, siteId, fieldId] = ref.split(":");
	return `core-seo-geo-score-chip-${elementId}-${siteId}-${fieldId}`;
}

/**
 * Scans the page itself for any `--pending` chip not already being watched -- covers a chip that
 * loaded already pending (mid-calculation from a normal save elsewhere), independently of
 * `coreSeoGeoWatchScoreChips()`.
 */
function scanForPendingChips() {
	document.querySelectorAll(`.${PENDING_CLASS}[data-element-id]`).forEach((chip) => {
		const ref = refFromDataset(chip.dataset);
		if (ref) watchedRefs.add(ref);
	});
}

async function poll() {
	scanForPendingChips();
	if (watchedRefs.size === 0) return;

	const refs = Array.from(watchedRefs);
	const url = Craft.getActionUrl("core-seo-geo/seo-score/batch-status", {
		refs: refs.join(","),
	});

	let chips;

	try {
		const response = await fetch(url, { headers: { Accept: "application/json" } });
		if (!response.ok) return;
		chips = await response.json();
	} catch {
		// Next tick tries again -- a dropped request here just leaves the chip showing
		// "Calculating..." a little longer, nothing to recover from.
		return;
	}

	for (const ref of refs) {
		const id = chipIdForRef(ref);
		const html = chips[id];

		// Missing from the response (element/field no longer exists) - stop watching, there's
		// nothing more to swap in for it.
		if (html === undefined) {
			watchedRefs.delete(ref);
			continue;
		}

		const existing = document.getElementById(id);
		if (existing) existing.outerHTML = html;

		if (!html.includes(PENDING_CLASS)) watchedRefs.delete(ref);
	}
}

/**
 * Adds refs to the watch list and polls immediately, rather than waiting for the next tick --
 * called by `RecalculateSeoScore::getTriggerHtml()`'s JS right after a bulk recalculation, so the
 * chip flips to "Calculating..." right away instead of up to `POLL_INTERVAL_MS` late.
 *
 * @param {Array<{elementId: string, siteId: string, fieldId: string}>} refs
 */
window.coreSeoGeoWatchScoreChips = function (refs) {
	let added = false;

	for (const { elementId, siteId, fieldId } of refs) {
		const ref = refFromDataset({ elementId, siteId, fieldId });
		if (!ref) continue;

		watchedRefs.add(ref);
		added = true;
	}

	if (added) poll();
};

setInterval(poll, POLL_INTERVAL_MS);
