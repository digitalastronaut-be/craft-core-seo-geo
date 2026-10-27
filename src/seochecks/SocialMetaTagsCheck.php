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
 * Class SocialMetaTagsCheck
 *
 * Confirms `og:title`, `og:description`, `og:image`, and `twitter:card` all resolve to a
 * non-empty `content` in the rendered `<head>` -- catches a template fallback chain that
 * silently resolves to nothing even though the `SeoField`-level checks (title/description/
 * image) passed. Not applicable if there's no document to check.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.30.0
 */
class SocialMetaTagsCheck implements SeoScoreCheckInterface {
    /**
     * @since v1.30.0
     */
    private const array REQUIRED_TAGS = ['og:title', 'og:description', 'og:image', 'twitter:card'];

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getKey(): string {
        return 'social-meta-tags';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getLabel(): string {
        return Craft::t('core-seo-geo', 'Open Graph / Twitter tags');
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
        $missing = [];

        foreach (self::REQUIRED_TAGS as $tag) {
            $attribute = str_starts_with($tag, 'twitter:') ? 'name' : 'property';
            $content = '';

            foreach ($xpath->query("//*[local-name()='meta'][@{$attribute}='{$tag}']") as $meta) {
                /** @var \DOMElement $meta */
                $content = trim($meta->getAttribute('content'));
                if ($content !== '') break;
            }

            if ($content === '') $missing[] = $tag;
        }

        if ($missing !== []) {
            return new SeoScoreCheckResult(SeoScoreCheckStatus::Failed, Craft::t('core-seo-geo', 'Missing or empty: {tags}.', [
                'tags' => implode(', ', $missing),
            ]));
        }

        return new SeoScoreCheckResult(SeoScoreCheckStatus::Passed, Craft::t('core-seo-geo', 'Open Graph and Twitter tags all resolve to a value.'));
    }
}
