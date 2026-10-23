<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\jobs;

use Craft;

use craft\queue\BaseJob;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class SyncStructuredDataMappings
 *
 * Recomputes a `StructuredData` row for every entry in `$sectionHandle` against every mapping
 * currently configured for it - pushed by `StructuredDataMappingService::saveMapping()` so a
 * newly added/edited mapping backfills the section's existing entries, rather than only taking
 * effect the next time each entry happens to be resaved. Queued (not run inline) since a section
 * can have many entries across many sites, each needing its own Twig render per mapping.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.4.0
 */
class SyncStructuredDataMappings extends BaseJob {
    /**
     * @since v1.4.0
     */
    public string $sectionHandle;

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function execute($queue): void {
        CoreSeoGeo::getInstance()->getStructuredDataMappings()->syncSection($this->sectionHandle, function(int $step, int $total) use ($queue) {
            $this->setProgress($queue, $total > 0 ? $step / $total : 1);
        });
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    protected function defaultDescription(): ?string {
        return Craft::t('core-seo-geo', 'Syncing structured data for "{section}"', [
            'section' => $this->sectionHandle,
        ]);
    }
}
