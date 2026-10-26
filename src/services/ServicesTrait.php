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
                'schemaOrg' => [
                    'class' => SchemaOrgService::class,
                ],
                'seoScore' => [
                    'class' => SeoScoreService::class,
                ],
                'botDetection' => [
                    'class' => BotDetectionService::class,
                ],
                'markdown' => [
                    'class' => MarkdownService::class,
                ],
                'hreflang' => [
                    'class' => HreflangService::class,
                ],
                'favicon' => [
                    'class' => FaviconService::class,
                ],
                'sitemap' => [
                    'class' => SitemapService::class,
                ],
                'contentSeo' => [
                    'class' => ContentSeoService::class,
                ],
                'structuredDataMappings' => [
                    'class' => StructuredDataMappingService::class,
                ],
                'globalStructuredData' => [
                    'class' => GlobalStructuredDataService::class,
                ],
                'jsonLdGraph' => [
                    'class' => JsonLdGraphService::class,
                ],
                'trackingScripts' => [
                    'class' => TrackingScriptsService::class,
                ],
                'txtFiles' => [
                    'class' => TxtFilesService::class,
                ],
                'redirects' => [
                    'class' => RedirectsService::class,
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

    /**
     * @return SchemaOrgService
     */
    public function getSchemaOrg(): SchemaOrgService {
        return $this->get('schemaOrg');
    }

    /**
     * @return SeoScoreService
     */
    public function getSeoScore(): SeoScoreService {
        return $this->get('seoScore');
    }

    /**
     * @return BotDetectionService
     */
    public function getBotDetection(): BotDetectionService {
        return $this->get('botDetection');
    }

    /**
     * @return MarkdownService
     */
    public function getMarkdown(): MarkdownService {
        return $this->get('markdown');
    }

    /**
     * @return HreflangService
     */
    public function getHreflang(): HreflangService {
        return $this->get('hreflang');
    }

    /**
     * @return FaviconService
     */
    public function getFavicon(): FaviconService {
        return $this->get('favicon');
    }

    /**
     * @return SitemapService
     */
    public function getSitemap(): SitemapService {
        return $this->get('sitemap');
    }

    /**
     * @return ContentSeoService
     */
    public function getContentSeo(): ContentSeoService {
        return $this->get('contentSeo');
    }

    /**
     * @return StructuredDataMappingService
     */
    public function getStructuredDataMappings(): StructuredDataMappingService {
        return $this->get('structuredDataMappings');
    }

    /**
     * @return GlobalStructuredDataService
     */
    public function getGlobalStructuredData(): GlobalStructuredDataService {
        return $this->get('globalStructuredData');
    }

    /**
     * @return JsonLdGraphService
     */
    public function getJsonLdGraph(): JsonLdGraphService {
        return $this->get('jsonLdGraph');
    }

    /**
     * @return TrackingScriptsService
     */
    public function getTrackingScripts(): TrackingScriptsService {
        return $this->get('trackingScripts');
    }

    /**
     * @return TxtFilesService
     */
    public function getTxtFiles(): TxtFilesService {
        return $this->get('txtFiles');
    }

    /**
     * @return RedirectsService
     */
    public function getRedirects(): RedirectsService {
        return $this->get('redirects');
    }
}
