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
 * Class MainHasLinkCheck
 *
 * Fails when the page's `<main>` landmark has no outgoing links -- a common symptom of a
 * dead-end page (e.g. a template that forgot its body content). Not applicable rather than
 * failed when there's no document to check, or no `<main>` at all -- the latter is
 * {@see HasMainCheck}'s problem to report, not this one's.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.30.0
 */
class MainHasLinkCheck implements SeoScoreCheckInterface {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getKey(): string {
        return 'main-has-link';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Main content has a link');
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

        $xpath = new \DOMXPath($document);

        if ($xpath->query('//*[local-name()="main"]')->length === 0) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, '', false);
        }

        if ($xpath->query('//*[local-name()="main"]//*[local-name()="a"][@href]')->length === 0) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'The <main> element has no links.'));
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'The <main> element has at least one link.'));
    }
}
