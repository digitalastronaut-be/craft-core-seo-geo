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

use digitalastronaut\craftcoreseogeo\db\Table;
use digitalastronaut\craftcoreseogeo\records\TrackingScript;

/**
 * Class m261022_130000_add_tracking_scripts_table
 *
 * Adds the `coreseogeo_trackingscripts` table for existing installs. See `Install.php` for the
 * canonical fresh-install shape, which creates the same table.
 *
 * Each row is one tracking script a site owner wants injected into every page - a head script
 * (e.g. a GTM/gtag loader) and/or its paired body script (e.g. a `<noscript>` fallback), each
 * independently placed via `scriptLocation`/`bodyScriptLocation`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.9
 */
class m261022_130000_add_tracking_scripts_table extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function safeUp(): bool {
        if ($this->db->tableExists(Table::TRACKINGSCRIPTS)) {
            return true;
        }

        $this->createTable(Table::TRACKINGSCRIPTS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'scriptTemplate' => $this->mediumText(),
            'scriptLocation' => $this->string()->notNull()->defaultValue(TrackingScript::LOCATION_HEAD_END),
            'bodyScriptTemplate' => $this->mediumText(),
            'bodyScriptLocation' => $this->string()->notNull()->defaultValue(TrackingScript::LOCATION_BODY_START),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function safeDown(): bool {
        $this->dropTableIfExists(Table::TRACKINGSCRIPTS);

        return true;
    }
}
