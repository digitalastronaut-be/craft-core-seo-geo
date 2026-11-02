<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\web\assets\images;

use craft\web\AssetBundle;

/**
 * Class ImagesAssetBundle
 *
 * Publishes the plugin's static CP images - currently just `no-image.png`, the placeholder
 * used by `_previews.twig`'s social cards when the SEO field has no image set.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class ImagesAssetBundle extends AssetBundle {
    /**
     * @return void
     */
    public function init(): void {
        $this->sourcePath = '@digitalastronaut/craftcoreseogeo/web/assets/images';
        $this->publishOptions = [
            'only' => ['no-image.png'],
        ];

        parent::init();
    }
}
