<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\controllers;

use Craft;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class SitemapController
 *
 * Serves `sitemap.xml` (the sitemap index) and its per-group `sitemap-<handle>.xml`
 * sub-sitemaps. Generation and caching are handled by `SitemapService`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.1.0
 */
class SitemapController extends Controller {
    protected array|bool|int $allowAnonymous = true;

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function actionIndex(): Response {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $xml = CoreSeoGeo::getInstance()->getSitemap()->getIndexXml($siteId);

        return $this->_asXml($xml);
    }

    /**
     * @param string $group the requested sub-sitemap, with its `.xml` suffix still attached -
     * either a bare group handle (`pages`) or a paginated one (`news-2`)
     * @return Response
     * @throws NotFoundHttpException if `$group` doesn't resolve to a known group/page
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function actionGroup(string $group): Response {
        $groupKey = preg_replace('/\.xml$/', '', $group);
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $sitemap = CoreSeoGeo::getInstance()->getSitemap();

        [$groupHandle, $page] = $sitemap->resolveGroupKey($siteId, $groupKey);
        $xml = $groupHandle === null ? null : $sitemap->getGroupXml($siteId, $groupHandle, $page);

        if ($xml === null) {
            throw new NotFoundHttpException("No sitemap group found for \"{$groupKey}\".");
        }

        return $this->_asXml($xml);
    }

    /**
     * @param string $xml
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _asXml(string $xml): Response {
        $this->response->getHeaders()->set('Content-Type', 'application/xml; charset=UTF-8');

        return $this->asRaw($xml);
    }
}
