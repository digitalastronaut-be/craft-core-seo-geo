<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;

use digitalastronaut\craftcoreseogeo\twig\ExcludeLlmContentNode;
use digitalastronaut\craftcoreseogeo\twig\LlmContentNode;

use GuzzleHttp\Exception\GuzzleException;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use yii\caching\TagDependency;

/**
 * Class MarkdownService
 *
 * Generates, converts, and caches an element's Markdown representation: fetches the
 * element's own public URL (see `docs/llm-markdown-roadmap.md`, Part A3), extracts the
 * `{% llmContent %}`-marked regions, and converts them via `league/html-to-markdown`.
 *
 * Caches the result (success or failure) under a `TagDependency` built from
 * `collectCacheInfoForElement()` - the same mechanism Craft's own `{% cache %}` tag
 * uses - so it invalidates automatically whenever anything the page depends on
 * changes, with no custom pending/queued flags to maintain.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class MarkdownService extends Component {
    private const CACHE_TAG = 'core-seo-geo-markdown';

    /**
     * @var array<string, mixed>
     */
    private const DEFAULT_OPTIONS = [
        'header_style' => 'atx',
        'strip_tags' => true,
        'remove_nodes' => 'img picture style form button input select option svg script nav noscript video audio source',
    ];

    /**
     * @param ElementInterface $element
     * @return string Empty string if generation failed - see `getCachedResult()` for
     * why it failed.
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function getMarkdownForElement(ElementInterface $element): string {
        return $this->getResult($element)['markdown'];
    }

    /**
     * Cached read-through: returns the cached result if one exists, otherwise
     * generates, caches, and returns it.
     *
     * @param ElementInterface $element
     * @return array{markdown: string, failureReason: string|null, date: int}
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function getResult(ElementInterface $element): array {
        $cached = $this->getCachedResult($element);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->_generate($element);
        $this->_store($element, $result);

        return $result;
    }

    /**
     * Reads the cached result without generating one - for CP status display, which
     * must never block an editor's page load on an internal HTTP fetch.
     *
     * @param ElementInterface $element
     * @return array{markdown: string, failureReason: string|null, date: int}|null
     * `null` if nothing has been cached for this element yet.
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function getCachedResult(ElementInterface $element): ?array {
        $cached = Craft::$app->getCache()->get($this->_cacheKey($element));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param string $html
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function htmlToMarkdown(string $html): string {
        $converter = new HtmlConverter(self::DEFAULT_OPTIONS);
        $converter->getEnvironment()->addConverter(new TableConverter());

        return $this->_cleanup($converter->convert($html));
    }

    /**
     * A rough token-count estimate for a chunk of Markdown - the standard
     * characters-divided-by-four heuristic, with no tokenizer dependency.
     *
     * @param string $markdown
     * @return int
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public static function estimateTokens(string $markdown): int {
        return (int)ceil(mb_strlen($markdown) / 4);
    }

    /**
     * @param ElementInterface $element
     * @return array{markdown: string, failureReason: string|null, date: int}
     */
    private function _generate(ElementInterface $element): array {
        $url = $element->getUrl();
        if ($url === null) {
            return $this->_failure('Entry has no front-end URL.');
        }

        try {
            $html = (string)Craft::createGuzzleClient()->get($url)->getBody();
        } catch (GuzzleException $e) {
            return $this->_failure("Could not fetch \"{$url}\": {$e->getMessage()}");
        }

        $content = $this->_extractLlmContent($html);
        $markdown = trim($this->htmlToMarkdown($content));

        if ($markdown === '') {
            return $this->_failure('The page has no {% llmContent %} blocks to convert.');
        }

        return ['markdown' => $markdown, 'failureReason' => null, 'date' => time()];
    }

    /**
     * @param string $reason
     * @return array{markdown: string, failureReason: string|null, date: int}
     */
    private function _failure(string $reason): array {
        return ['markdown' => '', 'failureReason' => $reason, 'date' => time()];
    }

    /**
     * Pulls every `{% llmContent %}`-marked region out of a fetched page's HTML, with
     * any nested `{% excludeLlmContent %}` region removed from each.
     *
     * @param string $html
     * @return string
     */
    private function _extractLlmContent(string $html): string {
        $pattern = sprintf(
            '/%s(.*?)%s/s',
            preg_quote(LlmContentNode::START_MARKER, '/'),
            preg_quote(LlmContentNode::END_MARKER, '/'),
        );

        if (!preg_match_all($pattern, $html, $matches)) {
            return '';
        }

        $excludedPattern = sprintf(
            '/%s.*?%s/s',
            preg_quote(ExcludeLlmContentNode::START_MARKER, '/'),
            preg_quote(ExcludeLlmContentNode::END_MARKER, '/'),
        );

        return preg_replace($excludedPattern, '', implode('', $matches[1]));
    }

    /**
     * @param ElementInterface $element
     * @param array{markdown: string, failureReason: string|null, date: int} $result
     * @return void
     */
    private function _store(ElementInterface $element, array $result): void {
        $elements = Craft::$app->getElements();
        $elements->startCollectingCacheInfo();
        $elements->collectCacheInfoForElement($element);
        [$dependency, $duration] = $elements->stopCollectingCacheInfo();

        $dependency ??= new TagDependency();
        $dependency->tags[] = self::CACHE_TAG;

        Craft::$app->getCache()->set($this->_cacheKey($element), $result, $duration ?? 0, $dependency);
    }

    /**
     * @param ElementInterface $element
     * @return array
     */
    private function _cacheKey(ElementInterface $element): array {
        return [self::CACHE_TAG, 'page', $element->id, $element->siteId];
    }

    /**
     * @param string $markdown
     * @return string
     */
    private function _cleanup(string $markdown): string {
        // Strip leftover HTML tags from link text, and drop links that end up with no text left.
        $markdown = preg_replace_callback('/\[((?:[^\[\]]|\[[^\]]*\])*)\]\(([^)]+)\)/', function(array $match): string {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($match[1])));

            return $text === '' ? '' : "[{$text}]({$match[2]})";
        }, $markdown);

        // Remove empty list items left behind after a `remove_nodes` tag was the item's only content.
        $markdown = preg_replace('/^[ \t]*-[ \t]*$/m', '', $markdown);

        // The library's own sanitize() already decodes entities, but the strip_tags() pass above
        // can expose entities that were nested inside a tag's attributes (e.g. a title="&amp;").
        $markdown = html_entity_decode($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse runs of blank lines (left behind by removed nodes/list items) to a single one.
        return preg_replace('/(\n[ \t]*){2,}/', "\n\n", $markdown);
    }
}
