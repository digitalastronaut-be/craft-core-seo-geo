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

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Class ExcludeLlmContentTokenParser
 *
 * Parses `{% excludeLlmContent %}...{% endexcludeLlmContent %}`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class ExcludeLlmContentTokenParser extends AbstractTokenParser {
    /**
     * @return string
     */
    public function getTag(): string {
        return 'excludeLlmContent';
    }

    /**
     * @param Token $token
     * @return ExcludeLlmContentNode
     */
    public function parse(Token $token): ExcludeLlmContentNode {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideEnd'], true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new ExcludeLlmContentNode($body, $lineno, $this->getTag());
    }

    /**
     * @param Token $token
     * @return bool
     */
    public function decideEnd(Token $token): bool {
        return $token->test('endexcludeLlmContent');
    }
}
