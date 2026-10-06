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

use craft\helpers\App;

use digitalastronaut\craftcoreseogeo\web\assets\admin\AdminAssetBundle;

use nystudio107\pluginvite\services\VitePluginService;

/**
 * Class ServicesTrait
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
trait ServicesTrait {
    /**
     * @return array
     */
    public static function config(): array {
        return [
            'components' => [
                'vite' => [
                    'class' => CoreSeoGeoViteService::class,
                    'assetClass' => AdminAssetBundle::class,
                    'useForAllRequests' => true,
                    'pluginDevServerEnvVar' => 'CORE_SEO_GEO_VITE_DEVSERVER',
                    'useDevServer' => true,
                    'checkDevServer' => true,
                    'devServerInternal' => 'http://localhost:3004',
                    'devServerPublic' => App::env('PRIMARY_SITE_URL') . ':3005',
                    'errorEntry' => 'src/web/assets/admin/admin.js',
                ],
                'structuredData' => [
                    'class' => StructuredDataService::class,
                ],
            ],
        ];
    }

    /**
     * @return VitePluginService
     */
    public function getVite(): VitePluginService {
        return $this->get('vite');
    }

    /**
     * @return StructuredDataService
     */
    public function getStructuredData(): StructuredDataService {
        return $this->get('structuredData');
    }
}
