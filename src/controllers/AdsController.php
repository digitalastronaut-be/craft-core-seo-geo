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

use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

use yii\web\Response;

/**
 * Class AdsController
 *
 * Serves `ads.txt`. Generation is handled by `AdsService`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.12.0
 */
class AdsController extends Controller {
    protected array|bool|int $allowAnonymous = true;

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.12.0
     */
    public function actionIndex(): Response {
        $txt = CoreSeoGeo::getInstance()->getAds()->getAdsTxt();

        $this->response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($txt);
    }
}
