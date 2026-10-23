<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\jobs;

use Craft;

use craft\queue\BaseJob;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\models\SeoData;

/**
 * Class CalculateSeoScore
 *
 * Runs the SEO score checklist for one element+field and persists the result. Pushed by
 * `SeoScoreService::calculate()` rather than run inline on save, since several registered
 * checks fetch and parse the element's own rendered page -- too slow to do inside a save
 * request. Re-fetches the element and its field value itself rather than having them passed in,
 * since job properties are serialized onto the queue and must stay plain scalars.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
class CalculateSeoScore extends BaseJob {
    /**
     * @since v1.0.4
     */
    public int $elementId;
    public int $siteId;
    public int $fieldId;

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function execute($queue): void {
        $element = Craft::$app->getElements()->getElementById($this->elementId, null, $this->siteId, [
            'status' => null,
        ]);

        if ($element === null) return;

        $field = Craft::$app->getFields()->getFieldById($this->fieldId);

        if ($field === null) return;

        $fieldLayout = $element->getFieldLayout();

        if ($fieldLayout === null || !$fieldLayout->isFieldIncluded($field->handle)) return;

        $seoData = $element->getFieldValue($field->handle);

        if (!$seoData instanceof SeoData) return;

        CoreSeoGeo::getInstance()->getSeoScore()->process($element, $seoData, $this->fieldId);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    protected function defaultDescription(): ?string {
        return Craft::t('core-seo-geo', 'Calculating SEO score');
    }
}
