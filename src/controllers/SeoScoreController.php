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
use digitalastronaut\craftcoreseogeo\fields\SeoField;

use starfederation\datastar\ServerSentEventGenerator;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class SeoScoreController
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.27.0
 */
class SeoScoreController extends Controller {
    /**
     * Queues a fresh `CalculateSeoScore` run for `$fieldId` on `$elementId`, then patches the
     * entry edit page's SEO/GEO sidebar pane with the (now "Calculating...") result via
     * Datastar -- the pane's "Refresh" button's `data-on-click` target. Mirrors
     * `MarkdownController::actionRefresh()`, except the recalculation itself runs on the queue
     * rather than inline, so the patched pane shows the pending chip rather than a finished one
     * until the job actually runs.
     *
     * @param int $elementId
     * @param int $siteId
     * @param int $fieldId
     * @return Response
     * @throws NotFoundHttpException
     *
     * @author      Digitalastronaut
     * @since       v1.27.0
     */
    public function actionRefresh(int $elementId, int $siteId, int $fieldId): Response {
        $this->requireCpRequest();

        $element = Craft::$app->getElements()->getElementById($elementId, null, $siteId, ['status' => null]);
        if ($element === null) {
            throw new NotFoundHttpException("No element found with ID \"{$elementId}\".");
        }

        $field = Craft::$app->getFields()->getFieldById($fieldId);
        if (!$field instanceof SeoField) {
            throw new NotFoundHttpException("No SEO/GEO field found with ID \"{$fieldId}\".");
        }

        $seoScore = CoreSeoGeo::getInstance()->getSeoScore();
        $seoScore->markPending($element, $fieldId);
        $seoScore->calculate($element, $fieldId);

        $html = CoreSeoGeo::getInstance()->seoScoreSidebarHtml($element, $field);

        $sse = new ServerSentEventGenerator();
        $sse->sendHeaders();
        $sse->patchElements($html, ['selector' => "#core-seo-geo-score-pane-{$fieldId}", 'mode' => 'outer']);

        $response = Craft::$app->getResponse();
        $response->isSent = true;

        return $response;
    }
}
