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
 * Class m261017_160000_widen_seo_score_content_columns
 *
 * `metaTitle`/`metaDescription` were added as `$this->string()` - MySQL `VARCHAR(255)` - but
 * nothing constrains `SeoData::$metaTitle`/`$metaDescription` to that length (`ValidDescriptionCheck`
 * only flags an out-of-range length in its message, it never fails the check), so a legitimately
 * saved description past 255 characters fails this table's `UPDATE`/`INSERT` outright under MySQL's
 * default strict mode instead of silently truncating. Widened to 500, comfortably past any
 * realistic title/description while staying well under InnoDB's ~767-character limit for a
 * utf8mb4 column that's still part of a plain (non-prefixed) index.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.7
 */
class m261017_160000_widen_seo_score_content_columns extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.7
     */
    public function safeUp(): bool {
        $this->alterColumn(Table::SEOSCORE, 'metaTitle', $this->string(500));
        $this->alterColumn(Table::SEOSCORE, 'metaDescription', $this->string(500));

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.7
     */
    public function safeDown(): bool {
        $this->alterColumn(Table::SEOSCORE, 'metaTitle', $this->string());
        $this->alterColumn(Table::SEOSCORE, 'metaDescription', $this->string());

        return true;
    }
}
