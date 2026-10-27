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
 * Class m261026_120000_reset_seo_score_checks_for_status_format
 *
 * `coreseogeo_seoscores`.`checks` moved from a per-check `valid` boolean to a `status`
 * (`SeoScoreCheckStatus`) string -- existing rows still hold the old shape, which throws reading
 * the now-missing `status` key. Same no-backfill approach as
 * `m261017_150000_add_seo_score_checks_column`: cleared back to `null` here, re-populated the
 * next time each element's score recalculates.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.29.0
 */
class m261026_120000_reset_seo_score_checks_for_status_format extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.29.0
     */
    public function safeUp(): bool {
        $this->update(Table::SEOSCORE, ['checks' => null], '', [], false);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.29.0
     */
    public function safeDown(): bool {
        return true;
    }
}
