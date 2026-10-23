<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo;

use Craft;
use craft\base\Model;
use craft\base\Plugin;

use digitalastronaut\craftcoreseogeo\models\Settings;
use digitalastronaut\craftcoreseogeo\services\ServicesTrait;

/**
 * Class CoreSeoGeo
 *
 * @method static CoreSeoGeo getInstance()
 * @method Settings getSettings()
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class CoreSeoGeo extends Plugin {
    use ServicesTrait;
    use PluginTrait;

    public string $schemaVersion = '1.0.12';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    /**
     * @return void
     */
    public function init(): void {
        parent::init();

        $this->registerEvents();
    }

    /**
     * @inheritdoc
     *
     * @return array|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getCpNavItem(): ?array {
        $item = parent::getCpNavItem();

        $item['label'] = $this->getSettings()->pluginName ?: Craft::t('core-seo-geo', 'SEO/GEO');
        $item['url'] = 'core-seo-geo';
        $item['icon'] = '@digitalastronaut/craftcoreseogeo/web/assets/icons/seo-geo-field-icon.svg';
        $item['subnav'] = [
            'global-seo' => [
                'label' => Craft::t('core-seo-geo', 'Global'),
                'url' => 'core-seo-geo/settings/global-seo',
            ],
            'content-seo' => [
                'label' => Craft::t('core-seo-geo', 'Content'),
                'url' => 'core-seo-geo/settings/content-seo',
            ],
            'tracking-scripts' => [
                'label' => Craft::t('core-seo-geo', 'Tracking Scripts'),
                'url' => 'core-seo-geo/settings/tracking-scripts',
            ],
            'redirects' => [
                'label' => Craft::t('core-seo-geo', 'Redirects'),
                'url' => 'core-seo-geo/redirects',
            ],
            'structured-data' => [
                'label' => Craft::t('core-seo-geo', 'Structured Data'),
                'url' => 'core-seo-geo/structured-data',
            ],
            'appearance' => [
                'label' => Craft::t('core-seo-geo', 'Appearance'),
                'url' => 'core-seo-geo/settings/appearance',
            ],
        ];

        return $item;
    }

    /**
     * @return Model|null
     */
    protected function createSettingsModel(): ?Model {
        return Craft::createObject(Settings::class);
    }

    /**
     * Saves a *subset* of this plugin's settings - exactly one page's worth of posted fields -
     * without clobbering every other setting that isn't on that page.
     *
     * `craft\services\Plugins::savePluginSettings()` writes `plugins.core-seo-geo.settings` as
     * one whole project config node, built from `toArray(array_keys($settings))` - only the
     * keys actually present in whatever was posted. Since this plugin now has several
     * independent settings pages (`global-seo.twig`, each `txt-<slug>.twig`, ...) that each post
     * only their own fields under the same `settings` namespace, calling it directly with just
     * `$posted` would silently erase every field from every *other* page the next time any one
     * of them is saved - confirmed happening in practice (ads.txt/security.txt/trust.txt all
     * reverted to their defaults after later, unrelated saves). Merging the posted fields over
     * this plugin's *current, full* settings before handing them to Craft keeps every page's
     * save self-contained again, regardless of how many more settings pages this grows to.
     *
     * @param array $posted this one page's posted fields only
     * @return bool whether the save succeeded
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function savePartialSettings(array $posted): bool {
        $full = array_merge($this->getSettings()->toArray(), $posted);

        return Craft::$app->getPlugins()->savePluginSettings($this, $full);
    }

    /**
     * @return string|null
     */
    protected function settingsHtml(): ?string {
        return Craft::$app->view->renderTemplate('core-seo-geo/pages/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }
}
