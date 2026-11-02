<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\services;

use Craft;

use craft\base\Component;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\elements\StructuredData as StructuredDataElement;
use digitalastronaut\craftcoreseogeo\jobs\SyncStructuredDataMappings;
use digitalastronaut\craftcoreseogeo\records\StructuredDataMapping as StructuredDataMappingRecord;

/**
 * Class StructuredDataMappingService
 *
 * Reads and writes `coreseogeo_structured_data_mappings` rows - the schema.org type/property
 * mapping configuration for an entry type, moved off `StructuredDataField`'s own settings so an
 * entry type can have any number of mappings instead of one rigid mapping per field instance.
 * Scoped by entry type rather than section so a mapping also works for an entry type that's only
 * ever used inside a Matrix field, which has no section of its own at all. Not site-scoped - a
 * mapping's property templates resolve per element (and so, through the element, per site) the
 * same way a `SeoField`'s templates do.
 *
 * Also owns syncing `coreseogeo_structured_data` (the computed JSON-LD, one row per
 * mapping/entry/site) against this configuration - see `syncEntry()` and `syncEntryType()`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.3.0
 */
class StructuredDataMappingService extends Component {
    /**
     * Returns every mapping row for the given entry type, ordered the way they should display -
     * by `sortOrder` first, then `id` as a stable tie-breaker for rows that don't have one yet.
     *
     * @param string $entryTypeHandle
     * @return StructuredDataMappingRecord[]
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function getRowsForEntryType(string $entryTypeHandle): array {
        return StructuredDataMappingRecord::find()
            ->where(['entryTypeHandle' => $entryTypeHandle])
            ->orderBy(['sortOrder' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * Returns the mapping with the given ID, scoped to the given entry type so one entry type's
     * mapping can't be loaded (or later saved over) via another's edit URL.
     *
     * @param int $id
     * @param string $entryTypeHandle
     * @return StructuredDataMappingRecord|null
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function getMappingById(int $id, string $entryTypeHandle): ?StructuredDataMappingRecord {
        return StructuredDataMappingRecord::findOne(['id' => $id, 'entryTypeHandle' => $entryTypeHandle]);
    }

    /**
     * Persists the given mapping's type/properties, creating a new row (for the given entry
     * type) when `$mapping` is `null` rather than an existing record. Called from
     * `StructuredDataMappingsController::actionSaveMapping()`.
     *
     * @param StructuredDataMappingRecord|null $mapping the existing mapping to update, or `null` to create a new one
     * @param string $entryTypeHandle
     * @param string $type a schema.org type name, e.g. `Product`
     * @param array<string, array{template?: string|null}> $properties keyed by schema.org property name
     * @param int|null $previewElementId the entry chosen in the mapping editor's "Preview Entry" field
     * @param bool $enabled whether the mapping should keep computing/outputting structured data
     * @return StructuredDataMappingRecord|null the saved mapping (with its `id` populated, for a
     * brand new one) on success, `null` if the save failed
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function saveMapping(?StructuredDataMappingRecord $mapping, string $entryTypeHandle, string $type, array $properties, ?int $previewElementId = null, bool $enabled = true): ?StructuredDataMappingRecord {
        $mapping ??= new StructuredDataMappingRecord(['entryTypeHandle' => $entryTypeHandle]);

        $mapping->type = $type;
        $mapping->properties = Json::encode($properties);
        $mapping->previewElementId = $previewElementId;
        $mapping->enabled = $enabled;

        // `ActiveRecord::prepareForDb()` only bumps `dateUpdated` when another attribute's
        // *value* actually changed - re-saving with no net content change (or flipping the
        // "Enabled" lightswitch back to where it started) would otherwise leave it stale, even
        // though the user explicitly hit Save. Setting it ourselves forces it to always advance.
        if (!$mapping->getIsNewRecord()) {
            $mapping->dateUpdated = Db::prepareDateForDb(DateTimeHelper::now());
        }

        if (!$mapping->save()) return null;

        if ($mapping->enabled) {
            // Backfills every existing entry of this type against this mapping - without this,
            // a brand new mapping (or a type/properties edit to an existing one) would only take
            // effect the next time each entry happens to be resaved, rather than "every entry of
            // a type with an active mapping is always synced" being true immediately.
            Craft::$app->getQueue()->push(new SyncStructuredDataMappings(['entryTypeHandle' => $entryTypeHandle]));
        } else {
            // Mirrors `deleteMapping()`'s own cleanup - a disabled mapping shouldn't keep
            // outputting structured data computed while it was still enabled, even though the
            // mapping row itself (and its configured properties) stick around for re-enabling.
            $this->_deleteStructuredDataForMapping($mapping->id);
        }

        return $mapping;
    }

    /**
     * Deletes the given mapping, along with every `StructuredData` element it computed (across
     * every site) - a hard delete rather than Craft's usual soft-delete/trash, since a
     * `StructuredData` row with no mapping behind it any more is just dead weight, not something
     * a user would ever want to restore. `coreseogeo_structured_data_mappings`'s own row is removed
     * after that, since `coreseogeo_structured_data.mappingId`'s `CASCADE` foreign key would
     * otherwise race the element deletion against the mapping row disappearing out from under it.
     *
     * @param StructuredDataMappingRecord $mapping
     * @return bool whether the mapping row itself was deleted
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function deleteMapping(StructuredDataMappingRecord $mapping): bool {
        $this->_deleteStructuredDataForMapping($mapping->id);

        return (bool)$mapping->delete();
    }

    /**
     * Deletes every mapping row for the given entry type handle, along with whatever
     * `StructuredData` elements they computed - called from `PluginTrait::
     * registerStructuredDataSync()`'s `EVENT_AFTER_DELETE_ENTRY_TYPE` handler, since there's no
     * foreign key to cascade this (`entryTypeHandle` is a plain string, not a reference to the -
     * by then gone - entry type's ID).
     *
     * @param string $entryTypeHandle
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.40.0
     */
    public function deleteRowsForEntryType(string $entryTypeHandle): void {
        foreach ($this->getRowsForEntryType($entryTypeHandle) as $mapping) {
            $this->deleteMapping($mapping);
        }
    }

    /**
     * Returns the distinct entry type handles that have at least one mapping configured - every
     * entry type `syncEntryType()` has anything to do for. Used by
     * `StructuredDataController::actionSync()` to back fill every mapped entry type at once.
     *
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function getMappedEntryTypeHandles(): array {
        return StructuredDataMappingRecord::find()
            ->select(['entryTypeHandle'])
            ->distinct()
            ->column();
    }

    /**
     * Returns the distinct schema.org type names configured across every mapping - the
     * free-form types a section's content settings can pick (e.g. `Person`, `WebPage`), as
     * opposed to `StructuredData::TYPES`' fixed standalone list. Used by
     * `StructuredData::defineSources()` to add a sidebar source for every type actually
     * configured, not just the built-in standalone ones.
     *
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.24.0
     */
    public function getMappedTypes(): array {
        return StructuredDataMappingRecord::find()
            ->select(['type'])
            ->distinct()
            ->column();
    }

    /**
     * Ensures the given entry has one up to date `StructuredData` row for every mapping
     * configured on its entry type - called from `PluginTrait::registerStructuredDataSync()` on
     * every entry save, the same way `StructuredDataField::afterElementSave()` used to compute
     * its own single mapping. Resolves the entry's type itself rather than taking a handle,
     * since an `Entry` always knows its own type and callers already have the entry, not its
     * handle. Unlike a section, every entry has a type regardless of whether it's owned by a
     * section or nested inside a Matrix field, so this covers both the same way.
     *
     * An entry type with no mappings at all is a no-op - `getRowsForEntryType()` simply returns
     * nothing to iterate.
     *
     * @param Entry $entry
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function syncEntry(Entry $entry): void {
        foreach ($this->getRowsForEntryType($entry->getType()->handle) as $mapping) {
            if (!$mapping->enabled) continue;

            $this->_syncEntryMapping($mapping, $entry);
        }
    }

    /**
     * Full reconciliation for one entry type: every (entry, site) pair of that type gets one
     * `StructuredData` row per mapping currently configured for it. Used both for backfilling a
     * newly saved mapping (`saveMapping()`, via the queued `SyncStructuredDataMappings` job) and
     * for the manual/deploy-time `core-seo-geo/structured-data/sync` console command. Unlike
     * `syncEntry()` (one entry, the site it was just saved in), this iterates every site an
     * entry is enabled for via `site('*')->unique(false)` - so e.g. 17 entries in 2 languages
     * against 3 mappings produces exactly 17 * 2 * 3 = 102 synced rows, not just whichever site
     * happened to be saved most recently.
     *
     * @param string $entryTypeHandle
     * @param callable(int, int): void|null $onProgress called after each (entry, site, mapping)
     * row is synced, with the running count and the total to sync
     * @return int how many (entry, site, mapping) rows were synced
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function syncEntryType(string $entryTypeHandle, ?callable $onProgress = null): int {
        $mappings = array_values(array_filter(
            $this->getRowsForEntryType($entryTypeHandle),
            static fn(StructuredDataMappingRecord $mapping): bool => $mapping->enabled,
        ));

        if ($mappings === []) return 0;

        $entries = Entry::find()
            ->type($entryTypeHandle)
            ->site('*')
            ->unique(false)
            ->status(null)
            ->all();

        $total = count($entries) * count($mappings);
        $synced = 0;

        foreach ($entries as $entry) {
            foreach ($mappings as $mapping) {
                $this->_syncEntryMapping($mapping, $entry);
                $synced++;

                if ($onProgress !== null) $onProgress($synced, $total);
            }
        }

        return $synced;
    }

    // Private Methods
    // =========================================================================

    /**
     * Hard-deletes every `StructuredData` element computed for the given mapping, across every
     * site - shared by `deleteMapping()` (the mapping row itself is gone right after) and
     * `saveMapping()`'s own cleanup when a mapping is saved as disabled (the mapping row stays,
     * only its already-computed output goes away).
     *
     * @param int $mappingId
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.39.0
     */
    private function _deleteStructuredDataForMapping(int $mappingId): void {
        $elements = StructuredDataElement::find()
            ->mappingId($mappingId)
            ->site('*')
            ->unique(false)
            ->status(null)
            ->all();

        foreach ($elements as $element) {
            Craft::$app->getElements()->deleteElement($element, true);
        }
    }

    /**
     * Computes and upserts the one `StructuredData` row for `$mapping` against `$entry`'s
     * current site - the same "find the existing row for this (mapping, owner, site), or start a
     * new one" shape `StructuredDataField::_persistComputedData()` used when the mapping lived on
     * the field itself.
     *
     * @param StructuredDataMappingRecord $mapping
     * @param Entry $entry
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    private function _syncEntryMapping(StructuredDataMappingRecord $mapping, Entry $entry): void {
        $structuredData = StructuredDataElement::find()
            ->mappingId($mapping->id)
            ->ownerId($entry->id)
            ->siteId($entry->siteId)
            ->status(null)
            ->one() ?? new StructuredDataElement([
                'mappingId' => $mapping->id,
                'ownerId' => $entry->id,
                'siteId' => $entry->siteId,
            ]);

        $properties = Json::decode($mapping->properties ?? '[]') ?: [];

        $structuredData->title = Craft::t('core-seo-geo', '{element} ({type})', [
            'element' => (string)$entry,
            'type' => $mapping->type,
        ]);
        $structuredData->type = $mapping->type;
        $structuredData->properties = CoreSeoGeo::getInstance()->getStructuredData()->renderProperties($entry, $properties);

        if (!Craft::$app->getElements()->saveElement($structuredData)) {
            Craft::warning("Couldn't sync structured data mapping #{$mapping->id} for entry #{$entry->id}: " . Json::encode($structuredData->getErrors()), __METHOD__);
        }
    }
}
