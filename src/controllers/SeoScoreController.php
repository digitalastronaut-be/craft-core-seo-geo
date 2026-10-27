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
use craft\helpers\App;
use craft\queue\QueueInterface;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\fields\SeoField;
use digitalastronaut\craftcoreseogeo\services\SeoScoreService;

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
     * Datastar -- the pane's "Refresh" button's `data-star-on:click` target. Mirrors
     * `MarkdownController::actionRefresh()`, except the recalculation itself runs on the queue
     * rather than inline (several checks fetch and parse the element's own rendered page, too
     * slow to do unconditionally on every request -- see `CalculateSeoScore`), so the patched
     * pane shows the pending chip rather than a finished one until the job actually runs.
     *
     * `_triggerQueue()` then kicks the queue so that job (and anything else waiting on it)
     * actually gets run without anyone having to reload the page for Craft's own CP job-tracker
     * to notice -- unlike a normal save, a Datastar `@get` click doesn't go through the CP JS
     * lifecycle that would otherwise do this automatically. Still fully async from this request's
     * point of view: it closes the connection to the browser first (same trick
     * `craft\controllers\QueueController::actionRun()` uses), so an unrelated job that happened
     * to already be queued -- a big import, say -- runs first and delays *this* job's completion,
     * but never blocks this click. The pane's own `data-star-on-interval` polling (see
     * `PluginTrait::_seoScoreSidebarHtml()`) is what picks the result up once it's actually done.
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

        $this->_triggerQueue();

        return $response;
    }

    /**
     * Re-renders the sidebar pane as-is, without queuing another `CalculateSeoScore` run --
     * unlike `actionRefresh()`, this is safe to poll. Backs the `data-star-on-interval` that
     * `PluginTrait::_seoScoreSidebarHtml()` puts on the pane while `pending`, so the pane picks
     * up the finished score on its own once the queue job behind an `actionRefresh()` call (or a
     * normal element save, which queues one the same way) finishes running. The plain HTML
     * response (Craft defaults new responses to `Response::FORMAT_HTML`) is enough for Datastar's
     * `@get` to match it to the pane by its `id` and patch it in -- no SSE stream needed since
     * there's only ever the one, final patch to send.
     *
     * @param int $elementId
     * @param int $siteId
     * @param int $fieldId
     * @return string
     * @throws NotFoundHttpException
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    public function actionStatus(int $elementId, int $siteId, int $fieldId): string {
        $this->requireCpRequest();

        $element = Craft::$app->getElements()->getElementById($elementId, null, $siteId, ['status' => null]);
        if ($element === null) {
            throw new NotFoundHttpException("No element found with ID \"{$elementId}\".");
        }

        $field = Craft::$app->getFields()->getFieldById($fieldId);
        if (!$field instanceof SeoField) {
            throw new NotFoundHttpException("No SEO/GEO field found with ID \"{$fieldId}\".");
        }

        return CoreSeoGeo::getInstance()->seoScoreSidebarHtml($element, $field);
    }

    /**
     * One shared poll for however many element index chips are currently `--pending`, instead of
     * each polling individually -- an index page can show up to 100 rows, and 100 independent
     * `data-star-on-interval` polls every couple seconds (one per chip, the sidebar pane's own
     * approach) would mean 100 requests every tick almost all the time, for a state that's idle
     * outside of a recalculation. `admin/elementIndexScorePolling.js` instead scans the page
     * itself for whichever chips are still pending and sends exactly one request per tick
     * listing just those, which also means the request shrinks to nothing as rows finish instead
     * of staying flat.
     *
     * `$refs` is a comma-separated list of `elementId:siteId:fieldId` triples (not a JSON body) so
     * this stays a plain `GET` -- same as `actionRefresh()`/`actionStatus()` -- rather than
     * needing a CSRF token threaded through the polling JS for a `POST`.
     *
     * @param string $refs
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.29.0
     */
    public function actionBatchStatus(string $refs): Response {
        $this->requireCpRequest();

        $seoScore = CoreSeoGeo::getInstance()->getSeoScore();
        $chips = [];

        foreach (explode(',', $refs) as $ref) {
            $parts = explode(':', $ref);
            if (count($parts) !== 3) continue;

            [$elementId, $siteId, $fieldId] = array_map('intval', $parts);
            if ($elementId <= 0 || $siteId <= 0 || $fieldId <= 0) continue;

            $html = $seoScore->batchStatusChipHtml($elementId, $siteId, $fieldId);
            if ($html === null) continue;

            $chips[SeoScoreService::chipId($elementId, $siteId, $fieldId)] = $html;
        }

        return $this->asJson($chips);
    }

    /**
     * Kicks the queue so the job just pushed by `actionRefresh()` (and anything else already
     * waiting) actually runs, without anyone having to reload the page for Craft's own CP
     * job-tracker to pick it up on its own. Mirrors `craft\controllers\QueueController::actionRun()`
     * almost exactly, including its guard clauses -- skip if web-triggered queue running is
     * disabled, or if a runner's already reserved jobs, or if there's nothing waiting -- the only
     * difference is that action builds and sends its own (trivial) response before closing the
     * connection, where this request has already sent its SSE patch by the time this runs, so
     * there's nothing left to build; `fastcgi_finish_request()` (under PHP-FPM) just finishes
     * closing the connection that's already been flushed.
     *
     * Deliberately drains the *whole* queue (`$queue->run()`), not just our own job -- there's no
     * way to run only one without potentially never running anything ahead of it in priority/id
     * order, which could leave our job stuck behind a reserved-but-stalled one forever. The
     * accepted tradeoff: an unrelated bulk import that happened to already be queued runs first
     * and delays this job's completion. It never blocks this request though, since the browser
     * already has its response by the time `run()` is called.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    private function _triggerQueue(): void {
        $queue = Craft::$app->getQueue();
        if (!$queue instanceof QueueInterface) return;
        if (!Craft::$app->getConfig()->getGeneral()->runQueueAutomatically) return;
        if ($queue->getHasReservedJobs() || !$queue->getHasWaitingJobs()) return;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        App::maxPowerCaptain();
        $queue->run();
    }
}
