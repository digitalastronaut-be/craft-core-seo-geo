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
 * Class m261017_150000_add_seo_score_checks_column
 *
 * Adds the `checks` JSON column to `coreseogeo_seoscores` for existing installs -- the per-check
 * breakdown (key/label/weight/valid/applicable/message) shown in the element edit screen
 * sidebar. See `Install.php` for the canonical fresh-install shape, which creates the same
 * column. Existing rows read `checks` as `null` until their next recalculation; no backfill.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class m261017_150000_add_seo_score_checks_column extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function safeUp(): bool {
        if (!$this->db->columnExists(Table::SEOSCORE, 'checks')) {
            $this->addColumn(Table::SEOSCORE, 'checks', $this->json()->after('metaDescription'));
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function safeDown(): bool {
        if ($this->db->columnExists(Table::SEOSCORE, 'checks')) {
            $this->dropColumn(Table::SEOSCORE, 'checks');
        }

        return true;
    }
}
