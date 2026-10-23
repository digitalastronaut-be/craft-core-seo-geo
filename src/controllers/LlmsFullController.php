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
 * Class LlmsFullController
 *
 * Serves `llms-full.txt`. Generation is handled by `LlmsFullService`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.15.0
 */
class LlmsFullController extends Controller {
    protected array|bool|int $allowAnonymous = true;

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function actionIndex(): Response {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $txt = CoreSeoGeo::getInstance()->getLlmsFull()->getLlmsFullTxt($siteId);

        $this->response->getHeaders()->set('Content-Type', 'text/markdown; charset=UTF-8');

        return $this->asRaw($txt);
    }
}
