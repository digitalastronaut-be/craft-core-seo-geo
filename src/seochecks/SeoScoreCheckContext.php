<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\seochecks;

use Craft;

use craft\base\ElementInterface;

use digitalastronaut\craftcoreseogeo\models\SeoData;

use GuzzleHttp\Exception\GuzzleException;
use Masterminds\HTML5;

/**
 * Class SeoScoreCheckContext
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
final class SeoScoreCheckContext {
    /**
     * @see getDocument()
     *
     * @since v1.30.0
     */
    private bool $_documentFetched = false;
    private ?\DOMDocument $_document = null;

    /**
     * @param ElementInterface $element
     * @param SeoData $seoData
     * @param int $fieldId
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function __construct(
        public readonly ElementInterface $element,
        public readonly SeoData $seoData,
        public readonly int $fieldId,
    ) {
    }

    /**
     * Fetches and parses `$element`'s own rendered front-end page, for checks that need to
     * inspect the actual HTML output rather than just the `SeoField` value (e.g. whether
     * `<main>` exists, heading structure, `alt` text). Fetched at most once per context --
     * memoized here rather than in `SeoScoreService`, so the request only happens when at
     * least one registered, enabled check actually asks for it, and every such check in the
     * same run shares the one parsed document instead of re-fetching per check.
     *
     * `null` when there's nothing to check against: no front-end URL (headless/disabled
     * element), the request failed, or the response couldn't be parsed. Checks that depend on
     * this should treat a `null` document as **not applicable**, not a failure -- the page's
     * own markup hasn't actually been shown to be wrong, there's just nothing to look at.
     *
     * Masterminds\HTML5 parses every element into the `http://www.w3.org/1999/xhtml`
     * namespace (even though the source markup has no `xmlns`), so a plain unprefixed XPath
     * query like `//main` silently matches nothing against the returned document -- checks
     * must query by `local-name()` instead, e.g. `//*[local-name()="main"]`.
     *
     * @return \DOMDocument|null
     *
     * @author      Digitalastronaut
     * @since       v1.30.0
     */
    public function getDocument(): ?\DOMDocument {
        if ($this->_documentFetched) return $this->_document;

        $this->_documentFetched = true;

        $url = $this->element->getUrl();
        if ($url === null) return null;

        try {
            $html = (string)Craft::createGuzzleClient()->get($url)->getBody();
        } catch (GuzzleException) {
            return null;
        }

        try {
            $this->_document = (new HTML5())->loadHTML($html);
        } catch (\Throwable) {
            return null;
        }

        return $this->_document;
    }
}
