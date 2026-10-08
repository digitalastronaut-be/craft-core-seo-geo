<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Entry;
use craft\helpers\Html;
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\elements\StructuredData as StructuredDataElement;
use digitalastronaut\craftcoreseogeo\models\StructuredData;

use yii\db\Schema;

/**
 * Class StructuredDataField
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredDataField extends Field {
    /**
     * @var string 
     * @since v1.0.0
     */
    public string $type;
    public array $properties = [];
    public mixed $previewElementId = null;

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function displayName(): string {
        return Craft::t('core-seo-geo', 'Structured Data');
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function icon(): string {
        return '@digitalastronaut/craftcoreseogeo/web/assets/icons/structured-data-field.svg';
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function phpType(): string {
        return StructuredData::class;
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function dbType(): string {
        return Schema::TYPE_JSON;
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getSettingsHtml(): ?string {
        return StructuredDataFieldSettings::html($this);
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed {
        if ($value instanceof StructuredData) return $value;

        return new StructuredData([
            'type' => $this->type,
            'data' => $this->_resolveData($element),
        ]);
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function isValueEmpty(mixed $value, ElementInterface $element): bool {
        if ($value instanceof StructuredData) return $value->isEmpty();

        return parent::isValueEmpty($value, $element);
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getSearchKeywords(mixed $value, ElementInterface $element): string {
        if (!$value instanceof StructuredData) return '';

        return implode(' ', array_filter(array_map(
            static fn(mixed $propertyValue): string => \is_scalar($propertyValue) ? (string)$propertyValue : '',
            $value->data,
        )));
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function beforeSave(bool $isNew): bool {
        $allowedProperties = CoreSeoGeo::getInstance()->getSchemaOrg()->getType($this->type)['properties'] ?? [];

        $this->properties = array_intersect_key($this->properties, array_flip($allowedProperties));
        $this->previewElementId = $this->_previewElementId();

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function afterElementSave(ElementInterface $element, bool $isNew): void {
        if (!$element->getIsRevision()) {
            $this->_persistComputedData($element);
        }

        parent::afterElementSave($element, $isNew);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string {
        $view = Craft::$app->getView();
        $id = $view->namespaceInputId(Html::id($this->handle));

        $json = $value instanceof StructuredData && !$value->isEmpty()
            ? Json::encode($value->toJsonLd(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';

        return $view->renderTemplate('core-seo-geo/fields/structured-data/_input', [
            'id' => $id,
            'name' => $this->handle,
            'json' => $json,
            'rows' => max(4, min(20, substr_count($json, "\n") + 1)),
        ]);
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function defineRules(): array {
        $rules = parent::defineRules();

        $rules[] = [['type'], function(string $attribute) {
            if (CoreSeoGeo::getInstance()->getSchemaOrg()->getType($this->$attribute) === null) {
                $this->addError($attribute, Craft::t('core-seo-geo', '{value} isn\'t a known schema.org type.', [
                    'value' => $this->$attribute,
                ]));
            }
        }];

        return $rules;
    }

    /**
     * @param ElementInterface|null $element
     * @return array<string, mixed>
     *
     * @since v1.0.0
     */
    private function _resolveData(?ElementInterface $element): array {
        if ($element === null) return [];

        if ($element->id === null) return $this->_renderProperties($element);

        $persisted = StructuredDataElement::find()
            ->fieldId($this->id)
            ->ownerId($element->id)
            ->siteId($element->siteId)
            ->status(null)
            ->one();

        return $persisted?->properties ?? $this->_renderProperties($element);
    }

    /**
     * @param ElementInterface $element
     * @return array<string, mixed>
     *
     * @since v1.0.0
     */
    private function _renderProperties(ElementInterface $element): array {
        return CoreSeoGeo::getInstance()->getStructuredData()->renderProperties($element, $this->properties);
    }

    /**
     * @param ElementInterface $element
     * @return void
     *
     * @since v1.0.0
     */
    private function _persistComputedData(ElementInterface $element): void {
        $structuredData = StructuredDataElement::find()
            ->fieldId($this->id)
            ->ownerId($element->id)
            ->siteId($element->siteId)
            ->status(null)
            ->one() ?? new StructuredDataElement([
                'fieldId' => $this->id,
                'ownerId' => $element->id,
                'siteId' => $element->siteId,
            ]);

        $structuredData->title = Craft::t('core-seo-geo', '{element} ({type})', [
            'element' => (string)$element,
            'type' => $this->type,
        ]);
        $structuredData->type = $this->type;
        $structuredData->properties = $this->_renderProperties($element);

        if (!Craft::$app->getElements()->saveElement($structuredData)) {
            Craft::warning("Couldn't persist structured data for field \"{$this->handle}\" on element #{$element->id}: " . Json::encode($structuredData->getErrors()), __METHOD__);
        }
    }

    /**
     * @return Entry|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getPreviewEntry(): ?Entry {
        $id = $this->_previewElementId();

        if ($id === null) return null;

        /** @var Entry|null $entry */
        $entry = Craft::$app->getElements()->getElementById($id, Entry::class);

        return $entry;
    }

    /**
     * @return int|null
     *
     * @since v1.0.0
     */
    private function _previewElementId(): ?int {
        $value = $this->previewElementId;

        if (\is_array($value)) $value = reset($value) ?: null;

        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * @param mixed $value
     * @return string
     *
     * @since v1.0.0
     */
    public static function formatPropertyValueHtml(mixed $value): string {
        if ($value === null || $value === '') {
            return Html::tag('span', Craft::t('core-seo-geo', '(empty)'), ['class' => ['light']]);
        }

        $display = \is_scalar($value)
            ? (string)$value
            : Json::encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return self::_collapsible($display);
    }

    /**
     * @param string $value
     * @return string
     *
     * @since v1.0.0
     */
    private static function _collapsible(string $value): string {
        $collapseAt = 50;
        $encoded = Html::encode($value);

        if (mb_strlen($value) <= $collapseAt) {
            return Html::tag('code', $encoded, ['style' => ['word-break' => 'break-all']]);
        }

        $summary = Html::encode(mb_strimwidth($value, 0, $collapseAt, '…'));

        return Html::tag('details', Html::tag('summary', Html::tag('code', $summary), [
            'style' => [
                'display' => 'inline-flex',
                'cursor' => 'pointer',
            ],
        ]) . Html::tag('code', $encoded, [
            'style' => [
                'display' => 'block',
                'margin-top' => 'var(--xs)',
                'white-space' => 'pre-wrap',
                'word-break' => 'break-all',
            ],
        ]));
    }
}
