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
 * Class m261102_170000_rename_tables_to_snake_case
 *
 * Renames the plugin's tables to consistent snake_case (`coreseogeo_seodata` ->
 * `coreseogeo_seo_data`, `coreseogeo_structureddatamappings` ->
 * `coreseogeo_structured_data_mappings`, etc.) for installs that already ran `Install.php`
 * before `{@see Table}` switched to the new names.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.39.0
 */
class m261102_170000_rename_tables_to_snake_case extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.39.0
     */
    public function safeUp(): bool {
        $renames = [
            '{{%coreseogeo_structureddata}}' => Table::STRUCTUREDDATA,
            '{{%coreseogeo_seoscores}}' => Table::SEOSCORE,
            '{{%coreseogeo_seodata}}' => Table::SEODATA,
            '{{%coreseogeo_structureddatamappings}}' => Table::STRUCTUREDDATAMAPPINGS,
            '{{%coreseogeo_trackingscripts}}' => Table::TRACKINGSCRIPTS,
            '{{%coreseogeo_txtfilecontent}}' => Table::TXTFILECONTENT,
        ];

        $db = Craft::$app->getDb();

        foreach ($renames as $oldName => $newName) {
            if ($db->tableExists($oldName) && !$db->tableExists($newName)) {
                $this->renameTable($oldName, $newName);
            }
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
        echo "m261102_170000_rename_tables_to_snake_case cannot be reverted.\n";

        return false;
    }
}
