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

/**
 * Class StructuredDataService
 *
 * {@see \digitalastronaut\craftcoreseogeo\variables\CoreSeoGeoVariable}).
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredDataService extends Component {
    /**
     * Builds a schema.org `BreadcrumbList` object. A "Home" crumb pointing at the current
     * site's base URL is always put first; `item` (the crumb's URL) is omitted from the last
     * crumb, since that's the current page.
     *
     * @param array<int, array{label: string, href: string}> $items The breadcrumbs, from the
     * one right under "Home" to the current page, in order.
     * @param string|null $homeUrl The "Home" crumb's URL. Defaults to the site's domain itself
     * (scheme + host), not its base URL, since the domain root is what "Home" means even when
     * the current site is set up under a subpath.
     * @param string|null $homeLabel The "Home" crumb's label.
     * @return array{'@context': string, '@type': string, itemListElement: array<int, array<string, mixed>>}
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function createBreadcrumbs(array $items, ?string $homeUrl = null, ?string $homeLabel = null): array {
        $homeUrl ??= $this->_domainUrl();
        $homeLabel ??= Craft::t('core-seo-geo', 'Home');

        $itemListElement = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => $homeLabel,
                'item' => $homeUrl,
            ],
        ];

        $lastIndex = \count($items) - 1;

        foreach ($items as $index => $item) {
            $listItem = [
                '@type' => 'ListItem',
                'position' => $index + 2,
                'name' => (string)($item['label'] ?? ''),
            ];

            if ($index !== $lastIndex) {
                $listItem['item'] = (string)($item['href'] ?? '');
            }

            $itemListElement[] = $listItem;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemListElement,
        ];
    }

    /**
     * Builds a schema.org `WebPageElement` object identifying one element on the page, e.g.
     * for the `mainContentOfPage` property. Pass whichever of `cssSelector`/`xpath` identifies
     * the element; schema.org allows either, or both.
     *
     * @param string|null $cssSelector A CSS selector identifying the element, e.g. `main` or
     * `#content`.
     * @param string|null $xpath An XPath identifying the element.
     * @return array{'@type': string, cssSelector?: string, xpath?: string}
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function createWebPageElement(?string $cssSelector = null, ?string $xpath = null): array {
        return array_filter([
            '@type' => 'WebPageElement',
            'cssSelector' => $cssSelector,
            'xpath' => $xpath,
        ], static fn(mixed $value): bool => $value !== null);
    }

    /**
     * Returns the current site's domain root (scheme + host, no path), e.g.
     * `https://example.com/`. Used as the default "Home" URL, since a site's own base URL can
     * sit under a subpath (e.g. `https://example.com/en/`) that "Home" shouldn't be scoped to.
     *
     * @return string
     *
     * @since v1.0.0
     */
    private function _domainUrl(): string {
        $baseUrl = (string)Craft::$app->getSites()->getCurrentSite()->getBaseUrl();
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if ($host === null) return $baseUrl;

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?? 'https';
        $port = parse_url($baseUrl, PHP_URL_PORT);

        return "{$scheme}://{$host}" . ($port !== null ? ":{$port}" : '') . '/';
    }
}
