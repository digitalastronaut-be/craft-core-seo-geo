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
 * Class SecurityController
 *
 * Serves `security.txt` at both the location RFC 9116 prefers and the root location kept for
 * backwards compatibility - see `PluginTrait::registerSiteUrlRules()`. Generation is
 * handled by `SecurityService`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.13.0
 */
class SecurityController extends Controller {
    protected array|bool|int $allowAnonymous = true;

    /**
     * @return Response
     * @throws NotFoundHttpException if the required `Contact`/`Expires` fields aren't both set
     * - see `SecurityService::getSecurityTxt()`
     *
     * @author      Digitalastronaut
     * @since       v1.13.0
     */
    public function actionIndex(): Response {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $txt = CoreSeoGeo::getInstance()->getSecurity()->getSecurityTxt($siteId);

        if ($txt === '') {
            throw new NotFoundHttpException('security.txt is missing its required Contact/Expires fields.');
        }

        $this->response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($txt);
    }
}
