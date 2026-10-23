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
 * Class m261021_140000_add_sitemap_enabled_to_seo_data
 *
 * Adds `sitemapEnabled` to `coreseogeo_seodata` for existing installs. See `Install.php` for the
 * canonical fresh-install shape, which creates the column directly.
 *
 * Lets a section be excluded from the sitemap entirely (both its index entry and its own
 * sub-sitemap) from that section's own Content SEO settings page, independent of the plugin-wide
 * `enableSitemap` setting. Defaults to `true` so existing sections keep sitemapping exactly as
 * before.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.2.1
 */
class m261021_140000_add_sitemap_enabled_to_seo_data extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.2.1
     */
    public function safeUp(): bool {
        if ($this->db->columnExists(Table::SEODATA, 'sitemapEnabled')) {
            return true;
        }

        $this->addColumn(Table::SEODATA, 'sitemapEnabled', $this->boolean()->notNull()->defaultValue(true)->after('canonicalConfig'));

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.2.1
     */
    public function safeDown(): bool {
        $this->dropColumn(Table::SEODATA, 'sitemapEnabled');

        return true;
    }
}
