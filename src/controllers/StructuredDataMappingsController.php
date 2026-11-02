<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\controllers;

use Craft;
use craft\enums\Color;
use craft\helpers\AdminTable;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\EntryType;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\fields\StructuredDataField;
use digitalastronaut\craftcoreseogeo\records\StructuredDataMapping;

use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class StructuredDataMappingsController
 *
 * The structured data mapping management pages, reached from a "Manage mappings" button on the
 * Structured Data element index (`structured-data/_index.twig`) rather than a nav item of their
 * own: an index listing every entry type, a per-entry-type page for its own mappings table, and
 * the "Create mapping"/"Edit mapping" page for one mapping on that entry type. Split out of
 * `ContentSeoController` (which used to nest this under a *section's* own settings page) once
 * mappings were rescoped from `sectionHandle` to `entryTypeHandle` - an entry type works
 * regardless of whether it's used directly in a section or nested inside a Matrix field, which a
 * section never did. Not site-scoped at all (unlike Content SEO): a mapping's property templates
 * resolve per element, so there's no per-site variant of the mapping configuration itself.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.40.0
 */
class StructuredDataMappingsController extends Controller {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function beforeAction($action): bool {
        $this->requireAdmin(false);

        return parent::beforeAction($action);
    }

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function actionIndex(): Response {
        return $this->renderTemplate('core-seo-geo/structured-data/mappings/_index.twig');
    }

    /**
     * JSON data for the index's `Craft.VueAdminTable` - `_index.twig`'s `tableDataEndpoint`.
     * Built in memory over `getAllEntryTypes()` rather than a database query (same reasoning as
     * `ContentSeoController::actionSeoScoreChecksTableData()`'s own checklist), since the full
     * row set is "however many entry types exist," not something that needs paging at the
     * database level.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function actionTableData(): Response {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $searchTerm = (string)$request->getParam('search', '');
        $sortDir = $request->getParam('sort.0.direction') === 'desc' ? SORT_DESC : SORT_ASC;

        $entryTypes = Craft::$app->getEntries()->getAllEntryTypes();

        if ($searchTerm !== '') {
            $needle = mb_strtolower($searchTerm);
            $entryTypes = array_values(array_filter(
                $entryTypes,
                static fn(EntryType $entryType): bool => str_contains(mb_strtolower($entryType->name), $needle)
                    || str_contains(mb_strtolower($entryType->handle), $needle),
            ));
        }

        $mappingsService = CoreSeoGeo::getInstance()->getStructuredDataMappings();
        $hasMappingsByHandle = [];

        foreach ($entryTypes as $entryType) {
            $hasMappingsByHandle[$entryType->handle] = $mappingsService->getRowsForEntryType($entryType->handle) !== [];
        }

        usort($entryTypes, static function(EntryType $a, EntryType $b) use ($sortDir, $hasMappingsByHandle): int {
            $mappingsCmp = (int)$hasMappingsByHandle[$b->handle] - (int)$hasMappingsByHandle[$a->handle];

            if ($mappingsCmp !== 0) {
                return $mappingsCmp;
            }

            return $sortDir === SORT_DESC
                ? strcasecmp($b->name, $a->name)
                : strcasecmp($a->name, $b->name);
        });

        $total = count($entryTypes);
        $pageEntryTypes = array_slice($entryTypes, ($page - 1) * $limit, $limit);

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $this->_entryTypeTableRows($pageEntryTypes),
        ]);
    }

    /**
     * One entry type's own mappings table, mirroring the "Structured data mappings" block
     * `content-seo-section.twig` used to carry. Posts to `actionSaveMapping()`/
     * `actionDeleteMapping()` via each mapping's own page/delete button.
     *
     * @param string $entryTypeHandle
     * @return Response
     * @throws NotFoundHttpException if no entry type has this handle
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function actionEntryType(string $entryTypeHandle): Response {
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No entry type exists with the handle "{handle}".', [
                'handle' => $entryTypeHandle,
            ]));
        }

        return $this->renderTemplate('core-seo-geo/structured-data/mappings/_entryType.twig', [
            'entryType' => $entryType,
            'mappingRows' => $this->_mappingTableRows(
                CoreSeoGeo::getInstance()->getStructuredDataMappings()->getRowsForEntryType($entryTypeHandle),
            ),
            'newMappingUrl' => UrlHelper::cpUrl("core-seo-geo/structured-data/mappings/{$entryTypeHandle}/new"),
        ]);
    }

    /**
     * The "Create mapping"/"Edit mapping" page for one structured data mapping on this entry
     * type - a new, unsaved `StructuredDataMapping` row when `$mappingId` is `null` (the
     * `.../new` route), the existing one otherwise. Renders the same type/properties mapping
     * editor as `StructuredDataField`'s own settings ({@see \digitalastronaut\
     * craftcoreseogeo\fields\StructuredDataField::settingsNamespace()}); posts to
     * `actionSaveMapping()`.
     *
     * @param string $entryTypeHandle
     * @param int|null $mappingId
     * @return Response
     * @throws NotFoundHttpException if no entry type has this handle, or no mapping has this ID
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function actionEditMapping(string $entryTypeHandle, ?int $mappingId = null): Response {
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No entry type exists with the handle "{handle}".', [
                'handle' => $entryTypeHandle,
            ]));
        }

        $mapping = $mappingId !== null
            ? CoreSeoGeo::getInstance()->getStructuredDataMappings()->getMappingById($mappingId, $entryTypeHandle)
            : null;

        if ($mappingId !== null && $mapping === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', "No structured data mapping exists with that ID."));
        }

        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();
        $type = $mapping->type ?? '';
        $properties = $this->_decodeConfig($mapping?->properties);
        $propertyNames = $schemaOrg->getType($type)['properties'] ?? [];
        $previewEntry = $mapping?->getPreviewEntry();

        return $this->renderTemplate('core-seo-geo/structured-data/mappings/_mappingEditor.twig', [
            'entryType' => $entryType,
            'mapping' => $mapping,
            'mappingProperties' => $properties,
            'mappingNamespace' => StructuredDataField::settingsNamespace(),
            'typeTree' => $schemaOrg->getTypeTree(),
            'expandedTypes' => $type !== '' ? $schemaOrg->getPrimaryAncestorPath($type) : [],
            'typeLabel' => $type !== '' ? ($schemaOrg->getType($type)['label'] ?? $type) : '',
            'propertyGroups' => $schemaOrg->getGroupedTypeProperties($type),
            // Covers every property of the type, not just the ones already added - the
            // picker's dropdown needs a tooltip for anything a user might still pick, not only
            // what's already in the mapping table below.
            'propertyTooltips' => $schemaOrg->getPropertyTooltips($propertyNames),
            'previewEntry' => $previewEntry,
            'calculatedValueHtml' => StructuredDataField::calculatedValueHtml($properties, $previewEntry),
            'jsonLdPreview' => StructuredDataField::jsonLdPreview($type, $properties, $previewEntry),
            'metadataHtml' => $mapping !== null ? $this->_mappingMetadataHtml($mapping) : null,
        ]);
    }

    /**
     * Persists the type/properties mapping posted from `_mappingEditor.twig`, creating a new
     * `coreseogeo_structured_data_mappings` row when no `id` was posted (a brand new mapping) or
     * updating the posted one otherwise. The posted `type`/`properties`/`previewElementId`
     * fields themselves are namespaced under `StructuredDataField::settingsNamespace()` - the
     * same mapping editor UI `StructuredDataField`'s own settings use - so they're read back out
     * from under that key rather than as plain top-level body params. `enabled` (the details
     * sidebar's lightswitch) is posted as a plain top-level param instead, same as every other
     * `enabled` lightswitch in this plugin (redirects, tracking scripts, ...).
     *
     * @return Response|null
     * @throws BadRequestHttpException if `entryTypeHandle` wasn't posted
     * @throws NotFoundHttpException if no entry type has this handle, or the posted `id` doesn't
     * match an existing mapping for it
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function actionSaveMapping(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $entryTypeHandle = $request->getRequiredBodyParam('entryTypeHandle');
        $id = $request->getBodyParam('id');

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No entry type exists with the handle "{handle}".', [
                'handle' => $entryTypeHandle,
            ]));
        }

        $mappingsService = CoreSeoGeo::getInstance()->getStructuredDataMappings();
        $mapping = $id ? $mappingsService->getMappingById((int)$id, $entryTypeHandle) : null;

        if ($id && $mapping === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', "No structured data mapping exists with that ID."));
        }

        $scoped = $request->getBodyParam('types', [])[StructuredDataField::settingsNamespaceKey()] ?? [];

        $savedMapping = $mappingsService->saveMapping(
            $mapping,
            $entryTypeHandle,
            (string)($scoped['type'] ?? ''),
            is_array($scoped['properties'] ?? null) ? $scoped['properties'] : [],
            StructuredDataField::normalizePreviewElementId($scoped['previewElementId'] ?? null),
            (bool)$request->getBodyParam('enabled', false),
        );

        if ($savedMapping === null) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the structured data mapping."));

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'Structured data mapping saved.'));

        // Passed through so `_mappingEditor.twig`'s `saveShortcutRedirect`'s `{id}` placeholder -
        // the Cmd+S/Ctrl+S shortcut's own redirect target, which keeps editing the same mapping
        // instead of the posted `redirect` field's "back to the entry type" URL - has a real ID
        // to resolve against for a brand new mapping, not just an already-saved one.
        return $this->redirectToPostedUrl($savedMapping);
    }

    /**
     * Deletes one structured data mapping, posted from the delete button in `_entryType.twig`'s
     * mappings table. Ajax-only (the button posts via `Craft.sendActionRequest()`), so this
     * relies on `asSuccess()`/`asFailure()` for their JSON responses rather than the
     * flash+redirect pattern the rest of this controller uses.
     *
     * @return Response|null
     * @throws BadRequestHttpException if `entryTypeHandle` or `id` wasn't posted
     * @throws NotFoundHttpException if no entry type has this handle, or the posted `id` doesn't
     * match an existing mapping for it
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function actionDeleteMapping(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $entryTypeHandle = $request->getRequiredBodyParam('entryTypeHandle');
        $id = (int)$request->getRequiredBodyParam('id');

        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No entry type exists with the handle "{handle}".', [
                'handle' => $entryTypeHandle,
            ]));
        }

        $mappingsService = CoreSeoGeo::getInstance()->getStructuredDataMappings();
        $mapping = $mappingsService->getMappingById($id, $entryTypeHandle);

        if ($mapping === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', "No structured data mapping exists with that ID."));
        }

        if (!$mappingsService->deleteMapping($mapping)) {
            return $this->asFailure(Craft::t('core-seo-geo', "Couldn't delete the structured data mapping."));
        }

        return $this->asSuccess(Craft::t('core-seo-geo', 'Structured data mapping deleted.'));
    }

    /**
     * @param string|null $json
     * @return array
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    private function _decodeConfig(?string $json): array {
        return $json !== null ? Json::decode($json) : [];
    }

    /**
     * Builds one `Craft.VueAdminTable` row per entry type for `actionTableData()`'s JSON
     * response. `title`/`url` feed the `__slot:title` column; `mappings` is comma-joined,
     * HTML-encoded text, falling back to `&ndash;` when the entry type has no mappings
     * configured.
     *
     * @param EntryType[] $entryTypes
     * @return array<int, array<string, mixed>>
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    private function _entryTypeTableRows(array $entryTypes): array {
        $mappingsService = CoreSeoGeo::getInstance()->getStructuredDataMappings();
        $rows = [];

        foreach ($entryTypes as $entryType) {
            $mappings = $this->_mappingSummaryRows($mappingsService->getRowsForEntryType($entryType->handle));

            $rows[] = [
                'id' => $entryType->handle,
                'title' => $entryType->name,
                'url' => UrlHelper::url("core-seo-geo/structured-data/mappings/{$entryType->handle}"),
                'handle' => $entryType->handle,
                'mappings' => $mappings !== [] ? Html::encode(implode(', ', array_map(
                    static fn(array $mapping): string => "{$mapping['typeLabel']} ({$mapping['propertyCount']})",
                    $mappings,
                ))) : '&ndash;',
            ];
        }

        return $rows;
    }

    /**
     * Builds the display-ready rows for `_entryType.twig`'s structured data mappings table: the
     * schema.org type's own label (falling back to its raw handle when it isn't a known type), a
     * comma-joined list of the mapping's configured property names, and an enabled/disabled
     * status badge ({@see _onOffStatusHtml()}) reflecting the mapping's own `enabled` column - a
     * disabled mapping stays listed here rather than disappearing, unlike deleting it.
     *
     * @param StructuredDataMapping[] $mappings
     * @return array<int, array{id: int, typeLabel: string, propertyNames: string, statusHtml: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    private function _mappingTableRows(array $mappings): array {
        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();

        return array_map(function(StructuredDataMapping $mapping) use ($schemaOrg): array {
            return [
                'id' => $mapping->id,
                'typeLabel' => $schemaOrg->getType($mapping->type)['label'] ?? $mapping->type,
                'propertyNames' => implode(', ', array_keys($this->_decodeConfig($mapping->properties))),
                'previewEntry' => $mapping->getPreviewEntry(),
                'statusHtml' => $this->_onOffStatusHtml(
                    $mapping->enabled,
                    Craft::t('core-seo-geo', 'Enabled'),
                    Craft::t('core-seo-geo', 'Disabled'),
                ),
            ];
        }, $mappings);
    }

    /**
     * @param StructuredDataMapping[] $mappings
     * @return array<int, array{typeLabel: string, propertyCount: int}>
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    private function _mappingSummaryRows(array $mappings): array {
        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();

        return array_map(function(StructuredDataMapping $mapping) use ($schemaOrg): array {
            return [
                'typeLabel' => $schemaOrg->getType($mapping->type)['label'] ?? $mapping->type,
                'propertyCount' => count($this->_decodeConfig($mapping->properties)),
            ];
        }, $mappings);
    }

    /**
     * The "Details" sidebar's read-only created/updated dates for an existing structured data
     * mapping - same shape as `RedirectsController::_metadataHtml()`'s own "Date created"/"Date
     * updated" rows, via the same `Cp::metadataHtml()` renderer. Not called for a brand new,
     * unsaved mapping, which has neither date yet.
     *
     * @param StructuredDataMapping $mapping
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    private function _mappingMetadataHtml(StructuredDataMapping $mapping): string {
        $formatter = Craft::$app->getFormatter();

        return Cp::metadataHtml([
            Craft::t('core-seo-geo', 'Date created') => $formatter->asDatetime($mapping->dateCreated, 'short', true),
            Craft::t('core-seo-geo', 'Date updated') => $formatter->asDatetime($mapping->dateUpdated, 'short', true),
        ]);
    }

    /**
     * The same status label shape `Lightswitch::getPreviewHtml()` uses for its own on/off
     * states - teal+check when `$on`, gray+xmark otherwise - with whatever labels the caller
     * wants for each state. Same shape as `ContentSeoController::_onOffStatusHtml()`, duplicated
     * rather than shared since it's six lines and the two controllers no longer have anything
     * else in common now that mappings have moved off Content SEO entirely.
     *
     * @param bool $on
     * @param string $onLabel
     * @param string $offLabel
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    private function _onOffStatusHtml(bool $on, string $onLabel, string $offLabel): string {
        return Cp::statusLabelHtml([
            'color' => $on ? Color::Teal : Color::Gray,
            'label' => $on ? $onLabel : $offLabel,
            'icon' => $on ? 'check' : 'xmark',
        ]);
    }
}
