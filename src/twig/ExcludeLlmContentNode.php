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
 * Class ExcludeLlmContentNode
 *
 * Compiles `{% excludeLlmContent %}...{% endexcludeLlmContent %}` to the block's own
 * output, unchanged, wrapped in its own pair of HTML comment markers. Nested inside an
 * `{% llmContent %}` block, this carves a sub-region (an ad slot, a related-posts widget)
 * back out of the otherwise-included content when the markers are read.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class ExcludeLlmContentNode extends Node {
    /**
     * @since v1.0.6
     */
    public const string START_MARKER = '<!--coreseogeo:exclude-start-->';

    /**
     * @since v1.0.6
     */
    public const string END_MARKER = '<!--coreseogeo:exclude-end-->';

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
