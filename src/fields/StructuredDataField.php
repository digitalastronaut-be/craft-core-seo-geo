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

/**
 * Class StructuredDataField
 *
 * Read-only display of the structured data already computed for the owning entry - every
 * `StructuredDataField` instance shows the same thing, since the type/properties mapping it used
 * to carry in its own settings now lives on the entry's *section* instead (see
 * `StructuredDataMappingService`, `StructuredDataMapping`), synced independently of any field
 * instance by `StructuredDataMappingService::syncEntry()` on every entry save. No settings of its
 * own as a result - add this field to a section's entry types purely to show that section's
 * computed JSON-LD somewhere in the CP; where it's added doesn't change what it shows.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredDataField extends Field {
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
     *
     * No content column: this field stores nothing of its own - its value is entirely looked up
     * (by owner element ID/site) from `StructuredData` elements a section mapping already
     * computed, same as how a relation count or a computed/virtual field needs no column either.
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public static function dbType(): array|string|null {
        return null;
    }

    /**
     * The Twig/view namespace the type/properties mapping editor's inputs render under - keyed
     * by this field's own class rather than any particular field instance's ID, since
     * `content-seo-mapping.twig` reuses this same mapping editor UI to edit a
     * `coreseogeo_structureddatamappings` row, which isn't backed by a field instance at all.
     * `StructuredDataFieldController`'s SSE-patched fragments assume this exact namespace, so
     * every page that includes the editor needs to render it under this same one for their ids
     * to keep matching.
     *
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public static function settingsNamespace(): string {
        return 'types[' . self::settingsNamespaceKey() . ']';
    }

    /**
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public static function settingsNamespaceKey(): string {
        return Html::id(self::class);
    }

    /**
     * @inheritdoc
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed {
        if ($value instanceof StructuredData) return $value;

        return new StructuredData(['objects' => $this->_resolveObjects($element)]);
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

        $keywords = [];

        foreach ($value->objects as $object) {
            foreach ($object as $propertyValue) {
                if (\is_scalar($propertyValue)) $keywords[] = (string)$propertyValue;
            }
        }

        return implode(' ', $keywords);
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
            ? Json::encode($value->objects, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';

        return $view->renderTemplate('core-seo-geo/fields/structured-data/_input', [
            'id' => $id,
            'name' => $this->handle,
            'json' => $json,
            'rows' => max(4, min(20, substr_count($json, "\n") + 1)),
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Looks up every `StructuredData` row already synced for this owner element/site - one per
     * mapping configured on the element's section ({@see StructuredDataMappingService::
     * syncEntry()}) - and returns each one's full JSON-LD. Nothing is rendered here: by the time
     * a field is displaying a value, syncing has already happened on save, the same way this
     * field never computed anything live even back when the mapping lived on its own settings.
     *
     * @param ElementInterface|null $element
     * @return array<int, array<string, mixed>>
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    private function _resolveObjects(?ElementInterface $element): array {
        if ($element === null || $element->id === null) return [];

        $rows = StructuredDataElement::find()
            ->ownerId($element->id)
            ->siteId($element->siteId)
            ->status(null)
            ->all();

        return array_map(
            static fn(StructuredDataElement $row): array => $row->toJsonLd(),
            $rows,
        );
    }

    /**
     * Renders each of `$properties`' templates against `$previewEntry` and formats the result
     * (or thrown error) as HTML for the mapping editor's "Calculated Value" column. Called from
     * `ContentSeoController`'s mapping editor pages, which render this same mapping editor UI.
     *
     * @param array<string, array{template?: string|null}> $properties keyed by schema.org property name
     * @param Entry|null $previewEntry
     * @return array<string, string>|null null when there's no preview entry to calculate against
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public static function calculatedValueHtml(array $properties, ?Entry $previewEntry): ?array {
        if ($previewEntry === null) return null;

        $errors = [];
        $rendered = CoreSeoGeo::getInstance()->getStructuredData()->renderProperties($previewEntry, $properties, $errors);
        $html = [];

        foreach (array_keys($properties) as $name) {
            $html[$name] = isset($errors[$name])
                ? self::formatPropertyErrorHtml($errors[$name])
                : self::formatPropertyValueHtml($rendered[$name] ?? null);
        }

        return $html;
    }

    /**
     * Renders the full JSON-LD object - `@context`, `@type`, and every configured property -
     * against `$previewEntry`, pretty-printed for the mapping editor's "Preview" tab. The same
     * shape `StructuredData::toJsonLd()` produces for a saved element, just computed ad hoc
     * against whichever entry is currently chosen as the preview.
     *
     * @param string $type a schema.org type name, e.g. `Product`
     * @param array<string, array{template?: string|null}> $properties keyed by schema.org property name
     * @param Entry|null $previewEntry
     * @return string|null null when there's no preview entry to render against
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public static function jsonLdPreview(string $type, array $properties, ?Entry $previewEntry): ?string {
        if ($previewEntry === null) return null;

        $structuredData = CoreSeoGeo::getInstance()->getStructuredData();
        $data = $structuredData->renderProperties($previewEntry, $properties);

        return Json::encode($structuredData->toJsonLd($type, $data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Normalizes a posted `previewElementId` value - an `elementSelectField`'s single-selection
     * mode still posts an array of one ID (or an empty string via its hidden fallback input when
     * nothing's selected) - down to the plain entry ID it represents.
     *
     * @param mixed $value
     * @return int|null
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public static function normalizePreviewElementId(mixed $value): ?int {
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
            return Html::tag('span', Craft::t('core-seo-geo', 'empty'), ['class' => ['light', 'code']]);
        }

        $display = \is_scalar($value)
            ? (string)$value
            : Json::encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return self::_collapsible($display);
    }

    /**
     * @param string $message
     * @return string
     *
     * @since v1.0.0
     */
    public static function formatPropertyErrorHtml(string $message): string {
        return Html::tag('span', self::_collapsible($message), ['class' => ['error']]);
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
