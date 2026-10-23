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
 * Class TrustController
 *
 * Serves `trust.txt`. Generation is handled by `TrustService`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.14.0
 */
class TrustController extends Controller {
    protected array|bool|int $allowAnonymous = true;

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.14.0
     */
    public function actionIndex(): Response {
        $txt = CoreSeoGeo::getInstance()->getTrust()->getTrustTxt();

        $this->response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($txt);
    }
}
