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
use craft\base\ElementInterface;

/**
 * Class HreflangService
 * 
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.8
 */
class HreflangService extends Component {
    /**
     * Includes a trailing `x-default` entry pointing at the install's primary site
     * (`Craft::$app->getSites()->getPrimarySite()`), falling back to the first resolved
     * alternate when the primary site isn't among this element's own supported sites, or the
     * element has no localized copy there.
     *
     * @param ElementInterface $element
     * @return array<int, array{language: string, url: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    public function getAlternates(ElementInterface $element): array {
        if (!$element->id) return [];

        $siteIds = array_map(
            static fn(mixed $site): int => is_array($site) ? $site['siteId'] : $site,
            $element->getSupportedSites(),
        );

        if (\count($siteIds) < 2) return [];

        $localizedElements = $element::find()->id($element->id)->siteId($siteIds)->all();
        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;
        $alternates = [];
        $defaultUrl = null;

        foreach ($localizedElements as $localizedElement) {
            $url = $localizedElement->getUrl();

            if ($url === null) continue;

            $alternates[] = [
                'language' => $localizedElement->getSite()->language,
                'url' => $url,
            ];

            if ($localizedElement->siteId === $primarySiteId) {
                $defaultUrl = $url;
            }
        }

        if ($alternates === []) return [];

        $alternates[] = ['language' => 'x-default', 'url' => $defaultUrl ?? $alternates[0]['url']];

        return $alternates;
    }
}
