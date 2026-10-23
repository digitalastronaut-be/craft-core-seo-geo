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
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;

use digitalastronaut\craftcoreseogeo\elements\StructuredData;
use digitalastronaut\craftcoreseogeo\fields\StructuredDataField;
use digitalastronaut\craftcoreseogeo\records\StructuredDataMapping as StructuredDataMappingRecord;

/**
 * Class FieldMappingUiElement
 *
 * Read-only reference table showing each property the owning `StructuredDataMapping` computes,
 * alongside its configured Twig template and this row's current computed value - purely for
 * context, nothing here is editable. Renders nothing for a standalone object (`$mappingId`
 * null), since there's no mapping to show.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class FieldMappingUiElement extends BaseUiElement {
    /**
     * @inheritdoc
     */
    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string {
        if (!$element instanceof StructuredData || $element->mappingId === null) return null;

        $mapping = StructuredDataMappingRecord::findOne($element->mappingId);

        if ($mapping === null) return null;

        $properties = Json::decode($mapping->properties ?? '[]') ?: [];
        $rows = [];

        foreach ($properties as $property => $propertyData) {
            $template = $propertyData['template'] ?? '';

            $rows[$property] = [
                'field' => Html::tag('strong', Html::encode($property)),
                'template' => Html::tag('code', Html::encode($template), ['style' => ['word-break' => 'break-all']]),
                'value' => StructuredDataField::formatPropertyValueHtml($element->properties[$property] ?? null),
            ];
        }

        $mappingUrl = $mapping->sectionHandle !== ''
            ? UrlHelper::cpUrl("core-seo-geo/settings/content-seo/{$mapping->sectionHandle}/mappings/{$mapping->id}")
            : null;

        return Html::tag('div', Cp::editableTableFieldHtml([
            'label' => Craft::t('core-seo-geo', 'Field Mapping'),
            'instructions' => $mappingUrl !== null
                ? Craft::t('core-seo-geo', 'How this object\'s properties are computed, for reference. [Edit this mapping]({url}).', [
                    'url' => $mappingUrl,
                ])
                : Craft::t('core-seo-geo', 'How this object\'s properties are computed, for reference.'),
            'static' => true,
            'name' => 'fieldMapping',
            'cols' => [
                'field' => ['heading' => Craft::t('core-seo-geo', 'Property'), 'type' => 'heading', 'width' => '20%'],
                'template' => ['heading' => Craft::t('core-seo-geo', 'Template'), 'type' => 'heading', 'width' => '40%'],
                'value' => ['heading' => Craft::t('core-seo-geo', 'Computed Value'), 'type' => 'heading', 'width' => '40%'],
            ],
            'rows' => $rows,
        ]), ['class' => ['core-seo-geo-properties-table']]);
    }

    /**
     * @inheritdoc
     */
    protected function selectorLabel(): string {
        return Craft::t('core-seo-geo', 'Field Mapping');
    }
}
