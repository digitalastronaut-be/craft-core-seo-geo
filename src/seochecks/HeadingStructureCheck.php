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

/**
 * Class HeadingStructureCheck
 *
 * Walks the rendered page's `<h1>`-`<h6>` elements in document order and fails if more than
 * one `<h1>` exists, or if any step jumps by more than one level (e.g. `<h2>` straight to
 * `<h4>` with no `<h3>` between). Decreasing levels (closing out a section) is always fine.
 * Not applicable if there's no document to check, or no headings at all.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.30.0
 */
class HeadingStructureCheck implements SeoScoreCheckInterface {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getKey(): string {
        return 'heading-structure';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Headings are structured correctly');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getWeight(): int {
        return 10;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function validate(SeoScoreCheckContext $context): SeoScoreCheckResult {
        $document = $context->getDocument();

        if ($document === null) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, '', false);
        }

        $headings = (new \DOMXPath($document))->query(
            '//*[local-name()="h1" or local-name()="h2" or local-name()="h3" or local-name()="h4" or local-name()="h5" or local-name()="h6"]'
        );

        if ($headings->length === 0) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, '', false);
        }

        $h1Count = 0;
        $previousLevel = null;
        $skippedLevel = false;

        foreach ($headings as $heading) {
            $level = (int)substr($heading->nodeName, 1);

            if ($level === 1) $h1Count++;
            if ($previousLevel !== null && $level > $previousLevel + 1) $skippedLevel = true;

            $previousLevel = $level;
        }

        if ($h1Count > 1) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'The rendered page has {count} <h1> elements; there should be exactly one.', [
                'count' => $h1Count,
            ]));
        }

        if ($skippedLevel) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'A heading level is skipped (e.g. an <h2> followed directly by an <h4>).'));
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'Heading levels are structured correctly.'));
    }
}
