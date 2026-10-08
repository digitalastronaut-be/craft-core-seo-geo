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

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class StructuredDataFieldSettings
 *
 * Renders `StructuredDataField`'s settings-page HTML via a real Twig template
 * (`templates/fields/structured-data/_settings.twig`) rather than building it up through PHP
 * `Cp::xxxFieldHtml()` calls - this is where the Datastar-driven settings UI is being built, and
 * hand-writing `data-*` attributes directly in Twig markup is far more natural than threading
 * them through PHP config arrays. Not a `craft\fieldlayoutelements\BaseUiElement` (see
 * `fieldlayoutelements\FieldMappingUiElement` for one of those) - that system drives an
 * *element's* field layout, and a field's own settings page never goes through a field layout
 * at all, so there's no real Craft concept this should plug into.
 *
 * A searchable type picker, and (once a type is picked) a searchable list of that type's
 * properties below it - both lists are re-rendered server-side on every keystroke (debounced) via
 * `controllers\StructuredDataFieldController` and patched into the DOM through Datastar, rather
 * than filtered client-side, so results can be ranked by relevance instead of just DOM order.
 * Clicking a property toggles it into the mapping table below, where its Twig template gets
 * typed in - unlike the two search lists above, the mapping table holds a Monaco editor per row
 * (`nystudio107/craft-code-editor`), a stateful JS widget the server's HTML has no way to
 * represent, so only the one row that actually changed is ever rendered/patched
 * (`actionSyncProperty()` on the same controller, append on add / remove on delete), never the
 * whole table - and the client disposes a row's Monaco instance itself, synchronously in the
 * same click handler that removes it (see the `coreSeoGeoDisposeEditor`/
 * `coreSeoGeoDisposeAllPropertyEditors` helpers registered in `_settings.twig`), rather than the
 * server inferring what to clean up. Switching or clearing the type throws every row away at once
 * via `actionClearRows()`. Only the field's own save ever touches its real `properties` value.
 * The Selectize-based pickers and
 * the editable-table mapping UI that used to live here are both gone - see the conversation
 * history for why (Selectize's selection change
 * doesn't fire a real native DOM event, which is exactly what Datastar's own `data-bind`/
 * `data-on` rely on, and the editable table fought Craft's own JS badly enough to cause real
 * data loss).
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredDataFieldSettings {
    /**
     * @param StructuredDataField $field
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function html(StructuredDataField $field): string {
        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();
        $propertyNames = $schemaOrg->getType($field->type)['properties'] ?? [];

        return Craft::$app->getView()->renderTemplate('core-seo-geo/fields/structured-data/_settings.twig', [
            'field' => $field,
            'fieldId' => $field->id,
            'typeOptions' => $schemaOrg->getTypeOptions(),
            'typeLabel' => $field->type !== '' ? ($schemaOrg->getType($field->type)['label'] ?? $field->type) : '',
            'propertyOptions' => array_combine($propertyNames, $propertyNames),
            'propertyDescriptions' => $schemaOrg->getPropertyDescriptions($propertyNames),
            'propertyTooltips' => $schemaOrg->getPropertyTooltips(array_keys($field->properties)),
        ]);
    }
}
