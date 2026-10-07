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
use craft\helpers\Cp;
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
    // Const Properties
    // =========================================================================

    /**
     * The default `type` for a brand new field, before anyone's picked a schema.org type.
     *
     * @since v1.0.0
     */
    public const string TYPE_WEBPAGE = 'WebPage';

    // Public Properties
    // =========================================================================

    /**
     * @var string The type of structured data this field represents. Configured by whoever
     * adds this field to a layout, in Settings → Fields.
     *
     * @since v1.0.0
     */
    public string $type = self::TYPE_WEBPAGE;

    /**
     * @var array<string, array{template: string}> Raw Twig template text, keyed by whichever
     * schema.org property it's for (one entry per row added via the settings page's "Add
     * Property" picker) and then by the editable table's `template` column id, configured by
     * whoever adds this field to a layout, in Settings → Fields. Each property's template is
     * rendered against the element at render time, so a dev can specify things like `{{ title }}`.
     *
     * @since v1.0.0
     */
    public array $properties = [];

    /**
     * @var mixed The ID of the entry picked in the field's settings to render the "Calculated
     * Value" preview column against, instead of the generic placeholder entry. Loosely typed,
     * rather than `?int`, because the settings page's element select posts it as a single-item
     * array - the same shape `SeoField::$_normalizeAssetId()` already tolerates for its image
     * select - and project config assigns posted settings onto this property directly, before
     * `beforeSave()` ever gets a chance to clean it up. Use `_previewElementId()` to read the
     * normalized value.
     *
     * @since v1.0.0
     */
    public mixed $previewElementId = null;

    // Public Methods
    // =========================================================================

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
     * @inheritdoc
     *
     * The properties table is built entirely from whichever properties are already in
     * `$this->properties` - rows are added by picking one from the "Add Property" field below
     * the table, not by enumerating a fixed per-type list (schema.org types range from a
     * handful of properties to 130+, too many to ever render as fixed rows). admin.js owns
     * adding/removing rows and keeping the "Add Property" options in sync with both the
     * selected type and whichever properties already have a row; this method only renders the
     * initial state.
     *
     * `fieldClass` marks each field's outer wrapper (not the `<select>`/table itself) so
     * admin.js can find these reliably via a plain class selector - the field settings UI can
     * namespace raw `id`s depending on where/how it's rendered, but it never rewrites classes.
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getSettingsHtml(): ?string {
        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();
        $typeProperties = $schemaOrg->getType($this->type)['properties'] ?? [];

        $html = Cp::selectizeFieldHtml([
            'label' => Craft::t('core-seo-geo', 'Schema.org Type'),
            'instructions' => Craft::t('core-seo-geo', 'The schema.org type this field represents.'),
            'id' => 'type',
            'name' => 'type',
            'fieldClass' => 'core-seo-geo-schema-type-field',
            'options' => $schemaOrg->getTypeOptions(),
            'value' => $this->type,
            'errors' => $this->getErrors('type'),
        ]);

        // Options start as the current type's properties minus whichever already have a row
        // below - admin.js takes over repopulating this (by type, and by what's already added)
        // from here on.
        $availableProperties = array_diff($typeProperties, array_keys($this->properties));

        $html .= Cp::selectizeFieldHtml([
            'label' => Craft::t('core-seo-geo', 'Add Property'),
            'instructions' => Craft::t('core-seo-geo', 'Pick a property to add it to the table below.'),
            'id' => 'addProperty',
            'fieldClass' => 'core-seo-geo-add-property-field',
            'options' => array_combine($availableProperties, $availableProperties),
            'value' => '',
        ]);

        $previewEntry = $this->_previewEntry();

        $html .= Cp::elementSelectFieldHtml([
            'label' => Craft::t('core-seo-geo', 'Preview Entry'),
            'instructions' => Craft::t('core-seo-geo', 'An entry to render the "Calculated Value" column below against, so templates referencing other fields (e.g. `entry.seoGeoField`) resolve too. Falls back to generic placeholder data, which can\'t, when nothing is picked.'),
            'id' => 'previewElementId',
            'name' => 'previewElementId',
            'elementType' => Entry::class,
            'elements' => $previewEntry !== null ? [$previewEntry] : [],
            'single' => true,
        ]);

        $rows = [];

        foreach ($this->properties as $property => $data) {
            $template = $data['template'] ?? null;

            $rows[$property] = [
                'field' => Html::encode($property),
                'template' => $template,
                'preview' => $this->_renderPreview($template),
                'remove' => $this->_removeButtonHtml($property),
            ];
        }

        // allowAdd/allowDelete/allowReorder all stay false - rows are added/removed entirely by
        // admin.js (via the "Add Property" field above and each row's own remove button), not
        // Craft's own editable-table JS, so there's no dependency on its internal row-management
        // API for a dynamically-sized, registry-driven table. `initJs: false` is required, not
        // just allowAdd/Delete/Reorder being false - Craft.EditableTable still watches the
        // table's rows even with all three disabled, and throws trying to process a row it
        // didn't create itself (confirmed: it threw reading an internal property its own
        // createRowObj() expects, the moment admin.js removed a hand-added row).
        return $html . Html::tag('div', Cp::editableTableFieldHtml([
            'label' => Craft::t('core-seo-geo', 'Properties'),
            'instructions' => Craft::t('core-seo-geo', 'A Twig template for each property, rendered against the element. For example, `{{ title }}` for the page title.'),
            'id' => 'properties',
            'name' => 'properties',
            'cols' => [
                'field' => [
                    'heading' => Craft::t('core-seo-geo', 'Property'),
                    'type' => 'heading',
                    'class' => 'core-seo-geo-field-heading',
                ],
                'template' => [
                    'heading' => Craft::t('core-seo-geo', 'Value'),
                    'type' => 'multiline',
                    'rows' => 1,
                    'width' => '50%',
                ],
                'preview' => [
                    'heading' => Craft::t('core-seo-geo', 'Calculated Value'),
                    'type' => 'heading',
                    'width' => '50%',
                    'info' => Craft::t('core-seo-geo', 'Rendered against the Preview Entry above, or generic placeholder data when none is picked - not a real page render, so Twig errors and typos (e.g. a wrong attribute name) show up here even though they wouldn\'t affect a real entry the same way.'),
                ],
                'remove' => [
                    'heading' => '',
                    'type' => 'heading',
                    'width' => '1%',
                ],
            ],
            'rows' => $rows,
            'allowAdd' => false,
            'allowDelete' => false,
            'allowReorder' => false,
            'initJs' => false,
            'errors' => $this->getErrors('properties'),
        ]), ['class' => ['core-seo-geo-properties-table']]);
    }

    /**
     * @inheritdoc
     *
     * Ignores `$value` entirely: a `StructuredData` instance is always rebuilt from `$element`,
     * the same way `SeoField` recomputes `metaTitle`. There's nothing left here a content
     * editor can type into, so there's nothing to read back.
     *
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
    public function getSearchKeywords(mixed $value, ElementInterface $element): string {
        if (!$value instanceof StructuredData) return '';

        return implode(' ', array_filter(array_map(
            static fn(mixed $propertyValue): string => \is_scalar($propertyValue) ? (string)$propertyValue : '',
            $value->data,
        )));
    }

    /**
     * @inheritdoc
     *
     * Drops any `properties` entries that don't belong to the currently selected `type`
     * before the field's settings are persisted, so switching types doesn't leave the
     * previous type's templates behind.
     *
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
     *
     * Persists this field's computed output for `$element` as a `StructuredData` element, so
     * `normalizeValue()` can read it back instead of re-rendering `properties` against `$element`
     * on every single page view. Runs on every save, drafts included: a draft gets its own
     * persisted row (keyed by `$element->id`, which differs from the canonical entry's id), and
     * it's cleaned up automatically ({@see StructuredDataElement::afterSave()}'s FK, `ON DELETE
     * CASCADE`) once the draft itself is deleted.
     *
     * Revisions are skipped entirely, unlike drafts: every save of a live entry creates a brand
     * new revision element with its own id, kept forever per the project's revision retention,
     * not cleaned up the way an abandoned draft is. Persisting for revisions too would leak one
     * more orphaned row per save, indefinitely, for data nothing ever reads back (a revision is
     * a historical snapshot, never rendered as the live page).
     *
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
     *
     * Renders a read-only preview of the computed JSON-LD. Everything is configured on the
     * field itself, in Settings → Fields, so there's nothing left for a content editor to
     * edit here.
     *
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
     *
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

    // Private Methods
    // =========================================================================

    /**
     * Renders each of the selected type's property templates against `$element`, skipping
     * any that are blank. A rendered template that's itself JSON (e.g. the output of
     * `craft.coreSeoGeo.createBreadcrumbs()`) is decoded back into a nested value, so the
     * final structured data doesn't end up with a JSON string embedded inside a JSON object.
     *
     * `renderObjectTemplate()` exposes `$element`'s own attributes as bare variables (`title`,
     * `url`, ...), not under an `entry` variable the way a normal site template would. Since
     * that's the natural thing to reach for anyway, `entry` is passed through too as an alias
     * for `$element`, so both `{{ title }}` and `{{ entry.title }}` resolve.
     *
     * A property whose template throws (e.g. printing a `DateTime` attribute without a `|date`
     * filter) is logged and skipped, rather than taking down the whole element edit screen:
     * this field's input is a read-only preview, and one bad template shouldn't block editing
     * everything else on the entry.
     *
     * @param ElementInterface|null $element
     * @return array<string, mixed>
     *
     * @since v1.0.0
     */
    private function _renderProperties(?ElementInterface $element): array {
        if ($element === null) return [];

        $data = [];
        $view = Craft::$app->getView();

        foreach ($this->properties as $property => $propertyData) {
            $template = $propertyData['template'] ?? null;

            if ($template === null || trim($template) === '') continue;

            try {
                $rendered = trim($view->renderObjectTemplate($template, $element, ['entry' => $element]));
            } catch (\Throwable $e) {
                Craft::warning("Couldn't render the \"{$property}\" structured data property on field \"{$this->handle}\": {$e->getMessage()}", __METHOD__);
                continue;
            }

            if ($rendered === '') continue;

            $data[$property] = Json::decodeIfJson($rendered);
        }

        return $data;
    }

    /**
     * The data `normalizeValue()` hands off to its `StructuredData` model: the persisted
     * `StructuredDataElement` row for `$element`'s own site (keyed by `$this->id` +
     * `$element->id` + `$element->siteId`), when one exists, falling back to a live
     * `_renderProperties()` render otherwise - a brand new, never-saved `$element` has no id to
     * key by yet, and an already-saved one might not have a row for this particular site yet if
     * nobody's saved it there before, or this field was only just added to its layout. Either
     * way, the fallback means display never goes blank just because the persisted copy hasn't
     * caught up.
     *
     * @param ElementInterface|null $element
     * @return array<string, mixed>
     *
     * @since v1.0.0
     */
    private function _resolveData(?ElementInterface $element): array {
        if ($element?->id === null) return $this->_renderProperties($element);

        $persisted = StructuredDataElement::find()
            ->fieldId($this->id)
            ->ownerId($element->id)
            ->siteId($element->siteId)
            ->status(null)
            ->one();

        return $persisted?->properties ?? $this->_renderProperties($element);
    }

    /**
     * Finds or creates the `StructuredDataElement` row for `$element`'s own site, overwrites it
     * with a fresh `_renderProperties()` render, and saves it. Called from
     * `afterElementSave()`, once per save, rather than from `normalizeValue()`, which runs on
     * every read. Scoped by `$element->siteId` so a multi-site entry gets one persisted row per
     * language it's actually been saved in, instead of every site clobbering a single row.
     *
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
     * A row's "remove" cell - visually matches Craft's own `.delete.icon` button, but doesn't
     * rely on Craft's editable-table JS to handle the click (this table's `allowDelete` is
     * always `false`): `core-seo-geo-remove-property` is admin.js's own marker class, bound via
     * its own click handler, so it works identically whether the row was rendered here or
     * added client-side by the "Add Property" picker.
     *
     * @param string $property
     * @return string
     *
     * @since v1.0.0
     */
    private function _removeButtonHtml(string $property): string {
        return Html::tag('button', '', [
            'type' => 'button',
            'class' => ['delete', 'icon', 'core-seo-geo-remove-property'],
            'title' => Craft::t('core-seo-geo', 'Remove'),
            'aria' => [
                'label' => Craft::t('core-seo-geo', 'Remove {property}', ['property' => $property]),
            ],
        ]);
    }

    /**
     * Renders `$template` against `_previewElement()`, for the settings table's "Calculated
     * Value" column - this exists purely to catch Twig errors and typos (e.g. a wrong attribute
     * name resolving to nothing) before a dev ever opens a real entry. Without a Preview Entry
     * picked, that's a throwaway placeholder with no custom field data, so anything beyond an
     * entry's own native attributes (`title`, `postDate`, ...) - a reference to another field
     * like `entry.seoGeoField`, for instance - only resolves once one's picked. A result that's
     * itself JSON (e.g. the output of `craft.coreSeoGeo.createBreadcrumbs()`) is pretty-printed,
     * the same way the field's own JSON-LD input is. A long result is collapsed via
     * {@see self::_collapsible()} so it doesn't dominate the table.
     *
     * @param string|null $template
     * @return string Encoded HTML, safe to pass as an editable table `heading`-type cell value.
     *
     * @since v1.0.0
     */
    private function _renderPreview(?string $template): string {
        if ($template === null || trim($template) === '') return '';

        $element = $this->_previewElement();

        try {
            $rendered = trim(Craft::$app->getView()->renderObjectTemplate($template, $element, ['entry' => $element]));
        } catch (\Throwable $e) {
            return Html::tag('span', Html::encode($e->getMessage()), ['class' => ['error']]);
        }

        if ($rendered === '') {
            return Html::tag('span', Craft::t('core-seo-geo', '(empty)'), ['class' => ['light']]);
        }

        $decoded = Json::decodeIfJson($rendered);

        if (\is_array($decoded)) {
            $rendered = Json::encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return self::_collapsible($rendered);
    }

    /**
     * The element the "Calculated Value" preview column renders property templates against:
     * the entry picked via `previewElementId`, when one was picked and still resolves, falling
     * back to a throwaway placeholder entry otherwise - so templates that only reference an
     * entry's own native attributes (`title`, `postDate`, ...) still preview something even
     * before a dev has picked one.
     *
     * @return ElementInterface
     *
     * @since v1.0.0
     */
    private function _previewElement(): ElementInterface {
        return $this->_previewEntry() ?? $this->_placeholderElement();
    }

    /**
     * Resolves `previewElementId` to the actual `Entry` it points at, if any. Used both to
     * render the preview column and to repopulate the element select on the settings page.
     *
     * @return Entry|null
     *
     * @since v1.0.0
     */
    private function _previewEntry(): ?Entry {
        $id = $this->_previewElementId();

        if ($id === null) return null;

        /** @var Entry|null $entry */
        $entry = Craft::$app->getElements()->getElementById($id, Entry::class);

        return $entry;
    }

    /**
     * Normalizes `previewElementId` down to a single ID. The settings page's element select
     * posts it as a single-item array - the same shape `SeoField::_normalizeAssetId()` already
     * tolerates for its own single-asset select - and project config may still hand it back as
     * one on an unsaved/invalid settings re-render, before `beforeSave()` has cleaned it up.
     *
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
     * Builds a throwaway sample entry with placeholder values, used as the "Calculated Value"
     * preview's element when no `previewElementId` is picked (or it no longer resolves).
     *
     * @return ElementInterface
     *
     * @since v1.0.0
     */
    private function _placeholderElement(): ElementInterface {
        $entry = new Entry();
        $entry->title = Craft::t('core-seo-geo', 'Example Entry Title');
        $entry->postDate = new \DateTime();
        $entry->dateCreated = new \DateTime();
        $entry->dateUpdated = new \DateTime();

        return $entry;
    }

    /**
     * Renders an already-computed property value (as stored on a `StructuredData` element,
     * decoded, not re-rendered against any template) the same way `_renderPreview()` renders a
     * freshly-rendered one: JSON-encoded if it's not a plain scalar, then collapsed.
     *
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
