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
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\elements\StructuredData as StructuredDataElement;
use digitalastronaut\craftcoreseogeo\jobs\SyncStructuredDataMappings;
use digitalastronaut\craftcoreseogeo\records\StructuredDataMapping as StructuredDataMappingRecord;

/**
 * Class StructuredDataMappingService
 *
 * Reads and writes `coreseogeo_structureddatamappings` rows - the schema.org type/property
 * mapping configuration for a section, moved off `StructuredDataField`'s own settings so a
 * section can have any number of mappings instead of one rigid mapping per field instance. Not
 * site-scoped - a mapping's property templates resolve per element (and so, through the
 * element, per site) the same way a `SeoField`'s templates do.
 *
 * Also owns syncing `coreseogeo_structureddata` (the computed JSON-LD, one row per
 * mapping/entry/site) against this configuration - see `syncEntry()` and `syncSection()`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.3.0
 */
class StructuredDataMappingService extends Component {
    /**
     * Returns every mapping row for the given section, ordered the way they should display - by
     * `sortOrder` first, then `id` as a stable tie-breaker for rows that don't have one yet.
     *
     * @param string $sectionHandle
     * @return StructuredDataMappingRecord[]
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function getRowsForSection(string $sectionHandle): array {
        return StructuredDataMappingRecord::find()
            ->where(['sectionHandle' => $sectionHandle])
            ->orderBy(['sortOrder' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * Returns the mapping with the given ID, scoped to the given section so one section's
     * mapping can't be loaded (or later saved over) via another's edit URL.
     *
     * @param int $id
     * @param string $sectionHandle
     * @return StructuredDataMappingRecord|null
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function getMappingById(int $id, string $sectionHandle): ?StructuredDataMappingRecord {
        return StructuredDataMappingRecord::findOne(['id' => $id, 'sectionHandle' => $sectionHandle]);
    }

    /**
     * Persists the given mapping's type/properties, creating a new row (for the given section)
     * when `$mapping` is `null` rather than an existing record. Called from
     * `ContentSeoController::actionSaveMapping()`.
     *
     * @param StructuredDataMappingRecord|null $mapping the existing mapping to update, or `null` to create a new one
     * @param string $sectionHandle
     * @param string $type a schema.org type name, e.g. `Product`
     * @param array<string, array{template?: string|null}> $properties keyed by schema.org property name
     * @param int|null $previewElementId the entry chosen in the mapping editor's "Preview Entry" field
     * @return bool whether the save succeeded
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function saveMapping(?StructuredDataMappingRecord $mapping, string $sectionHandle, string $type, array $properties, ?int $previewElementId = null): bool {
        $mapping ??= new StructuredDataMappingRecord(['sectionHandle' => $sectionHandle]);

        $mapping->type = $type;
        $mapping->properties = Json::encode($properties);
        $mapping->previewElementId = $previewElementId;

        if (!$mapping->save()) return false;

        // Backfills every existing entry in the section against this mapping - without this, a
        // brand new mapping (or a type/properties edit to an existing one) would only take
        // effect the next time each entry happens to be resaved, rather than "every entry from a
        // section with an active mapping is always synced" being true immediately.
        Craft::$app->getQueue()->push(new SyncStructuredDataMappings(['sectionHandle' => $sectionHandle]));

        return true;
    }

    /**
     * Deletes the given mapping, along with every `StructuredData` element it computed (across
     * every site) - a hard delete rather than Craft's usual soft-delete/trash, since a
     * `StructuredData` row with no mapping behind it any more is just dead weight, not something
     * a user would ever want to restore. `coreseogeo_structureddatamappings`'s own row is removed
     * after that, since `coreseogeo_structureddata.mappingId`'s `CASCADE` foreign key would
     * otherwise race the element deletion against the mapping row disappearing out from under it.
     *
     * @param StructuredDataMappingRecord $mapping
     * @return bool whether the mapping row itself was deleted
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function deleteMapping(StructuredDataMappingRecord $mapping): bool {
        $elements = StructuredDataElement::find()
            ->mappingId($mapping->id)
            ->site('*')
            ->unique(false)
            ->status(null)
            ->all();

        foreach ($elements as $element) {
            Craft::$app->getElements()->deleteElement($element, true);
        }

        return (bool)$mapping->delete();
    }

    /**
     * Returns the distinct section handles that have at least one mapping configured - every
     * section `syncSection()` has anything to do for. Used by
     * `StructuredDataController::actionSync()` to back fill every mapped section at once.
     *
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function getMappedSectionHandles(): array {
        return StructuredDataMappingRecord::find()
            ->select(['sectionHandle'])
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
     * configured on its section - called from `PluginTrait::registerStructuredDataSync()` on
     * every entry save, the same way `StructuredDataField::afterElementSave()` used to compute
     * its own single mapping. Resolves the entry's section itself rather than taking a handle,
     * since a `Entry` always knows its own section and callers already have the entry, not its
     * handle.
     *
     * A `Single` section (or any section with no mappings at all) is a no-op -
     * `getRowsForSection()` simply returns nothing to iterate.
     *
     * @param Entry $entry
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function syncEntry(Entry $entry): void {
        $section = $entry->getSection();

        if ($section === null) return;

        foreach ($this->getRowsForSection($section->handle) as $mapping) {
            $this->_syncEntryMapping($mapping, $entry);
        }
    }

    /**
     * Full reconciliation for one section: every (entry, site) pair the section actually has
     * content for gets one `StructuredData` row per mapping currently configured for it. Used
     * both for backfilling a newly saved mapping (`saveMapping()`, via the queued
     * `SyncStructuredDataMappings` job) and for the manual/deploy-time
     * `core-seo-geo/structured-data/sync` console command. Unlike `syncEntry()` (one entry, the
     * site it was just saved in), this iterates every site an entry is enabled for via
     * `site('*')->unique(false)` - so e.g. 17 entries in 2 languages against 3 mappings produces
     * exactly 17 * 2 * 3 = 102 synced rows, not just whichever site happened to be saved most
     * recently.
     *
     * @param string $sectionHandle
     * @param callable(int, int): void|null $onProgress called after each (entry, site, mapping)
     * row is synced, with the running count and the total to sync
     * @return int how many (entry, site, mapping) rows were synced
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function syncSection(string $sectionHandle, ?callable $onProgress = null): int {
        $mappings = $this->getRowsForSection($sectionHandle);

        if ($mappings === []) return 0;

        $entries = Entry::find()
            ->section($sectionHandle)
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
