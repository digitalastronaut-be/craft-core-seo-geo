<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;

use digitalastronaut\craftcoreseogeo\db\Table;
use digitalastronaut\craftcoreseogeo\elements\StructuredData as StructuredDataElement;
use digitalastronaut\craftcoreseogeo\fields\StructuredDataField;

/**
 * Class m261021_180000_rename_field_id_to_mapping_id_on_structured_data
 *
 * Renames `coreseogeo_structureddata.fieldId` to `mappingId`, re-pointing its foreign key at
 * `coreseogeo_structureddatamappings` instead of `craft_fields`. See `Install.php` for the
 * canonical fresh-install shape, which creates the table with this same column.
 *
 * `StructuredDataField` no longer owns a type/properties mapping of its own - that configuration
 * lives entirely in `coreseogeo_structureddatamappings` now, keyed per section rather than per
 * field instance (see `m261021_150000_add_structured_data_mappings_table.php`). A row computed
 * under the old regime is keyed by a `StructuredDataField` instance's ID, which has no
 * correspondence to a `coreseogeo_structureddatamappings` row at all - there's nothing to
 * migrate that old ID *into*, so those rows are hard-deleted here instead. The new
 * section-driven sync (`StructuredDataMappingService::syncSection()`) recomputes them correctly
 * keyed by `mappingId` the next time it runs (on the owning entry's next save, or via the
 * `core-seo-geo/structured-data/sync` console command).
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.4.0
 */
class m261021_180000_rename_field_id_to_mapping_id_on_structured_data extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function safeUp(): bool {
        if ($this->db->columnExists(Table::STRUCTUREDDATA, 'mappingId')) {
            return true;
        }

        $this->dropForeignKeyIfExists(Table::STRUCTUREDDATA, ['fieldId']);
        $this->dropIndexIfExists(Table::STRUCTUREDDATA, ['fieldId', 'ownerId', 'siteId']);
        $this->renameColumn(Table::STRUCTUREDDATA, 'fieldId', 'mappingId');

        // Only safe to delete *through* the elements service (rather than before the rename)
        // because `StructuredDataQuery::afterPrepare()` - like the rest of this plugin's
        // already-updated PHP - selects the `mappingId` column by name; running this against the
        // pre-rename schema would fail with an unknown column error.
        $this->_deleteFieldBackedRows();

        $this->addForeignKey(null, Table::STRUCTUREDDATA, ['mappingId'], Table::STRUCTUREDDATAMAPPINGS, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::STRUCTUREDDATA, ['mappingId', 'ownerId', 'siteId'], true);

        $this->_clearFieldSettings();

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function safeDown(): bool {
        $this->dropForeignKeyIfExists(Table::STRUCTUREDDATA, ['mappingId']);
        $this->dropIndexIfExists(Table::STRUCTUREDDATA, ['mappingId', 'ownerId', 'siteId']);
        $this->renameColumn(Table::STRUCTUREDDATA, 'mappingId', 'fieldId');
        $this->addForeignKey(null, Table::STRUCTUREDDATA, ['fieldId'], CraftTable::FIELDS, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::STRUCTUREDDATA, ['fieldId', 'ownerId', 'siteId'], true);

        return true;
    }

    /**
     * Hard-deletes every field-backed (`ownerId` not null) `StructuredData` element - see the
     * class docblock for why these can't be migrated forward. Standalone objects (`ownerId`
     * null) are untouched. Goes through the elements service (not a raw table delete) so
     * `craft_elements`/`elements_sites`/the search index don't end up with orphaned rows for
     * elements this table no longer has content for.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    private function _deleteFieldBackedRows(): void {
        $ids = (new Query())
            ->select(['id'])
            ->from(Table::STRUCTUREDDATA)
            ->where(['not', ['ownerId' => null]])
            ->column($this->db);

        foreach ($ids as $id) {
            $element = Craft::$app->getElements()->getElementById((int)$id, StructuredDataElement::class, null, ['status' => null]);

            if ($element !== null) Craft::$app->getElements()->deleteElement($element, true);
        }
    }

    /**
     * Clears the now-unrecognized `type`/`properties`/`previewElementId` settings off every
     * existing `StructuredDataField` instance. Without this, the next thing to touch that field
     * at all - a CP edit screen, a `project-config/apply`, another entry save - would try to
     * hydrate it from its old stored settings and fail with an `UnknownPropertyException`, since
     * the field class no longer declares those properties (see the class docblock for where its
     * configuration moved to instead).
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    private function _clearFieldSettings(): void {
        $this->update(CraftTable::FIELDS, ['settings' => null], ['type' => StructuredDataField::class]);
    }
}
