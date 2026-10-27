<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\elements\actions;

use Craft;

use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\fields\SeoField;

/**
 * Class RecalculateSeoScore
 *
 * Bulk element-index counterpart to the entry edit page's per-field "Refresh" button
 * ({@see \digitalastronaut\craftcoreseogeo\controllers\SeoScoreController::actionRefresh()}) --
 * queues a fresh `CalculateSeoScore` run for every `SeoField` on every selected element, without
 * anyone having to open each one individually.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.29.0
 */
class RecalculateSeoScore extends ElementAction {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.29.0
     */
    public static function displayName(): string {
        return Craft::t('core-seo-geo', 'Recalculate SEO/GEO score');
    }

    /**
     * Craft's element index never reloads the visible rows after a bulk action on its own -- it
     * only calls `Craft.cp.runQueue()` (see `afterAction()` in its own JS) and leaves the table
     * exactly as it was. Every selected row's chip already carries its `data-element-id`/
     * `data-site-id`/`data-field-id` regardless of score state (see `SeoScoreService::renderChip()`)
     * -- even though the chip doesn't show pending yet, those attributes are enough to hand the
     * ref straight to `admin/elementIndexScorePolling.js`'s watch list
     * (`window.coreSeoGeoWatchScoreChips()`), which polls it (and swaps just that one chip's HTML
     * in once it has something new) the same way it already does for a chip the page loaded with
     * mid-calculation. No table reload needed -- `markPending()` already ran server-side by the
     * time this fires, so the very next poll tick renders it correctly.
     *
     * No `validateSelection` override needed, unlike most of Craft's own bulk actions that
     * register a trigger for this (see e.g. `craft\elements\actions\Restore`) -- this action has
     * no selection constraints of its own, so returning `null` here still leaves the element
     * index's default "plain menu item, POST on click" handling in place; registering the
     * `Craft.ElementActionTrigger` is only to get its `afterActivate` hook, which runs once the
     * bulk action's own request has already resolved.
     *
     * @return string|null
     *
     * @author      Digitalastronaut
     * @since       v1.29.0
     */
    public function getTriggerHtml(): ?string {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        afterActivate: async (selectedItems, elementIndex) => {
            const refs = [];

            selectedItems.find('.core-seo-geo-score-chip[data-element-id]').each(function() {
                const { elementId, siteId, fieldId } = this.dataset;
                if (elementId && siteId && fieldId) refs.push({ elementId, siteId, fieldId });
            });

            window.coreSeoGeoWatchScoreChips?.(refs);
        },
    });
})();
JS, [static::class]);

        return null;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.29.0
     */
    public function performAction(ElementQueryInterface $query): bool {
        $seoScore = CoreSeoGeo::getInstance()->getSeoScore();
        $queuedCount = 0;

        foreach (Db::each($query) as $element) {
            foreach (SeoField::getFieldsOn($element) as $field) {
                $seoScore->markPending($element, (int)$field->id);
                $seoScore->calculate($element, (int)$field->id);
                $queuedCount++;
            }
        }

        if ($queuedCount === 0) {
            $this->setMessage(Craft::t('core-seo-geo', 'None of the selected elements have an SEO/GEO field.'));
            return false;
        }

        $this->setMessage(Craft::t('core-seo-geo', 'Queued {count, plural, =1{# recalculation} other{# recalculations}}.', [
            'count' => $queuedCount,
        ]));

        return true;
    }
}
