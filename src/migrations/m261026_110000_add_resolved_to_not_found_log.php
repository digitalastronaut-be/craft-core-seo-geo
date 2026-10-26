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

/**
 * Class m261026_110000_add_resolved_to_not_found_log
 *
 * Adds `resolved` to `coreseogeo_404s` for existing installs. See `Install.php` for the
 * canonical fresh-install shape, which creates the column directly.
 *
 * The "404s" tab's detail page's "Manually fixed" lightswitch - for a 404 that isn't, and
 * never will be, covered by a redirect (a deleted asset re-uploaded under the same path, say).
 * Defaults to `false` so every existing row still shows as needing attention.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.23.0
 */
class m261026_110000_add_resolved_to_not_found_log extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    public function safeUp(): bool {
        if ($this->db->columnExists(Table::NOTFOUNDLOG, 'resolved')) {
            return true;
        }

        $this->addColumn(Table::NOTFOUNDLOG, 'resolved', $this->boolean()->notNull()->defaultValue(false)->after('hitCount'));

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    public function safeDown(): bool {
        $this->dropColumn(Table::NOTFOUNDLOG, 'resolved');

        return true;
    }
}
