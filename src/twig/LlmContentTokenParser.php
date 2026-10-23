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
 * Class LlmContentTokenParser
 *
 * Parses `{% llmContent %}...{% endllmContent %}`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class LlmContentTokenParser extends AbstractTokenParser {
    /**
     * @return string
     */
    public function getTag(): string {
        return 'llmContent';
    }

    /**
     * @param Token $token
     * @return LlmContentNode
     */
    public function parse(Token $token): LlmContentNode {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideEnd'], true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new LlmContentNode($body, $lineno, $this->getTag());
    }

    /**
     * @param Token $token
     * @return bool
     */
    public function decideEnd(Token $token): bool {
        return $token->test('endllmContent');
    }
}
