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
use craft\base\Element;
use craft\elements\Entry;
use craft\helpers\App;
use craft\queue\QueueInterface;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

use starfederation\datastar\ServerSentEventGenerator;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class MarkdownController
 *
 * Serves an entry's Markdown representation at its own URL plus `.md`. Generation and
 * caching are handled by `MarkdownService`. The route itself isn't even registered when
 * `Settings::$enableMarkdown` is off ({@see \digitalastronaut\craftcoreseogeo\PluginTrait::
 * registerSiteUrlRules()}); `actionRender()` additionally 404s per-entry when the entry's own
 * section has opted out via `ContentSeo::$markdownEnabled`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class MarkdownController extends Controller {
    // Only `render` is public - `refresh`/`status` are reached from the CP sidebar, so they
    // stay behind the default CP auth.
    protected array|bool|int $allowAnonymous = ['render'];

    /**
     * @param string $slug
     * @return Response
     * @throws NotFoundHttpException
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function actionRender(string $slug): Response {
        $uri = preg_replace('/\.md$/', '', $slug);
        if ($uri === '' || $uri === 'index') {
            $uri = Element::HOMEPAGE_URI;
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $entry = Entry::find()->uri($uri)->siteId($siteId)->one();

        if (!$entry) {
            throw new NotFoundHttpException("No entry found for URI \"{$uri}\".");
        }

        $sectionHandle = $entry->getSection()?->handle;
        if ($sectionHandle !== null && !CoreSeoGeo::getInstance()->getContentSeo()->isMarkdownEnabled($sectionHandle, $siteId)) {
            throw new NotFoundHttpException('Markdown generation is disabled for this entry\'s section.');
        }

        $markdown = CoreSeoGeo::getInstance()->getMarkdown()->getMarkdownForElement($entry);

        if ($markdown === '') {
            throw new NotFoundHttpException('Could not generate Markdown for this page.');
        }

        Craft::$app->getResponse()->getHeaders()->set('Content-Type', 'text/markdown; charset=UTF-8');

        return $this->asRaw($markdown);
    }

    /**
     * Queues a fresh `GenerateMarkdown` job for `$elementId`, then patches the CP sidebar's
     * `.core-seo-geo-markdown-pane` with the ("Generating…") result via Datastar - the
     * "Refresh" button's `data-star-on:click` target. Mirrors `SeoScoreController::
     * actionRefresh()`: the regeneration itself runs on the queue rather than inline, since it
     * fetches and converts the entry's own rendered page - too slow (and, on a single-worker web
     * server, liable to deadlock against this very request) to do inline - so the patched pane
     * shows the pending state rather than a finished one until the job actually runs.
     *
     * `_triggerQueue()` then kicks the queue so that job actually gets run without anyone having
     * to reload the page for Craft's own CP job-tracker to notice. Still fully async from this
     * request's point of view - see `SeoScoreController::actionRefresh()`'s own doc comment for
     * why. The pane's own `data-star-on-interval` polling (see `PluginTrait::
     * _markdownSidebarHtml()`) is what picks the result up once it's actually done.
     *
     * @param int $elementId
     * @param int $siteId
     * @return Response
     * @throws NotFoundHttpException
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function actionRefresh(int $elementId, int $siteId): Response {
        $this->requireCpRequest();

        $entry = Craft::$app->getElements()->getElementById($elementId, Entry::class, $siteId, ['status' => null]);
        if (!$entry instanceof Entry) {
            throw new NotFoundHttpException("No entry found with ID \"{$elementId}\".");
        }

        $markdown = CoreSeoGeo::getInstance()->getMarkdown();
        $markdown->markPending($entry);
        $markdown->queueRefresh($entry);

        $html = CoreSeoGeo::getInstance()->markdownSidebarHtml($entry);

        $sse = new ServerSentEventGenerator();
        $sse->sendHeaders();
        $sse->patchElements($html, ['selector' => '.core-seo-geo-markdown-pane', 'mode' => 'outer']);

        $response = Craft::$app->getResponse();
        $response->isSent = true;

        $this->_triggerQueue();

        return $response;
    }

    /**
     * Re-renders the sidebar pane as-is, without queuing another `GenerateMarkdown` run -
     * unlike `actionRefresh()`, this is safe to poll. Backs the `data-star-on-interval` that
     * `PluginTrait::_markdownSidebarHtml()` puts on the pane while pending, so the pane picks up
     * the finished Markdown on its own once the queue job behind an `actionRefresh()` call (or a
     * normal entry save, which queues one the same way) finishes running. The plain HTML
     * response (Craft defaults new responses to `Response::FORMAT_HTML`) is enough for
     * Datastar's `@get` to match it to the pane by its `id` and patch it in - no SSE stream
     * needed since there's only ever the one, final patch to send. Mirrors `SeoScoreController::
     * actionStatus()`.
     *
     * @param int $elementId
     * @param int $siteId
     * @return string
     * @throws NotFoundHttpException
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function actionStatus(int $elementId, int $siteId): string {
        $this->requireCpRequest();

        $entry = Craft::$app->getElements()->getElementById($elementId, Entry::class, $siteId, ['status' => null]);
        if (!$entry instanceof Entry) {
            throw new NotFoundHttpException("No entry found with ID \"{$elementId}\".");
        }

        return CoreSeoGeo::getInstance()->markdownSidebarHtml($entry);
    }

    /**
     * Kicks the queue so the job just pushed by `actionRefresh()` (and anything else already
     * waiting) actually runs, without anyone having to reload the page for Craft's own CP
     * job-tracker to pick it up on its own. Copied from (and kept in sync by hand with)
     * `SeoScoreController::_triggerQueue()` - see that method's doc comment for the full
     * reasoning, including why this drains the whole queue rather than just this one job.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
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
