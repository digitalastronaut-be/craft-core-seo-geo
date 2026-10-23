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
 * Class m261017_140000_add_seo_score_content_columns
 *
 * Adds the `metaTitle`/`metaDescription` columns to `coreseogeo_seoscores` for existing installs
 * -- a denormalized, indexed copy of each row's resolved title/description, so the duplicate
 * content check can look sideways at other elements' rows without scanning JSON. See
 * `Install.php` for the canonical fresh-install shape, which creates the same columns.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.5
 */
class m261017_140000_add_seo_score_content_columns extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function safeUp(): bool {
        if (!$this->db->columnExists(Table::SEOSCORE, 'metaTitle')) {
            $this->addColumn(Table::SEOSCORE, 'metaTitle', $this->string()->after('pending'));
            $this->createIndex(null, Table::SEOSCORE, ['siteId', 'metaTitle']);
        }

        if (!$this->db->columnExists(Table::SEOSCORE, 'metaDescription')) {
            $this->addColumn(Table::SEOSCORE, 'metaDescription', $this->string()->after('metaTitle'));
            $this->createIndex(null, Table::SEOSCORE, ['siteId', 'metaDescription']);
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.5
     */
    public function safeDown(): bool {
        if ($this->db->columnExists(Table::SEOSCORE, 'metaDescription')) {
            $this->dropColumn(Table::SEOSCORE, 'metaDescription');
        }

        if ($this->db->columnExists(Table::SEOSCORE, 'metaTitle')) {
            $this->dropColumn(Table::SEOSCORE, 'metaTitle');
        }

        return true;
    }
}
