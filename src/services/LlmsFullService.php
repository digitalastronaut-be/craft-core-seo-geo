<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\services;

use Craft;
use craft\base\Component;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

use yii\caching\TagDependency;

/**
 * Class LlmsFullService
 *
 * Generates `llms-full.txt`, the llmstxt.org companion to `llms.txt`: instead of a link and a
 * one-line description per page, it concatenates every eligible page's *full* Markdown content
 * in sequence. Eligibility is exactly `LlmsService::getEligibleEntriesByGroup()`'s set - this
 * plugin isn't maintaining a third definition of "what counts as this site's public content" -
 * and the per-page content itself is `MarkdownService::getMarkdownForElement()`, the same
 * cached, self-fetched Markdown the `.md` route already serves per page.
 *
 * The aggregate result is cached the same way `LlmsService::getLlmsTxt()` caches its own - one
 * `TagDependency` built by collecting cache info across every eligible element - on top of
 * `MarkdownService`'s own per-page cache, so a cold cache still means one self-fetch per
 * eligible page the first time this is requested (there's no warming job yet, by design - see
 * `docs/llm-markdown-roadmap.md`).
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.15.0
 */
class LlmsFullService extends Component {
    private const string CACHE_TAG = 'core-seo-geo-llms-full';

    /**
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function getLlmsFullTxt(int $siteId): string {
        $cacheKey = [self::CACHE_TAG, 'llms-full-txt', $siteId];
        $cached = Craft::$app->getCache()->get($cacheKey);

        if (\is_string($cached)) {
            return $cached;
        }

        $elements = Craft::$app->getElements();
        $elements->startCollectingCacheInfo();

        $txt = $this->_generate($siteId);

        [$dependency, $duration] = $elements->stopCollectingCacheInfo();
        $dependency ??= new TagDependency();
        $dependency->tags[] = self::CACHE_TAG;

        Craft::$app->getCache()->set($cacheKey, $txt, $duration ?? 0, $dependency);

        return $txt;
    }

    /**
     * Invalidates every site's cached llms-full.txt. Same reasoning as
     * `LlmsService::clearCache()` - covers the `ContentSeo`/`Settings` save cases an eligible
     * element's own `TagDependency` can't.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function clearCache(): void {
        TagDependency::invalidate(Craft::$app->getCache(), self::CACHE_TAG);
    }

    /**
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    private function _generate(int $siteId): string {
        $llms = CoreSeoGeo::getInstance()->getLlms();
        $markdown = CoreSeoGeo::getInstance()->getMarkdown();

        [$title, $summary] = $llms->getTitleAndSummary($siteId);

        $lines = ["# {$title}"];

        if ($summary !== '') {
            $lines[] = '';
            $lines[] = "> {$summary}";
        }

        $languagesLine = $llms->getLanguagesLine($siteId, 'llms-full.txt');

        if ($languagesLine !== null) {
            $lines[] = '';
            $lines[] = $languagesLine;
        }

        foreach ($llms->getEligibleEntriesByGroup($siteId) as $label => $entries) {
            $lines[] = '';
            $lines[] = "## {$label}";

            foreach ($entries as $entry) {
                $pageMarkdown = $markdown->getMarkdownForElement($entry['element']);

                // A page with no `{% llmContent %}` blocks (or that failed to fetch) has
                // nothing to add here - it still gets its link-and-description line in
                // llms.txt, just not a content section in this file.
                if ($pageMarkdown === '') continue;

                $lines[] = '';
                $lines[] = '---';
                $lines[] = '';
                // Deliberately bold text, not a `#`-heading: the page's own content can (and
                // often does) open with its own real heading, and a heading here immediately
                // above that one reads as "this page has nothing in it" - a blank-looking
                // heading followed immediately by another heading - even though content
                // follows right after. Bold text can never be confused with an empty section.
                $lines[] = "**{$entry['title']}** ({$entry['url']})";
                $lines[] = '';
                $lines[] = $pageMarkdown;
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
