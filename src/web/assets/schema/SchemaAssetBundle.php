<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\web\assets\schema;

use craft\web\AssetBundle;

/**
 * Class SchemaAssetBundle
 *
 * Publishes `registry.json` (the CP-facing twin of `registry.php`, see
 * `console\controllers\SchemaController`) as a static, content-hashed CP resource - the
 * schema.org vocabulary is read-only and only changes when the plugin itself is updated, so the
 * browser can cache it indefinitely rather than fetching it through a controller action.
 *
 * `sourcePath` points at the whole `static/schemaorg/` folder, but `publishOptions.only`
 * restricts what actually gets published - the vendored CSVs, `VERSION.md`, and `registry.php`
 * have no business being web-accessible CP resources.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class SchemaAssetBundle extends AssetBundle {
    /**
     * @return void
     */
    public function init(): void {
        $this->sourcePath = '@digitalastronaut/craftcoreseogeo/static/schemaorg';
        $this->publishOptions = [
            'only' => ['registry.json'],
        ];

        parent::init();
    }
}
