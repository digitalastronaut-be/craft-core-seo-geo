<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\models;

use craft\base\Model;

/**
 * Class WebPage
 *
 * @see         https://schema.org/WebPage
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class WebPage extends Model {
    public mixed $breadcrumb = null; // A set of links that can help a user understand and navigate a website hierarchy. Accepts a `BreadcrumbList` or plain text.
    public ?string $lastReviewed = null; // The date on which the content on this web page was last reviewed for accuracy and/or completeness.
    public mixed $mainContentOfPage = null; // Indicates if this web page element carries the main content of the page. Accepts a `WebPageElement`.
    public mixed $primaryImageOfPage = null; // Indicates the main image on the page. Accepts an `ImageObject`.
    public ?string $relatedLink = null; // A link related to this web page, for example to other related web pages.
    public mixed $reviewedBy = null; // People or organizations that have reviewed the content on this web page. Accepts an `Organization` or `Person`.
    public ?string $significantLink = null; // One of the more significant URLs on the page. Typically, these are the ones someone will most likely want to visit next after arriving from a search engine.
    public mixed $speakable = null; // Indicates sections of a web page that are particularly 'speakable' in the sense of being highlighted as being well-suited for audio output. Accepts a `SpeakableSpecification` or a URL.
    public mixed $specialty = null; // One of the domain specialities to which this web page's content applies. Accepts a `Specialty`.
}
