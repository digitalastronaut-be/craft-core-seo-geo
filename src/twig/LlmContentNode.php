<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\twig;

use Twig\Compiler;
use Twig\Node\Node;

/**
 * Class LlmContentNode
 *
 * Compiles `{% llmContent %}...{% endllmContent %}` to the block's own output, unchanged,
 * wrapped in a pair of HTML comment markers. The markers have no effect on the rendered
 * page - they exist so a later fetch of that same page's HTML can locate exactly the
 * regions a template author marked as "content" (see `MarkdownService`), rather
 * than this plugin guessing at content via CSS selectors.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class LlmContentNode extends Node {
    /**
     * @since v1.0.6
     */
    public const string START_MARKER = '<!--coreseogeo:llmcontent-start-->';

    /**
     * @since v1.0.6
     */
    public const string END_MARKER = '<!--coreseogeo:llmcontent-end-->';

    /**
     * @param Node $body
     * @param int $lineno
     * @param string $tag
     */
    public function __construct(Node $body, int $lineno, string $tag) {
        parent::__construct(['body' => $body], [], $lineno, $tag);
    }

    /**
     * @param Compiler $compiler
     * @return void
     */
    public function compile(Compiler $compiler): void {
        $compiler
            ->addDebugInfo($this)
            ->write('echo ')->repr(self::START_MARKER)->raw(";\n")
            ->subcompile($this->getNode('body'))
            ->write('echo ')->repr(self::END_MARKER)->raw(";\n");
    }
}
