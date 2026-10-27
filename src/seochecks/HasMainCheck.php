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
 * Class HasMainCheck
 *
 * Fetches the element's own rendered page ({@see SeoScoreCheckContext::getDocument()}) and
 * checks for exactly one `<main>` landmark. Not applicable if the page couldn't be fetched/
 * parsed at all (no front-end URL, request failure) -- that's a different problem than the
 * template actually missing a `<main>`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.30.0
 */
class HasMainCheck implements SeoScoreCheckInterface {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getKey(): string {
        return 'has-main';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Has a <main> element');
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

        $mainCount = (new \DOMXPath($document))->query('//*[local-name()="main"]')->length;

        if ($mainCount === 0) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'The rendered page has no <main> element.'));
        }

        if ($mainCount > 1) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Warning, Craft::t('core-seo-geo', 'The rendered page has {count} <main> elements; there should be exactly one.', [
                'count' => $mainCount,
            ]));
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'The rendered page has exactly one <main> element.'));
    }
}
