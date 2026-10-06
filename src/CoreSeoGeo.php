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
use craft\helpers\UrlHelper;

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

    public string $schemaVersion = '1.0.1';
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

        $item['label'] = Craft::t('core-seo-geo', 'SEO/GEO');
        $item['url'] = 'structured-data';
        $item['icon'] = '@digitalastronaut/craftcoreseogeo/web/assets/icons/seo-geo-field-icon.svg';
        $item['subnav'] = [
            'structured-data' => [
                'label' => Craft::t('core-seo-geo', 'Structured Data'),
                'url' => 'structured-data',
            ],
            'settings' => [
                'label' => Craft::t('core-seo-geo', 'Settings'),
                'url' => UrlHelper::cpUrl('settings/plugins/core-seo-geo'),
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
     * @return string|null
     */
    protected function settingsHtml(): ?string {
        return Craft::$app->view->renderTemplate('core-seo-geo/pages/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }
}
