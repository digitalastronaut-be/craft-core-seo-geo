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
use craft\elements\Asset;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class FaviconService
 *
 * Derives every icon format a site needs from a raster source asset via Craft's own
 * asset transforms.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.8
 */
class FaviconService extends Component {
    /**
     * @param Asset $asset
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function getFaviconPngUrl(Asset $asset): string {
        return $asset->getUrl(['width' => 32, 'height' => 32, 'format' => 'png']);
    }

    /**
     * @param Asset $asset
     * @return string|null `null` if the configured image driver is GD, which has no ICO
     * encoder.
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    public function getFaviconIcoUrl(Asset $asset): ?string {
        if (!Craft::$app->getImages()->getIsImagick()) return null;

        return $asset->getUrl(['width' => 32, 'height' => 32, 'format' => 'ico']);
    }

    /**
     * @param Asset $asset
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    public function getAppleTouchIconUrl(Asset $asset): string {
        return $asset->getUrl(['width' => 180, 'height' => 180, 'format' => 'png']);
    }

    /**
     * @param Asset $asset
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    public function getIcon192Url(Asset $asset): string {
        return $asset->getUrl(['width' => 192, 'height' => 192, 'format' => 'png']);
    }

    /**
     * @param Asset $asset
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    public function getIcon512Url(Asset $asset): string {
        return $asset->getUrl(['width' => 512, 'height' => 512, 'format' => 'png']);
    }

    /**
     * @param Asset $asset
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    public function getIconMaskUrl(Asset $asset): string {
        return $this->getIcon512Url($asset);
    }

    /**
     * `theme_color`/`background_color` are omitted entirely when `Settings::$themeColor` isn't
     * set, rather than sent as an empty string - an absent key is valid per the manifest spec,
     * an empty one isn't.
     *
     * @param Asset $asset
     * @return array{name: string, icons: array<int, array{src: string, type: string, sizes: string, purpose?: string}>, theme_color?: string, background_color?: string}
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    public function getManifest(Asset $asset): array {
        $themeColor = CoreSeoGeo::getInstance()->getSettings()->themeColor;

        return [
            'name' => Craft::$app->getSites()->getCurrentSite()->name,
            'icons' => [
                ['src' => $this->getIcon192Url($asset), 'type' => 'image/png', 'sizes' => '192x192'],
                ['src' => $this->getIconMaskUrl($asset), 'type' => 'image/png', 'sizes' => '512x512', 'purpose' => 'maskable'],
                ['src' => $this->getIcon512Url($asset), 'type' => 'image/png', 'sizes' => '512x512'],
            ],
            ...($themeColor ? ['theme_color' => $themeColor, 'background_color' => $themeColor] : []),
        ];
    }
}
