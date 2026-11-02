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
    public const string STRUCTUREDDATA = '{{%coreseogeo_structured_data}}';

    /**
     * @since v1.0.4
     */
    public const string SEOSCORE = '{{%coreseogeo_seo_scores}}';

    /**
     * @since v1.1.0
     */
    public const string SEODATA = '{{%coreseogeo_seo_data}}';

    /**
     * @since v1.3.0
     */
    public const string STRUCTUREDDATAMAPPINGS = '{{%coreseogeo_structured_data_mappings}}';

    /**
     * @since v1.0.9
     */
    public const string TRACKINGSCRIPTS = '{{%coreseogeo_tracking_scripts}}';

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
     * @since v1.20.0
     */
    public const string METRICS = '{{%coreseogeo_metrics}}';

    /**
     * @since v1.32.0
     */
    public const string TXTFILECONTENT = '{{%coreseogeo_txt_file_content}}';
}
