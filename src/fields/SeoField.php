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
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\models\SeoData;

use yii\db\Schema;
use yii\validators\UrlValidator;

/**
 * Class SeoField
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class SeoField extends Field {
    /**
     * @var array<int, array{label: string, template: string, editable: bool}> The blocks used
     * to build the meta title, in render order. Configured by whoever adds this field to a
     * layout, in Settings → Fields.
     *
     * @since v1.0.0
     */
    public array $titleFormatRows = [];

    /**
     * @var array<int, array{label: string, template: string, editable: bool}> The blocks used
     * to build the meta description. See {@see SeoField::$titleFormatRows}.
     *
     * @since v1.0.0
     */
    public array $descriptionFormatRows = [];

    /**
     * @var bool Whether the Canonical URL field shows up in the CP input. Most sites never
     * override their canonical URL, so this is here to let whoever's configuring the field
     * hide it and declutter the UI.
     *
     * @since v1.0.0
     */
    public bool $showCanonicalField = true;

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function displayName(): string {
        return Craft::t('core-seo-geo', 'SEO/GEO');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function icon(): string {
        return '@digitalastronaut/craftcoreseogeo/web/assets/icons/seo-geo-field-icon.svg';
    }

    /**
     * @inheritdoc
     * 
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function phpType(): string {
        return SeoData::class;
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
     * @return array<int, array{label: string, value: string, data: array{hint: string}}>
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getRobotsOptions(): array {
        return [
            ['optgroup' => Craft::t('core-seo-geo', 'Robot meta tags')],
            [
                'label' => Craft::t('core-seo-geo', 'No Index'),
                'value' => SeoData::ROBOTS_NOINDEX,
                'data' => ['hint' => Craft::t('core-seo-geo', "Hide this page from search results and its cached link.")],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Follow'),
                'value' => SeoData::ROBOTS_NOFOLLOW,
                'data' => ['hint' => Craft::t('core-seo-geo', "Don't follow the links on this page.")],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Archive'),
                'value' => SeoData::ROBOTS_NOARCHIVE,
                'data' => ['hint' => Craft::t('core-seo-geo', 'Hide the cached link in search results.')],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Snippet'),
                'value' => SeoData::ROBOTS_NOSNIPPET,
                'data' => ['hint' => Craft::t('core-seo-geo', 'Hide the text or video snippet. A thumbnail may still show.')],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Translate'),
                'value' => SeoData::ROBOTS_NOTRANSLATE,
                'data' => ['hint' => Craft::t('core-seo-geo', "Don't offer translation of this page.")],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Image Index'),
                'value' => SeoData::ROBOTS_NOIMAGEINDEX,
                'data' => ['hint' => Craft::t('core-seo-geo', "Don't index images on this page.")],
            ],
            ['optgroup' => Craft::t('core-seo-geo', 'AI meta tags')],
            [
                'label' => Craft::t('core-seo-geo', 'No AI'),
                'value' => SeoData::ROBOTS_NOAI,
                'data' => ['hint' => Craft::t('core-seo-geo', "Ask generative AI crawlers not to use this page's text to train or answer prompts.")],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Image AI'),
                'value' => SeoData::ROBOTS_NOIMAGEAI,
                'data' => ['hint' => Craft::t('core-seo-geo', "Ask generative AI crawlers not to use this page's images to train or answer prompts.")],
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
        $cols = self::_templateRowColumns();

        return
            Cp::editableTableFieldHtml([
                'label' => Craft::t('core-seo-geo', 'Meta Title Config'),
                'instructions' => Craft::t('core-seo-geo', "The blocks used to build the meta title, rendered in order and joined together. A block's template accepts {attr} shorthand and full {{ twig }} against the entry being rendered."),
                'id' => 'titleFormatRows',
                'name' => 'titleFormatRows',
                'addRowLabel' => Craft::t('core-seo-geo', 'Add a block'),
                'allowAdd' => true,
                'allowReorder' => true,
                'allowDelete' => true,
                'minRows' => 1,
                'cols' => $cols,
                'rows' => $this->titleFormatRows,
                'errors' => $this->getErrors('titleFormatRows'),
            ]) .
            Cp::editableTableFieldHtml([
                'label' => Craft::t('core-seo-geo', 'Meta Description Config'),
                'instructions' => Craft::t('core-seo-geo', 'The blocks used to build the meta description. Same rules as the title blocks above.'),
                'id' => 'descriptionFormatRows',
                'name' => 'descriptionFormatRows',
                'addRowLabel' => Craft::t('core-seo-geo', 'Add a block'),
                'allowAdd' => true,
                'allowReorder' => true,
                'allowDelete' => true,
                'minRows' => 1,
                'cols' => $cols,
                'rows' => $this->descriptionFormatRows,
                'errors' => $this->getErrors('descriptionFormatRows'),
            ]) .
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('core-seo-geo', 'Show Canonical Field'),
                'instructions' => Craft::t('core-seo-geo', "Show the Canonical URL field in the CP input. Most sites never need to override it, so turning this off can declutter the UI."),
                'id' => 'showCanonicalField',
                'name' => 'showCanonicalField',
                'on' => $this->showCanonicalField,
            ]);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed {
        if ($value instanceof SeoData) return $value;
        if (\is_string($value)) $value = Json::decodeIfJson($value);
        if (!\is_array($value)) $value = [];

        [$titleRows, $metaTitle] = $this->_resolveFormat($this->titleFormatRows, $value['titleRows'] ?? null, $element);
        [$descriptionRows, $metaDescription] = $this->_resolveFormat($this->descriptionFormatRows, $value['descriptionRows'] ?? null, $element);

        return new SeoData([
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'titleRows' => $titleRows,
            'descriptionRows' => $descriptionRows,
            'canonicalUrl' => $this->_normalizeString($value['canonicalUrl'] ?? null),
            'robots' => $this->_normalizeRobots($value['robots'] ?? []),
            // `imageId` is the pre-rename key: existing stored/posted content may still use it.
            'image' => $this->_normalizeAssetId($value['image'] ?? $value['imageId'] ?? null),
        ]);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function isValueEmpty(mixed $value, ElementInterface $element): bool {
        if ($value instanceof SeoData) return $value->isEmpty();

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
            ['validateSeoData'],
        ];
    }

    /**
     * Validates the meta title length and canonical URL format.
     *
     * @param ElementInterface $element the element being validated
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function validateSeoData(ElementInterface $element): void {
        $value = $element->getFieldValue($this->handle);

        if (!$value instanceof SeoData) return;

        /** @var Element $element */
        if ($this->titleFormatRows !== [] && $value->metaTitle === null) {
            $element->addError($this->handle, Craft::t('core-seo-geo', '{attribute} cannot be empty.', [
                'attribute' => Craft::t('core-seo-geo', 'Meta Title'),
            ]));
        }

        if ($this->descriptionFormatRows !== [] && $value->metaDescription === null) {
            $element->addError($this->handle, Craft::t('core-seo-geo', '{attribute} cannot be empty.', [
                'attribute' => Craft::t('core-seo-geo', 'Meta Description'),
            ]));
        }

        if ($value->canonicalUrl === null) return;

        $validator = new UrlValidator(['defaultScheme' => 'https']);

        if (!$validator->validate($value->canonicalUrl)) {
            $element->addError($this->handle, Craft::t('core-seo-geo', '{attribute} is not a valid URL.', [
                'attribute' => Craft::t('core-seo-geo', 'Canonical URL'),
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
        if (!$value instanceof SeoData) return '';

        return implode(' ', array_filter([
            $value->metaTitle,
            $value->metaDescription,
        ]));
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string {
        $view = Craft::$app->getView();
        $id = $view->namespaceInputId(Html::id($this->handle));

        $image = $value instanceof SeoData ? $value->getImage()->one() : null;

        return $view->renderTemplate('core-seo-geo/fields/seo/_input', [
            'field' => $this,
            'id' => $id,
            'name' => $this->handle,
            'value' => $value,
            'element' => $element,
            'image' => $image,
        ]);
    }

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

    /**
     * @param mixed $value
     * @return string[]
     *
     * @since       v1.0.0
     */
    private function _normalizeRobots(mixed $value): array {
        if (!\is_array($value)) return [];

        return array_values(array_intersect(SeoData::ROBOTS_DIRECTIVES, $value));
    }

    /**
     * Normalizes a posted element-select value into a single asset ID. The input posts a plain
     * scalar since it's configured as a single selection, but an array is tolerated too in case
     * that ever changes.
     *
     * @param mixed $value
     * @return int|null
     *
     * @since v1.0.0
     */
    private function _normalizeAssetId(mixed $value): ?int {
        if (\is_array($value)) $value = reset($value) ?: null;

        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * Resolves a title/description format against its settings rows: non-editable rows always
     * use the settings row's own template (a posted override is never trusted for those), while
     * editable rows use the posted override when present and non-blank, falling back to the
     * template's own computed value when the override was saved empty. The resolved rows are
     * then joined with a single space between each non-blank block.
     *
     * @param array<int, array{label: string, template: string, editable: bool}> $formatRows the field's own settings rows
     * @param mixed $postedRows the posted/stored per-row override text, if any
     * @param ElementInterface|null $element the element being saved, used to resolve `{attr}`/`{{ twig }}` in each row
     * @return array{0: string[], 1: ?string} the resolved per-row text, and the joined result
     *
     * @since v1.0.0
     */
    private function _resolveFormat(array $formatRows, mixed $postedRows, ?ElementInterface $element): array {
        $postedRows = \is_array($postedRows) ? $postedRows : [];
        $rows = [];

        foreach ($formatRows as $i => $row) {
            $template = (string)($row['template'] ?? '');
            $editable = $this->_isTruthy($row['editable'] ?? false);
            $posted = $postedRows[$i] ?? null;
            $postedIsBlank = !\is_string($posted) || trim($posted) === '';

            $effective = ($editable && !$postedIsBlank) ? $posted : $template;
            $rows[$i] = $element !== null ? Craft::$app->getView()->renderObjectTemplate($effective, $element) : $effective;
        }

        if ($rows === []) {
            return [[], null];
        }

        $nonBlankRows = array_filter($rows, static fn(string $row): bool => trim($row) !== '');
        $joined = trim(implode(' ', $nonBlankRows));

        return [$rows, $joined === '' ? null : $joined];
    }

    /**
     * Coerces a posted/stored lightswitch value to a boolean. PHP's `(bool)'0'` is `true`,
     * which would be wrong for the "0" a lightswitch posts when off.
     *
     * @param mixed $value
     * @return bool
     *
     * @since v1.0.0
     */
    private function _isTruthy(mixed $value): bool {
        return $value === true || $value === '1' || $value === 1;
    }

    /**
     * Returns the shared column config for the title/description block-settings tables.
     *
     * @return array<string, array{heading: string, type: string}>
     *
     * @since v1.0.0
     */
    private static function _templateRowColumns(): array {
        return [
            'label' => [
                'heading' => Craft::t('core-seo-geo', 'Label'),
                'type' => 'singleline',
            ],
            'template' => [
                'heading' => Craft::t('core-seo-geo', 'Template'),
                'type' => 'template',
            ],
            'editable' => [
                'heading' => Craft::t('core-seo-geo', 'Editable'),
                'type' => 'lightswitch',
            ],
        ];
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function defineRules(): array {
        $rules = parent::defineRules();

        $rules[] = [['titleFormatRows', 'descriptionFormatRows'], 'validateFormatRowLabels'];

        return $rules;
    }


    /**
     * Validates that every row in the given format-rows table has a label, so blocks are always
     * identifiable in the CP and in `{attr}` shorthand lookups.
     *
     * @param string $attribute `titleFormatRows` or `descriptionFormatRows`
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function validateFormatRowLabels(string $attribute): void {
        /** @var array<int, array{label?: string, template?: string, editable?: bool}> $rows */
        $rows = $this->$attribute;

        foreach ($rows as $row) {
            if (trim((string)($row['label'] ?? '')) === '') {
                $this->addError($attribute, Craft::t('core-seo-geo', 'Every row must have a label.'));
                return;
            }
        }
    }
}
