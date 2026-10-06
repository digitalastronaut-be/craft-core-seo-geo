<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\web\assets\admin;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Class AdminAssetBundle
 *
 * Doesn't declare `$css`/`$js` itself — actually loading the built (or, in
 * dev mode, live) entry is `VitePluginService::register()`'s job, called
 * from `PluginTrait::registerCpAssets()`. This bundle's only purpose is to
 * give the Vite service a `sourcePath` to publish and resolve `/cpresources/`
 * URLs (manifest.json, hashed asset paths) against once built.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class AdminAssetBundle extends AssetBundle {
    /**
     * @return void
     */
    public function init(): void {
        $this->sourcePath = '@digitalastronaut/craftcoreseogeo/web/assets/dist';
        $this->depends = [CpAsset::class];

        parent::init();
    }
}
