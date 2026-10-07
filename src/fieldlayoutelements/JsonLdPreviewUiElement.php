<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseUiElement;
use craft\helpers\Cp;
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\elements\StructuredData;

/**
 * Class JsonLdPreviewUiElement
 *
 * Read-only, pretty-printed JSON-LD preview - the same disabled-textarea rendering
 * `StructuredDataField`'s own input uses.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class JsonLdPreviewUiElement extends BaseUiElement {
    /**
     * @inheritdoc
     */
    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string {
        if (!$element instanceof StructuredData) return null;

        $json = Json::encode($element->toJsonLd(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $input = Craft::$app->getView()->renderTemplate('core-seo-geo/fields/structured-data/_input', [
            'id' => 'structuredDataJsonLd',
            'name' => 'structuredDataJsonLd',
            'json' => $json,
            'rows' => max(4, min(20, substr_count($json, "\n") + 1)),
        ]);

        return Cp::fieldHtml($input, [
            'label' => Craft::t('core-seo-geo', 'JSON-LD Preview'),
            'instructions' => Craft::t('core-seo-geo', 'The full computed object, exactly as it would be output on a page.'),
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function selectorLabel(): string {
        return Craft::t('core-seo-geo', 'JSON-LD Preview');
    }
}
