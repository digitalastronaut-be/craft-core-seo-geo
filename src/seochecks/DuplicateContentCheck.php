<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\seochecks;

use Craft;

use craft\base\Element;

use digitalastronaut\craftcoreseogeo\records\SeoScore as SeoScoreRecord;

/**
 * Class DuplicateContentCheck
 *
 * Not applicable when neither a meta title nor a meta description is set -- there's nothing to
 * compare yet (and `ValidTitleCheck`/`ValidDescriptionCheck` already flag the emptiness itself).
 * Otherwise fails if either one exactly matches another element's stored value on the same
 * site, read from the denormalized `metaTitle`/`metaDescription` columns on
 * `coreseogeo_seoscores` rather than scanning every element's field value directly.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.5
 */
class DuplicateContentCheck implements SeoScoreCheckInterface {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function getKey(): string {
        return 'duplicate-content';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Duplicate content');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function getWeight(): int {
        return 10;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function validate(SeoScoreCheckContext $context): SeoScoreCheckResult {
        /** @var Element $element */
        $element = $context->element;

        $title = $context->seoData->metaTitle;
        $description = $context->seoData->metaDescription;

        $hasTitle = $title !== null && trim($title) !== '';
        $hasDescription = $description !== null && trim($description) !== '';

        if (!$hasTitle && !$hasDescription) {
            return new SeoScoreCheckResult(
                SeoScoreCheckStatus::Passed,
                Craft::t('core-seo-geo', 'No title or description is set yet to check for duplicates.'),
                false,
            );
        }

        $siteId = $element->getSite()->id;
        $duplicateTitle = $hasTitle && $this->_isDuplicate($siteId, $element->id, 'metaTitle', $title);
        $duplicateDescription = $hasDescription && $this->_isDuplicate($siteId, $element->id, 'metaDescription', $description);

        if ($duplicateTitle && $duplicateDescription) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'Both the meta title and description are already used by another page on this site.'));
        }

        if ($duplicateTitle) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'The meta title is already used by another page on this site.'));
        }

        if ($duplicateDescription) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'The meta description is already used by another page on this site.'));
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'The meta title and description are unique on this site.'));
    }

    // Private Methods
    // =========================================================================

    /**
     * Whether another element on the same site already has this exact value stored in the
     * given denormalized column.
     *
     * @param int $siteId
     * @param int $elementId the element being checked, excluded from the match
     * @param string $column `metaTitle` or `metaDescription`
     * @param string $value
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    private function _isDuplicate(int $siteId, int $elementId, string $column, string $value): bool {
        return SeoScoreRecord::find()
            ->andWhere([
                'siteId' => $siteId,
                $column => $value,
            ])
            ->andWhere(['!=', 'elementId', $elementId])
            ->exists();
    }
}
