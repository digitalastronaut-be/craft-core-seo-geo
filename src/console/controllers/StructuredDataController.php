<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\console\controllers;

use craft\console\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Class StructuredDataController
 *
 * Deploy-time/manual backfill for `coreseogeo_structured_data`: real-time sync
 * (`StructuredDataMappingService::syncEntry()`) keeps an entry type's rows current as its
 * entries are saved one at a time, and a mapping save queues a targeted resync of its own entry
 * type, but neither covers entries that existed before this feature shipped, or an install
 * where the queue hasn't run yet. `php craft core-seo-geo/structured-data/sync` runs that same
 * reconciliation for every entry type with at least one mapping, synchronously and with progress
 * output, so a deploy script (or a developer) can force every entry current on demand.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.4.0
 */
class StructuredDataController extends Controller {
    /**
     * @param string|null $entryType limit the sync to a single entry type's handle; every entry
     * type with at least one mapping otherwise
     * @return int
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function actionSync(?string $entryType = null): int {
        $mappings = CoreSeoGeo::getInstance()->getStructuredDataMappings();
        $entryTypeHandles = $entryType !== null ? [$entryType] : $mappings->getMappedEntryTypeHandles();

        if ($entryTypeHandles === []) {
            $this->stdout('No entry types have an active structured data mapping.' . PHP_EOL, Console::FG_YELLOW);
            return ExitCode::OK;
        }

        foreach ($entryTypeHandles as $entryTypeHandle) {
            $this->stdout("Syncing \"{$entryTypeHandle}\"... ");

            $synced = $mappings->syncEntryType($entryTypeHandle);

            $this->stdout("{$synced} structured data elements synced." . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
