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

use craft\helpers\Json;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class FaviconController
 *
 * Serves `manifest.webmanifest`, computed on every request from the configured favicon
 * asset rather than written to disk -- `FaviconService` already resolves each icon through
 * Craft's own asset transform URLs, so there's nothing here to regenerate or cache.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.8
 */
class FaviconController extends Controller {
    protected array|bool|int $allowAnonymous = true;

    /**
     * @return Response
     * @throws NotFoundHttpException if no favicon asset is configured
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    public function actionManifest(): Response {
        $asset = CoreSeoGeo::getInstance()->getSettings()->getFaviconAsset();

        if ($asset === null) {
            throw new NotFoundHttpException('No favicon asset is configured.');
        }

        $manifest = CoreSeoGeo::getInstance()->getFavicon()->getManifest($asset);

        $this->response->getHeaders()->set('Content-Type', 'application/manifest+json');

        return $this->asRaw(Json::encode($manifest));
    }
}
