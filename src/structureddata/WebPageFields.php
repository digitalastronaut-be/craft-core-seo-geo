<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\structureddata;

use Craft;

/**
 * Class WebPageFields
 *
 * The settings-page fields for the `WebPage` structured data type. Each entry's `property`
 * matches a property on {@see \digitalastronaut\craftcoreseogeo\models\WebPage}.
 *
 * @see       https://schema.org/WebPage
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class WebPageFields implements StructuredDataTypeFieldsInterface {
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function fields(): array {
        return [
            [
                'property' => 'breadcrumb',
                'label' => Craft::t('core-seo-geo', 'Breadcrumb'),
                'instructions' => Craft::t('core-seo-geo', 'A set of links that help a user understand and navigate the site hierarchy.'),
                'default' => '{{ craft.coreSeoGeo.createBreadcrumbs([{ label: title, href: url }]) }}',
            ],
            [
                'property' => 'lastReviewed',
                'label' => Craft::t('core-seo-geo', 'Last Reviewed'),
                'instructions' => Craft::t('core-seo-geo', 'The date this page was last reviewed for accuracy and/or completeness.'),
                'default' => '{{ dateUpdated|date("Y-m-d") }}',
            ],
            [
                'property' => 'mainContentOfPage',
                'label' => Craft::t('core-seo-geo', 'Main Content Of Page'),
                'instructions' => Craft::t('core-seo-geo', "Indicates the web page element that carries the page's main content."),
                'default' => '{{ craft.coreSeoGeo.createWebPageElement("main") }}',
            ],
            [
                'property' => 'primaryImageOfPage',
                'label' => Craft::t('core-seo-geo', 'Primary Image Of Page'),
                'instructions' => Craft::t('core-seo-geo', 'The main image on the page.'),
            ],
            [
                'property' => 'relatedLink',
                'label' => Craft::t('core-seo-geo', 'Related Link'),
                'instructions' => Craft::t('core-seo-geo', 'A link related to this page, for example to other related pages.'),
            ],
            [
                'property' => 'reviewedBy',
                'label' => Craft::t('core-seo-geo', 'Reviewed By'),
                'instructions' => Craft::t('core-seo-geo', 'The person or organization that reviewed the content on this page.'),
            ],
            [
                'property' => 'significantLink',
                'label' => Craft::t('core-seo-geo', 'Significant Link'),
                'instructions' => Craft::t('core-seo-geo', 'One of the more significant links on the page, typically the one a visitor is most likely to want to visit next.'),
            ],
            [
                'property' => 'speakable',
                'label' => Craft::t('core-seo-geo', 'Speakable'),
                'instructions' => Craft::t('core-seo-geo', "Sections of the page that are well-suited for audio output, e.g. by a voice assistant."),
            ],
            [
                'property' => 'specialty',
                'label' => Craft::t('core-seo-geo', 'Specialty'),
                'instructions' => Craft::t('core-seo-geo', "One of the domain specialities to which this page's content applies."),
            ],
        ];
    }
}
