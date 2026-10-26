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

use DateTime;
use Generator;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\db\ElementQuery;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\CategoryGroup;
use craft\models\Section;
use craft\models\Site;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\events\RegisterSitemapUrlsEvent;
use digitalastronaut\craftcoreseogeo\fields\SeoField;
use digitalastronaut\craftcoreseogeo\models\SeoData;

/**
 * Class SitemapService
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.1.0
 */
class SitemapService extends Component {
    public const string EVENT_REGISTER_URLS = 'registerSitemapUrls';
    private const string CATEGORIES_GROUP = 'categories';
    private const string CUSTOM_URLS_GROUP = 'custom-urls';
    private const string SINGLES_GROUP = 'singles';

    private const int CACHE_DURATION = 86400;
    private const int PAGE_SIZE = 1000;

    /**
     * @param int $siteId
     * @return array<string, string> handle => label
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getGroups(int $siteId): array {
        $groups = [];

        $contentSeo = CoreSeoGeo::getInstance()->getContentSeo();

        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            if ($section->type === Section::TYPE_SINGLE) continue;
            if (!($section->getSiteSettings()[$siteId]->hasUrls ?? false)) continue;
            if (!$contentSeo->isSitemapEnabled($section->handle, $siteId)) continue;

            $groups[$section->handle] = $section->name;
        }

        if ($this->_urlableSingleSections($siteId) !== []) $groups[self::SINGLES_GROUP] = Craft::t('core-seo-geo', 'Singles');
        if ($this->_urlableCategoryGroups($siteId) !== []) $groups[self::CATEGORIES_GROUP] = Craft::t('core-seo-geo', 'Categories');
        if ($this->getCustomUrls($siteId) !== []) $groups[self::CUSTOM_URLS_GROUP] = Craft::t('core-seo-geo', 'Custom URLs');

        return $groups;
    }

    /**
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getIndexXml(int $siteId): string {
        $entries = '';

        foreach (array_keys($this->getGroups($siteId)) as $handle) {
            $pageCount = $this->_pageCount($siteId, $handle);

            for ($page = 1; $page <= $pageCount; $page++) {
                $fileHandle = $pageCount > 1 ? "{$handle}-{$page}" : $handle;
                $url = UrlHelper::siteUrl("sitemap-{$fileHandle}.xml", null, null, $siteId);
                $entries .= '<sitemap><loc>' . Html::encode($url) . '</loc></sitemap>';
            }
        }

        return $this->_wrapXml('sitemapindex', $entries);
    }

    /**
     * @param int $siteId
     * @param string $groupKey
     * @return array{0: string|null, 1: int} the base handle (`null` if unresolved) and page
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function resolveGroupKey(int $siteId, string $groupKey): array {
        $groups = $this->getGroups($siteId);

        if (array_key_exists($groupKey, $groups)) {
            return [$groupKey, 1];
        }

        if (preg_match('/^(.+)-(\d+)$/', $groupKey, $matches) && array_key_exists($matches[1], $groups)) {
            return [$matches[1], (int)$matches[2]];
        }

        return [null, 1];
    }

    /**
     * @param int $siteId
     * @param string $groupHandle
     * @param int $page 1-indexed
     * @return string|null
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getGroupXml(int $siteId, string $groupHandle, int $page = 1): ?string {
        if (!array_key_exists($groupHandle, $this->getGroups($siteId))) {
            return null;
        }

        if ($page < 1 || $page > $this->_pageCount($siteId, $groupHandle)) {
            return null;
        }

        $cache = Craft::$app->getCache();
        $cacheKey = "coreseogeo_sitemap_{$siteId}_{$groupHandle}_{$page}";
        $cached = $cache->get($cacheKey);

        if ($cached !== false) return $cached;

        $elements = Craft::$app->getElements();
        $elements->startCollectingCacheInfo();

        $xml = $this->_renderGroupPageXml($siteId, $groupHandle, $page);

        [$dependency, $duration] = $elements->stopCollectingCacheInfo();

        $cache->set($cacheKey, $xml, $duration ?? self::CACHE_DURATION, $dependency);

        return $xml;
    }

    /**
     * @param int $siteId
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getStaticCustomRouteUrls(int $siteId): array {
        $urls = [];

        foreach ($this->_customRoutePatterns() as $pattern) {
            if ($this->_isDynamicRoutePattern($pattern)) {
                continue;
            }

            $urls[] = UrlHelper::siteUrl($pattern, null, null, $siteId);
        }

        return $urls;
    }

    /**
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getUnresolvedRoutePatterns(): array {
        return array_values(array_filter(
            $this->_customRoutePatterns(),
            fn(string $pattern): bool => $this->_isDynamicRoutePattern($pattern),
        ));
    }

    /**
     * Combines URLs from the static `config/routes.php`/project-config routes, the Sitemap
     * settings page's manually-maintained table (`Settings::getEnabledCustomUrls()`), and
     * `EVENT_REGISTER_URLS` listeners, in that order. None of these carry `images`/`alternates`
     * keys - there's no backing element to pull asset relations or site alternates from - so
     * `_urlXml()` treats both as absent.
     *
     * @param int $siteId
     * @return array<int, array{loc: string, lastmod: DateTime|null}>
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getCustomUrls(int $siteId): array {
        $urls = array_map(
            static fn(string $url): array => ['loc' => $url, 'lastmod' => null],
            $this->getStaticCustomRouteUrls($siteId),
        );

        foreach (CoreSeoGeo::getInstance()->getSettings()->getEnabledCustomUrls() as $url) {
            $urls[] = $url;
        }

        $event = new RegisterSitemapUrlsEvent(['siteId' => $siteId]);
        $this->trigger(self::EVENT_REGISTER_URLS, $event);

        foreach ($event->urls as $url) {
            $urls[] = \is_array($url)
                ? ['loc' => $url['loc'], 'lastmod' => $url['lastmod'] ?? null]
                : ['loc' => $url, 'lastmod' => null];
        }

        return $urls;
    }

    /**
     * @param int|null $siteId
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function clearCustomUrlsCache(?int $siteId = null): void {
        $cache = Craft::$app->getCache();
        $siteIds = $siteId !== null ? [$siteId] : array_map(
            static fn(Site $site): int => $site->id,
            Craft::$app->getSites()->getAllSites(),
        );

        foreach ($siteIds as $id) {
            $pageCount = $this->_pageCount($id, self::CUSTOM_URLS_GROUP);

            for ($page = 1; $page <= $pageCount; $page++) {
                $cache->delete("coreseogeo_sitemap_{$id}_" . self::CUSTOM_URLS_GROUP . "_{$page}");
            }
        }
    }

    /**
     * @param int $siteId
     * @param string $groupHandle
     * @param int $page
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _renderGroupPageXml(int $siteId, string $groupHandle, int $page): string {
        $entries = '';

        foreach ($this->_urlsForGroupPage($siteId, $groupHandle, $page) as $url) {
            $entries .= $this->_urlXml($url);
        }

        return $this->_wrapXml('urlset', $entries);
    }

    /**
     * @param array{loc: string, lastmod: DateTime|null, images: string[], alternates: array<int, array{language: string, url: string}>} $url
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _urlXml(array $url): string {
        $xml = '<url><loc>' . Html::encode($url['loc']) . '</loc>';

        if ($url['lastmod'] !== null) {
            $xml .= '<lastmod>' . $url['lastmod']->format(DateTime::ATOM) . '</lastmod>';
        }

        foreach ($url['alternates'] ?? [] as $alternate) {
            $xml .= '<xhtml:link rel="alternate" hreflang="' . Html::encode($alternate['language']) . '" href="' . Html::encode($alternate['url']) . '"/>';
        }

        foreach ($url['images'] ?? [] as $imageUrl) {
            $xml .= '<image:image><image:loc>' . Html::encode($imageUrl) . '</image:loc></image:image>';
        }

        return $xml . '</url>';
    }

    /**
     * @param int $siteId
     * @param string $groupHandle
     * @return int
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _pageCount(int $siteId, string $groupHandle): int {
        if ($groupHandle === self::CUSTOM_URLS_GROUP) {
            $total = \count($this->getCustomUrls($siteId));
        } else {
            $query = $this->getQueryForGroup($siteId, $groupHandle);
            $total = $query === null ? 0 : $query->count();
        }

        return max(1, (int)ceil($total / self::PAGE_SIZE));
    }

    /**
     * @param int $siteId
     * @param string $groupHandle
     * @param int $page
     * @return Generator<array{loc: string, lastmod: DateTime|null, images: string[], alternates: array<int, array{language: string, url: string}>}>
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _urlsForGroupPage(int $siteId, string $groupHandle, int $page): Generator {
        if ($groupHandle === self::CUSTOM_URLS_GROUP) {
            $urls = \array_slice($this->getCustomUrls($siteId), ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

            foreach ($urls as $url) yield $url;

            return;
        }

        $query = $this->getQueryForGroup($siteId, $groupHandle);

        if ($query === null) return;

        $query->offset(($page - 1) * self::PAGE_SIZE)->limit(self::PAGE_SIZE);

        yield from $this->_urlsForQuery($query, $siteId);
    }

    /**
     * The element query backing one of `getGroups()`'s handles - `null` for `custom-urls`,
     * since those have no backing element at all. Public (promoted from `_queryForGroup()`) so
     * `TxtFilesService` can enumerate the same eligible content for llms.txt without this plugin
     * maintaining two separate "what counts as this site's indexable content" definitions.
     *
     * @param int $siteId
     * @param string $groupHandle
     * @return ElementQuery|null
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getQueryForGroup(int $siteId, string $groupHandle): ?ElementQuery {
        if ($groupHandle === self::CUSTOM_URLS_GROUP) {
            return null;
        }

        if ($groupHandle === self::CATEGORIES_GROUP) {
            $groups = $this->_urlableCategoryGroups($siteId);

            return $groups === []
                ? null
                : Category::find()->group($groups)->siteId($siteId)->status(Category::STATUS_ENABLED)->orderBy(['elements.id' => SORT_ASC]);
        }

        if ($groupHandle === self::SINGLES_GROUP) {
            $sections = $this->_urlableSingleSections($siteId);

            return $sections === []
                ? null
                : Entry::find()->section($sections)->siteId($siteId)->status(Entry::STATUS_LIVE)->orderBy(['elements.id' => SORT_ASC]);
        }

        return Entry::find()->section($groupHandle)->siteId($siteId)->status(Entry::STATUS_LIVE)->orderBy(['elements.id' => SORT_ASC]);
    }

    /**
     * @param ElementQuery $query
     * @param int $siteId
     * @return Generator<array{loc: string, lastmod: DateTime|null, images: string[], alternates: array<int, array{language: string, url: string}>}>
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _urlsForQuery(ElementQuery $query, int $siteId): Generator {
        foreach ($query->each() as $element) {
            if ($this->_isNoIndex($element)) {
                continue;
            }

            $url = $element->getUrl();

            if ($url === null) {
                continue;
            }

            yield [
                'loc' => $url,
                'lastmod' => $element->dateUpdated,
                'images' => $this->_imageUrls($element, $siteId),
                'alternates' => CoreSeoGeo::getInstance()->getHreflang()->getAlternates($element),
            ];
        }
    }

    /**
     * Every image for `$element`'s own asset fields, plus every entry nested inside it via a
     * Matrix field at any depth - deduped by asset id, in first-seen order.
     *
     * @param ElementInterface $element
     * @param int $siteId
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.2.1
     */
    private function _imageUrls(ElementInterface $element, int $siteId): array {
        $urlsByAssetId = [];

        foreach ($this->_assetsForElement($element, $siteId) as $asset) {
            $url = $asset->getUrl();

            if ($url !== null) $urlsByAssetId[$asset->id] = $url;
        }

        return array_values($urlsByAssetId);
    }

    /**
     * Recursively walks `$element`'s own image-kind asset relations plus every entry nested
     * inside it via a Matrix field, at any depth. A Matrix field's nested entries are owned
     * elements (`elements_owners.ownerId` pointing back to `$element`, or to the nested entry one
     * level up for a Matrix-in-Matrix) rather than relations on `$element` itself, so an asset
     * field buried several blocks deep never shows up as a relation on `$element` - only a full
     * walk down the ownership tree finds it. Non-image assets (PDFs, videos, ...) are excluded -
     * the sitemap image protocol is for images only.
     *
     * @param ElementInterface $element
     * @param int $siteId
     * @return Generator<Asset>
     *
     * @author      Digitalastronaut
     * @since       v1.2.1
     */
    private function _assetsForElement(ElementInterface $element, int $siteId): Generator {
        yield from Asset::find()->relatedTo($element)->kind(Asset::KIND_IMAGE)->siteId($siteId)->all();

        $nestedEntries = Entry::find()
            ->owner($element)
            ->siteId($siteId)
            ->status(null)
            ->all();

        foreach ($nestedEntries as $nestedEntry) {
            yield from $this->_assetsForElement($nestedEntry, $siteId);
        }
    }

    /**
     * @param ElementInterface $element
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _isNoIndex(ElementInterface $element): bool {
        foreach (SeoField::getFieldsOn($element) as $field) {
            if (in_array(SeoData::ROBOTS_NOINDEX, $this->_rawRobots($element, $field), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ElementInterface $element
     * @param SeoField $field
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _rawRobots(ElementInterface $element, SeoField $field): array {
        $raw = $element->getBehavior('customFields')->{$field->handle};

        if ($raw instanceof SeoData) return $raw->robots;
        if (is_string($raw)) $raw = Json::decodeIfJson($raw);

        return SeoField::normalizeRobots(\is_array($raw) ? ($raw['robots'] ?? []) : []);
    }

    /**
     * @param int $siteId
     * @return Section[]
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _urlableSingleSections(int $siteId): array {
        return array_values(array_filter(
            Craft::$app->getEntries()->getAllSections(),
            fn(Section $section): bool => $section->type === Section::TYPE_SINGLE
                && ($section->getSiteSettings()[$siteId]->hasUrls ?? false),
        ));
    }

    /**
     * @param int $siteId
     * @return CategoryGroup[]
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _urlableCategoryGroups(int $siteId): array {
        return array_values(array_filter(
            Craft::$app->getCategories()->getAllGroups(),
            fn(CategoryGroup $group): bool => $group->getSiteSettings()[$siteId]->hasUrls ?? false,
        ));
    }

    /**
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _customRoutePatterns(): array {
        $patterns = [];

        foreach (array_keys(Craft::$app->getRoutes()->getConfigFileRoutes()) as $pattern) {
            if (is_string($pattern)) {
                $patterns[$pattern] = true;
            }
        }

        foreach (Craft::$app->getRoutes()->getProjectConfigRoutes() as $route) {
            $patterns[$route['pattern']] = true;
        }

        return array_keys($patterns);
    }

    /**
     * @param string $pattern
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _isDynamicRoutePattern(string $pattern): bool {
        return str_contains($pattern, '<');
    }

    /**
     * @param string $rootTag
     * @param string $innerXml
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _wrapXml(string $rootTag, string $innerXml): string {
        $namespaces = 'xmlns="https://www.sitemaps.org/schemas/sitemap/0.9"';

        // Only a `urlset` ever carries `<image:image>`/`<xhtml:link>` children - a
        // `sitemapindex`'s entries are just pointers to the sub-sitemaps, so both namespaces
        // would be dead weight there.
        if ($rootTag === 'urlset') {
            $namespaces .= ' xmlns:image="https://www.google.com/schemas/sitemap-image/1.1"';
            $namespaces .= ' xmlns:xhtml="https://www.w3.org/1999/xhtml"';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' .
            "<{$rootTag} {$namespaces}>{$innerXml}</{$rootTag}>";
    }
}
