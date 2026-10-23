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
use craft\db\Query;
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\db\Table;
use digitalastronaut\craftcoreseogeo\fields\SeoField;
use digitalastronaut\craftcoreseogeo\records\ContentSeo as ContentSeoRecord;

/**
 * Class m261021_130000_migrate_seo_field_settings_to_global_seo_data
 *
 * `SeoField` no longer has `titleFormatRows`/`descriptionFormatRows`/`showCanonicalField`
 * settings of its own - every element with the field now resolves its blocks from
 * `coreseogeo_seodata` instead (section row first, falling back to the site's global bundle row -
 * see `SeoField::_resolveFormatRows()`). Before this, a single field instance's settings applied
 * uniformly to every element using it, regardless of site or section, which is exactly what the
 * global bundle row is for - so this carries whatever was configured on each `SeoField` instance
 * (there's normally only ever one) into every site's global row, keeping existing elements
 * resolving the same meta title/description they did before. Reads the raw `fields.settings`
 * JSON directly rather than instantiating `SeoField` - its constructor now drops those keys (see
 * `SeoField::__construct()`), so the stored config can only be read back as plain data here.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.2.0
 */
class m261021_130000_migrate_seo_field_settings_to_global_seo_data extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.2.0
     */
    public function safeUp(): bool {
        if (!$this->db->tableExists(Table::SEODATA)) {
            return true;
        }

        $fieldRows = (new Query())
            ->select(['uid', 'settings'])
            ->from(['{{%fields}}'])
            ->where(['type' => SeoField::class])
            ->andWhere(['dateDeleted' => null])
            ->all($this->db);

        if ($fieldRows === []) {
            return true;
        }

        $titleConfig = [];
        $descriptionConfig = [];

        foreach ($fieldRows as $fieldRow) {
            $settings = Json::decode((string)$fieldRow['settings']) ?: [];

            if ($titleConfig === [] && !empty($settings['titleFormatRows'])) {
                $titleConfig = $settings['titleFormatRows'];
            }

            if ($descriptionConfig === [] && !empty($settings['descriptionFormatRows'])) {
                $descriptionConfig = $settings['descriptionFormatRows'];
            }
        }

        if ($titleConfig === [] && $descriptionConfig === []) {
            return true;
        }

        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            $record = ContentSeoRecord::findOne(['sectionHandle' => '', 'siteId' => $siteId])
                ?? new ContentSeoRecord(['siteId' => $siteId, 'sectionHandle' => '']);

            if ($titleConfig !== []) $record->titleConfig = Json::encode($titleConfig);
            if ($descriptionConfig !== []) $record->descriptionConfig = Json::encode($descriptionConfig);

            $record->save(false);
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.2.0
     */
    public function safeDown(): bool {
        echo "m261021_130000_migrate_seo_field_settings_to_global_seo_data cannot be reverted.\n";

        return false;
    }
}
