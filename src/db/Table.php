<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\db;

/**
 * Class Table
 *
 * Database table name constants, the same way `craft\db\Table` holds Craft's own.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
abstract class Table {
    /**
     * @since v1.0.0
     */
    public const string STRUCTUREDDATA = '{{%coreseogeo_structureddata}}';

    /**
     * @since v1.0.4
     */
    public const string SEOSCORE = '{{%coreseogeo_seoscores}}';

    /**
     * @since v1.1.0
     */
    public const string SEODATA = '{{%coreseogeo_seodata}}';

    /**
     * @since v1.3.0
     */
    public const string STRUCTUREDDATAMAPPINGS = '{{%coreseogeo_structureddatamappings}}';

    /**
     * @since v1.0.9
     */
    public const string TRACKINGSCRIPTS = '{{%coreseogeo_trackingscripts}}';

    /**
     * @since v1.16.0
     */
    public const string REDIRECTS = '{{%coreseogeo_redirects}}';

    /**
     * @since v1.16.0
     */
    public const string NOTFOUNDLOG = '{{%coreseogeo_404s}}';

    /**
     * @since v1.19.0
     */
    public const string REDIRECTSITES = '{{%coreseogeo_redirect_sites}}';

    /**
     * Retained only for `m261024_150000_add_404_hits_table`/`m261024_160000_add_redirect_
     * hits_table` (old, immutable migration history) to still reference - the table itself no
     * longer exists on a fresh install (see `Install.php`) or after `m261026_100000_add_
     * metrics_table` has run, which replaces it with `METRICS`.
     *
     * @since v1.17.0
     */
    public const string NOTFOUNDHITS = '{{%coreseogeo_404_hits}}';

    /**
     * @see self::NOTFOUNDHITS
     * @since v1.17.0
     */
    public const string REDIRECTHITS = '{{%coreseogeo_redirect_hits}}';

    /**
     * @since v1.20.0
     */
    public const string METRICS = '{{%coreseogeo_metrics}}';
}
