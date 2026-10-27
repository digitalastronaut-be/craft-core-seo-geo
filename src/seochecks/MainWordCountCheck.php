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
 * Class MainWordCountCheck
 *
 * Fails when the page's `<main>` landmark has fewer than {@see self::MIN_WORDS} words of text --
 * thin content is a common, otherwise invisible reason a page underperforms in search even when
 * every other check passes. Counts every text node under `<main>` (not just paragraphs), the
 * same "whatever a visitor would actually read" scope {@see ImageAltTextCheck} uses. Not
 * applicable rather than failed when there's no document to check, or no `<main>` at all -- the
 * latter is {@see HasMainCheck}'s problem to report, not this one's.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.31.0
 */
class MainWordCountCheck implements SeoScoreCheckInterface {
    /**
     * @since v1.31.0
     */
    private const int MIN_WORDS = 300;

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.31.0
     */
    public function getKey(): string {
        return 'main-word-count';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.31.0
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Main content has enough words');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.31.0
     */
    public function getWeight(): int {
        return 10;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.31.0
     */
    public function validate(SeoScoreCheckContext $context): SeoScoreCheckResult {
        $document = $context->getDocument();

        if ($document === null) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, '', false);
        }

        $mainNodes = (new \DOMXPath($document))->query('//*[local-name()="main"]');

        if ($mainNodes->length === 0) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, '', false);
        }

        $text = '';
        foreach ($mainNodes as $main) {
            $text .= ' ' . $main->textContent;
        }

        $words = array_filter(preg_split('/\s+/u', trim($text)) ?: [], static fn(string $word): bool => $word !== '');
        $wordCount = count($words);

        if ($wordCount < self::MIN_WORDS) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'The <main> element has only {count} words; at least {min} is recommended.', [
                'count' => $wordCount,
                'min' => self::MIN_WORDS,
            ]));
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'The <main> element has {count} words.', [
            'count' => $wordCount,
        ]));
    }
}
