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
 * Class m261024_130000_add_llms_txt_fields_to_seo_data
 *
 * Adds `llmsTxtTitle`/`llmsTxtSummary` to `coreseogeo_seodata` for existing installs. See
 * `Install.php` for the canonical fresh-install shape, which creates the columns directly.
 *
 * Both only apply to a site's global bundle row (`sectionHandle === ''`) - llms.txt's title and
 * summary are a per-site, sitewide thing, the same way `titleConfig`/`descriptionConfig` already
 * are, not something a section row has its own value for.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.15.0
 */
class m261024_130000_add_llms_txt_fields_to_seo_data extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function safeUp(): bool {
        if (!$this->db->columnExists(Table::SEODATA, 'llmsTxtTitle')) {
            $this->addColumn(Table::SEODATA, 'llmsTxtTitle', $this->string()->after('sitemapEnabled'));
        }

        if (!$this->db->columnExists(Table::SEODATA, 'llmsTxtSummary')) {
            $this->addColumn(Table::SEODATA, 'llmsTxtSummary', $this->text()->after('llmsTxtTitle'));
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function safeDown(): bool {
        $this->dropColumn(Table::SEODATA, 'llmsTxtSummary');
        $this->dropColumn(Table::SEODATA, 'llmsTxtTitle');

        return true;
    }
}
