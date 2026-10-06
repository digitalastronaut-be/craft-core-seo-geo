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

use nystudio107\pluginvite\helpers\FileHelper;
use nystudio107\pluginvite\services\VitePluginService;

/**
 * Class CoreSeoGeoViteService
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class CoreSeoGeoViteService extends VitePluginService {
    protected const MANIFEST_FILE_NAME = '.vite/manifest.json';

    /**
     * @return void
     */
    public function init(): void {
        parent::init();

        if ($this->assetClass) {
            $bundle = new $this->assetClass();
            $this->manifestPath = FileHelper::createUrl($bundle->sourcePath, static::MANIFEST_FILE_NAME);
        }
    }
}
