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

use craft\db\Migration;
use craft\db\Table as CraftTable;

use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class m261021_170000_add_preview_element_id_to_structured_data_mappings
 *
 * Adds `previewElementId` to `coreseogeo_structureddatamappings` for existing installs - the
 * mapping-row counterpart to `StructuredDataField::$previewElementId`, letting
 * `content-seo-mapping.twig` resolve the same "Preview Entry" picker to calculate each
 * property's value against. See `Install.php` for the canonical fresh-install shape. `SET NULL`
 * on delete rather than `CASCADE`: the previewed entry is only a reference for the mapping
 * editor's preview, not something the mapping belongs to - deleting it shouldn't delete the
 * mapping.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.3.0
 */
class m261021_170000_add_preview_element_id_to_structured_data_mappings extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function safeUp(): bool {
        if ($this->db->columnExists(Table::STRUCTUREDDATAMAPPINGS, 'previewElementId')) {
            return true;
        }

        $this->addColumn(Table::STRUCTUREDDATAMAPPINGS, 'previewElementId', $this->integer()->after('properties'));
        $this->addForeignKey(null, Table::STRUCTUREDDATAMAPPINGS, ['previewElementId'], CraftTable::ELEMENTS, ['id'], 'SET NULL', null);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function safeDown(): bool {
        $this->dropForeignKeyIfExists(Table::STRUCTUREDDATAMAPPINGS, ['previewElementId']);
        $this->dropColumn(Table::STRUCTUREDDATAMAPPINGS, 'previewElementId');

        return true;
    }
}
