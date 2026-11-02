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
 * Class m261102_142126_add_markdown_enabled_column
 *
 * Adds `coreseogeo_seodata.markdownEnabled` - the per-section Markdown toggle
 * ({@see \digitalastronaut\craftcoreseogeo\services\ContentSeoService::isMarkdownEnabled()}) -
 * for installs that already ran `Install.php` before that column existed on it.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.39.0
 */
class m261102_142126_add_markdown_enabled_column extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.39.0
     */
    public function safeUp(): bool {
        if (!Craft::$app->getDb()->columnExists(Table::SEODATA, 'markdownEnabled')) {
            $this->addColumn(Table::SEODATA, 'markdownEnabled', $this->boolean()->notNull()->defaultValue(true)->after('sitemapEnabled'));
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
        echo "m261102_142126_add_markdown_enabled_column cannot be reverted.\n";

        return false;
    }
}
