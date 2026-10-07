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

use digitalastronaut\craftcoreseogeo\elements\StructuredData;
use digitalastronaut\craftcoreseogeo\fields\StructuredDataField;

/**
 * Class FieldMappingUiElement
 *
 * Read-only reference table showing each property the owning `StructuredDataField` computes,
 * alongside its configured Twig template and this row's current computed value - purely for
 * context, nothing here is editable. Renders nothing for a standalone object (`$fieldId` null),
 * since there's no backing field to map from.
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
        if (!$element instanceof StructuredData || $element->fieldId === null) return null;

        $field = Craft::$app->getFields()->getFieldById($element->fieldId);

        if (!$field instanceof StructuredDataField) return null;

        $rows = [];

        foreach ($field->properties as $property => $propertyData) {
            $template = $propertyData['template'] ?? '';

            $rows[$property] = [
                'field' => Html::tag('strong', Html::encode($property)),
                'template' => Html::tag('code', Html::encode($template), ['style' => ['word-break' => 'break-all']]),
                'value' => StructuredDataField::formatPropertyValueHtml($element->properties[$property] ?? null),
            ];
        }

        return Html::tag('div', Cp::editableTableFieldHtml([
            'label' => Craft::t('core-seo-geo', 'Field Mapping'),
            'instructions' => Craft::t('core-seo-geo', 'How "{field}" computes this object\'s properties, for reference. Configured in Settings → Fields, not here.', [
                'field' => $field->name,
            ]),
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
