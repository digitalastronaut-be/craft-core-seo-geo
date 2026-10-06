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
}
