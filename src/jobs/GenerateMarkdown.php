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
use craft\elements\Entry;
use craft\queue\BaseJob;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class GenerateMarkdown
 *
 * Regenerates and caches one entry's Markdown representation. Pushed by
 * `MarkdownService::queueRefresh()` rather than run inline on save, since generation fetches
 * the entry's own front-end URL over HTTP - too slow (and, on a single-worker web server, liable
 * to deadlock against the save request itself) to do inside a save request. Re-fetches the
 * entry itself rather than having it passed in, since job properties are serialized onto the
 * queue and must stay plain scalars.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class GenerateMarkdown extends BaseJob {
    /**
     * @since v1.0.6
     */
    public int $elementId;
    public int $siteId;

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function execute($queue): void {
        $entry = Craft::$app->getElements()->getElementById($this->elementId, Entry::class, $this->siteId, [
            'status' => null,
        ]);

        if ($entry === null) return;

        CoreSeoGeo::getInstance()->getMarkdown()->refresh($entry);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    protected function defaultDescription(): ?string {
        return Craft::t('core-seo-geo', 'Generating Markdown');
    }
}
