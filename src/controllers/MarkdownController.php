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
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

use starfederation\datastar\ServerSentEventGenerator;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class MarkdownController
 *
 * Serves an entry's Markdown representation at its own URL plus `.md`. Generation and
 * caching are handled by `MarkdownService`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class MarkdownController extends Controller {
    // Only `render` is public - `refresh` forces a re-fetch of the entry's own front-end
    // URL and is reached from the CP sidebar, so it stays behind the default CP auth.
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

        $markdown = CoreSeoGeo::getInstance()->getMarkdown()->getMarkdownForElement($entry);

        if ($markdown === '') {
            throw new NotFoundHttpException('Could not generate Markdown for this page.');
        }

        Craft::$app->getResponse()->getHeaders()->set('Content-Type', 'text/markdown; charset=UTF-8');

        return $this->asRaw($markdown);
    }

    /**
     * Forces a fresh fetch + conversion of `$elementId`'s Markdown, bypassing the cache,
     * then patches the CP sidebar's `.core-seo-geo-markdown-pane` with the result via
     * Datastar - the "Refresh" button's `data-on-click` target.
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

        CoreSeoGeo::getInstance()->getMarkdown()->refresh($entry);

        $html = CoreSeoGeo::getInstance()->markdownSidebarHtml($entry);

        $sse = new ServerSentEventGenerator();
        $sse->sendHeaders();
        $sse->patchElements($html, ['selector' => '.core-seo-geo-markdown-pane', 'mode' => 'outer']);

        $response = Craft::$app->getResponse();
        $response->isSent = true;

        return $response;
    }
}
