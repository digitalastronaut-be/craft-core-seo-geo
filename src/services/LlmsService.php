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
use craft\helpers\UrlHelper;
use craft\models\Site;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\models\SeoData;

use yii\caching\TagDependency;

/**
 * Class LlmsService
 *
 * Generates `llms.txt` (the emerging, unofficial llmstxt.org convention) - a curated,
 * Markdown-formatted index of this site's content for AI agents to read instead of crawling
 * rendered HTML. Eligibility reuses exactly the same content `SitemapService::getGroups()`/
 * `getQueryForGroup()` already considers indexable, so this plugin isn't maintaining two
 * separate definitions of "what counts as this site's public content" - the one addition on
 * top is excluding `ROBOTS_NOAI` as well as `ROBOTS_NOINDEX`, since this file is explicitly
 * AI-facing in a way the sitemap isn't.
 *
 * Cached as one aggregate `TagDependency` built from every eligible element's own cache info
 * (same mechanism `MarkdownService` uses per page, and the same approach the original
 * `docs/llm-markdown-roadmap.md` planned for this) - a save to any element, section, or the
 * `ContentSeo` global row that would have invalidated an individual page's cache also
 * invalidates this one, automatically, with no pending/queued flags to maintain.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.15.0
 */
class LlmsService extends Component {
    private const string CACHE_TAG = 'core-seo-geo-llms';

    /**
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function getLlmsTxt(int $siteId): string {
        $cacheKey = [self::CACHE_TAG, 'llms-txt', $siteId];
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
     * Invalidates every site's cached llms.txt. The `TagDependency` built into `getLlmsTxt()`
     * already invalidates itself when an eligible *element* changes - this covers the cases that
     * aren't an element save at all: `Settings::$enableLlmsTxt`/`ContentSeo::$llmsTxtTitle`/
     * `$llmsTxtSummary` being saved. Called from `ContentSeoService::saveLlmsTxtConfig()`.
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
     * Every eligible element for `$siteId`, grouped by the same section/singles/categories
     * labels `SitemapService::getGroups()` uses, in that same order. Exposed publicly (not just
     * used internally by `getLlmsTxt()`) so `LlmsFullService` can reuse the identical eligible
     * set for llms-full.txt instead of re-querying it.
     *
     * @param int $siteId
     * @return array<string, array<int, array{title: string, url: string, description: string, element: \craft\base\ElementInterface}>>
     * group label => its entries
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function getEligibleEntriesByGroup(int $siteId): array {
        $sitemap = CoreSeoGeo::getInstance()->getSitemap();
        $contentSeo = CoreSeoGeo::getInstance()->getContentSeo();
        $groups = [];

        foreach ($sitemap->getGroups($siteId) as $handle => $label) {
            $query = $sitemap->getQueryForGroup($siteId, $handle);

            // `custom-urls` has no backing element to resolve a title/description/robots from.
            if ($query === null) continue;

            $entries = [];

            foreach ($query->each() as $element) {
                $seo = $contentSeo->resolveSeoData($element);
                if ($seo === null) continue;
                if (array_intersect($seo->robots, [SeoData::ROBOTS_NOINDEX, SeoData::ROBOTS_NOAI]) !== []) continue;

                $url = $element->getUrl();
                if ($url === null) continue;

                $entries[] = [
                    'title' => ($seo->metaTitle ?? '') !== '' ? $seo->metaTitle : (string)$element->title,
                    'url' => $url,
                    'description' => $seo->metaDescription ?? '',
                    'element' => $element,
                ];
            }

            if ($entries !== []) {
                $groups[$label] = $entries;
            }
        }

        return $groups;
    }

    /**
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    private function _generate(int $siteId): string {
        $lines = [];

        [$title, $summary] = $this->getTitleAndSummary($siteId);

        $lines[] = "# {$title}";

        if ($summary !== '') {
            $lines[] = '';
            $lines[] = "> {$summary}";
        }

        $languagesLine = $this->getLanguagesLine($siteId, 'llms.txt');

        if ($languagesLine !== null) {
            $lines[] = '';
            $lines[] = $languagesLine;
        }

        foreach ($this->getEligibleEntriesByGroup($siteId) as $label => $entries) {
            $lines[] = '';
            $lines[] = "## {$label}";
            $lines[] = '';

            foreach ($entries as $entry) {
                $line = "- [{$entry['title']}]({$entry['url']})";

                if ($entry['description'] !== '') {
                    $line .= ": {$entry['description']}";
                }

                $lines[] = $line;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * The `# Title`/`> Summary` llms.txt opens with - the site's own configured
     * `llmsTxtTitle`/`llmsTxtSummary` (per-site, on the `ContentSeo` global row), falling back
     * to the site's own name when no title was set. Public so `LlmsFullService` can reuse the
     * exact same header.
     *
     * @param int $siteId
     * @return array{0: string, 1: string} title, summary (summary is `''` when unset)
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function getTitleAndSummary(int $siteId): array {
        $row = CoreSeoGeo::getInstance()->getContentSeo()->getGlobalRow($siteId);

        $title = $row?->llmsTxtTitle;
        $title ??= Craft::$app->getSites()->getSiteById($siteId)?->name ?? Craft::$app->getSystemName();

        return [$title, trim((string)($row?->llmsTxtSummary ?? ''))];
    }

    /**
     * The other sites in `$siteId`'s own site group (same brand, other locales - e.g.
     * `pluginTestEn`/`pluginTestNl` both sitting in the same group), each pointing at their own
     * copy of the given file. Since this is per-site, an agent that's only ever seen the EN
     * `llms.txt` has no way to discover the NL one otherwise exists - this is the llms.txt
     * equivalent of the hreflang alternates `HreflangService::getAlternates()` adds to a page's
     * `<head>`, just at the site level instead of the per-element one that service handles.
     * `[]` when `$siteId`'s group has no other sites.
     *
     * @param int $siteId
     * @param string $filename `llms.txt` or `llms-full.txt`
     * @return array<int, array{label: string, url: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function getLanguageAlternates(int $siteId, string $filename): array {
        $sites = Craft::$app->getSites();
        $site = $sites->getSiteById($siteId);

        if ($site === null) return [];

        return array_values(array_map(
            static fn(Site $groupSite): array => [
                'label' => $groupSite->name,
                'url' => UrlHelper::siteUrl($filename, null, null, $groupSite->id),
            ],
            array_filter(
                $sites->getSitesByGroupId($site->groupId),
                static fn(Site $groupSite): bool => $groupSite->id !== $siteId,
            ),
        ));
    }

    /**
     * The ready-to-insert `Languages: [Label](url) | ...` line built from
     * `getLanguageAlternates()` - `null` when there's nothing to link to, so a caller can skip
     * adding it (and the blank line around it) entirely rather than checking emptiness itself.
     * Shared by this class's own `_generate()` and `LlmsFullService::_generate()`.
     *
     * @param int $siteId
     * @param string $filename `llms.txt` or `llms-full.txt`
     * @return string|null
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function getLanguagesLine(int $siteId, string $filename): ?string {
        $alternates = $this->getLanguageAlternates($siteId, $filename);

        if ($alternates === []) return null;

        return 'Languages: ' . implode(' | ', array_map(
            static fn(array $alternate): string => "[{$alternate['label']}]({$alternate['url']})",
            $alternates,
        ));
    }
}
