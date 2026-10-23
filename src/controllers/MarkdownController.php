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
    protected array|bool|int $allowAnonymous = true;

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
}
