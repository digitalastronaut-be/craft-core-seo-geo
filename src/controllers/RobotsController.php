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

use yii\web\Response;

/**
 * Class RobotsController
 *
 * Serves `robots.txt`. Generation is handled by `RobotsService`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.10.0
 */
class RobotsController extends Controller {
    protected array|bool|int $allowAnonymous = true;

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    public function actionIndex(): Response {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $txt = CoreSeoGeo::getInstance()->getRobots()->getRobotsTxt($siteId);

        $this->response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($txt);
    }
}
