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
 * Deploy-time/manual backfill for `coreseogeo_structureddata`: real-time sync
 * (`StructuredDataMappingService::syncEntry()`) keeps a section's rows current as its entries
 * are saved one at a time, and a mapping save queues a targeted resync of its own section, but
 * neither covers entries that existed before this feature shipped, or an install where the
 * queue hasn't run yet. `php craft core-seo-geo/structured-data/sync` runs that same
 * reconciliation for every section with at least one mapping, synchronously and with progress
 * output, so a deploy script (or a developer) can force every entry current on demand.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.4.0
 */
class StructuredDataController extends Controller {
    /**
     * @param string|null $section limit the sync to a single section's handle; every section
     * with at least one mapping otherwise
     * @return int
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function actionSync(?string $section = null): int {
        $mappings = CoreSeoGeo::getInstance()->getStructuredDataMappings();
        $sectionHandles = $section !== null ? [$section] : $mappings->getMappedSectionHandles();

        if ($sectionHandles === []) {
            $this->stdout('No sections have an active structured data mapping.' . PHP_EOL, Console::FG_YELLOW);
            return ExitCode::OK;
        }

        foreach ($sectionHandles as $sectionHandle) {
            $this->stdout("Syncing \"{$sectionHandle}\"... ");

            $synced = $mappings->syncSection($sectionHandle);

            $this->stdout("{$synced} structured data elements synced." . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
