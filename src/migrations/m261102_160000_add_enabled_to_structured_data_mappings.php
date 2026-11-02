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

use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class m261102_160000_add_enabled_to_structured_data_mappings
 *
 * Adds `coreseogeo_structureddatamappings.enabled` - lets a mapping be turned off without
 * deleting it ({@see \digitalastronaut\craftcoreseogeo\services\
 * StructuredDataMappingService::saveMapping()}) - for installs that already ran `Install.php`
 * before that column existed on it.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.39.0
 */
class m261102_160000_add_enabled_to_structured_data_mappings extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.39.0
     */
    public function safeUp(): bool {
        if (!Craft::$app->getDb()->columnExists(Table::STRUCTUREDDATAMAPPINGS, 'enabled')) {
            $this->addColumn(Table::STRUCTUREDDATAMAPPINGS, 'enabled', $this->boolean()->notNull()->defaultValue(true)->after('previewElementId'));
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.39.0
     */
    public function safeDown(): bool {
        echo "m261102_160000_add_enabled_to_structured_data_mappings cannot be reverted.\n";

        return false;
    }
}
