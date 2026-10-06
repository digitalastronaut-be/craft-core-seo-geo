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
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;

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
     * @since v1.0.0
     */
    public const string TYPE_WEBPAGE = 'WebPage';

    /**
     * @var string[] 
     *
     * @since v1.0.0
     */
    public const array TYPES = [
        self::TYPE_WEBPAGE,
    ];

    /**
     * @var string 
     *
     * @since v1.0.0
     */
    public string $type = self::TYPE_WEBPAGE;


    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function displayName(): string {
        return Craft::t('core-seo-geo', 'Structured Data');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function icon(): string {
        return '@digitalastronaut/craftcoreseogeo/web/assets/icons/structured-data-field.svg';
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function phpType(): string {
        return StructuredData::class;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function dbType(): string {
        return Schema::TYPE_JSON;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getTypeOptions(): array {
        return [
            [
                'label' => Craft::t('core-seo-geo', 'Web Page'),
                'value' => self::TYPE_WEBPAGE,
            ],
        ];
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getSettingsHtml(): ?string {
        return Cp::selectFieldHtml([
            'label' => Craft::t('core-seo-geo', 'Type'),
            'instructions' => Craft::t('core-seo-geo', 'The type of structured data this field represents.'),
            'id' => 'type',
            'name' => 'type',
            'options' => $this->getTypeOptions(),
            'value' => $this->type,
            'errors' => $this->getErrors('type'),
        ]);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed {
        if ($value instanceof StructuredData) return $value;
        if (\is_string($value)) $value = Json::decodeIfJson($value);
        if (!\is_array($value)) $value = [];

        $raw = $this->_normalizeString($value['raw'] ?? null);
        $data = \is_array($value['data'] ?? null) ? $value['data'] : [];

        if ($raw !== null) {
            $decoded = Json::decodeIfJson($raw);
            if (\is_array($decoded)) $data = $decoded;
        }

        return new StructuredData([
            'data' => $data,
            'raw' => $raw,
        ]);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function isValueEmpty(mixed $value, ElementInterface $element): bool {
        if ($value instanceof StructuredData) return $value->isEmpty();

        return parent::isValueEmpty($value, $element);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getElementValidationRules(): array {
        return [
            ['validateStructuredData'],
        ];
    }

    /**
     * Validates that the raw JSON-LD text, when present, parses as a JSON object.
     *
     * @param ElementInterface $element the element being validated
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function validateStructuredData(ElementInterface $element): void {
        $value = $element->getFieldValue($this->handle);

        if (!$value instanceof StructuredData) return;
        if ($value->raw === null) return;

        $decoded = Json::decodeIfJson($value->raw);

        if (!\is_array($decoded)) {
            $element->addError($this->handle, Craft::t('core-seo-geo', '{attribute} is not valid JSON.', [
                'attribute' => Craft::t('core-seo-geo', 'Structured Data'),
            ]));
        }
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getSearchKeywords(mixed $value, ElementInterface $element): string {
        if (!$value instanceof StructuredData) return '';

        return (string)$value->raw;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string {
        $view = Craft::$app->getView();
        $id = $view->namespaceInputId(Html::id($this->handle));

        return $view->renderTemplate('core-seo-geo/fields/structured-data/_input', [
            'field' => $this,
            'id' => $id,
            'name' => $this->handle,
            'value' => $value,
            'element' => $element,
        ]);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function defineRules(): array {
        $rules = parent::defineRules();

        $rules[] = [['type'], 'in', 'range' => self::TYPES];

        return $rules;
    }

    // Private Methods
    // =========================================================================

    /**
     * @param mixed $value
     * @return string|null
     *
     * @since       v1.0.0
     */
    private function _normalizeString(mixed $value): ?string {
        if (!\is_string($value)) return null;

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
