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
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\TemplateEvent;
use craft\helpers\Json;
use craft\services\Elements;
use craft\services\Fields;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;

use yii\base\Event;

use digitalastronaut\craftcoreseogeo\elements\StructuredData;
use digitalastronaut\craftcoreseogeo\fields\SeoField;
use digitalastronaut\craftcoreseogeo\fields\StructuredDataField;
use digitalastronaut\craftcoreseogeo\variables\CoreSeoGeoVariable;
use digitalastronaut\craftcoreseogeo\web\assets\admin\AdminAssetBundle;
use digitalastronaut\craftcoreseogeo\web\assets\schema\SchemaAssetBundle;

/**
 * Class PluginTrait
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
trait PluginTrait {
    /**
     * @return void
     */
    protected function registerEvents(): void {
        $this->registerSharedEvents();

        if (Craft::$app->request->isCpRequest) $this->registerCpEvents();
        if (Craft::$app->request->isConsoleRequest) $this->controllerNamespace = 'digitalastronaut\\craftcoreseogeo\\console\\controllers';
    }

    /**
     * @return void
     */
    protected function registerSharedEvents(): void {
        $this->registerFieldTypes();
        $this->registerElementTypes();
        $this->registerTemplateVariable();
    }

    /**
     * @return void
     */
    protected function registerFieldTypes(): void {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = SeoField::class;
                $event->types[] = StructuredDataField::class;
            }
        );
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function registerElementTypes(): void {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = StructuredData::class;
            }
        );
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function registerTemplateVariable(): void {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('coreSeoGeo', CoreSeoGeoVariable::class);
            }
        );
    }

    /**
     * @return void
     */
    protected function registerCpEvents(): void {
        $this->registerCpAssets();
        $this->registerCpUrlRules();
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function registerCpUrlRules(): void {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['core-seo-geo'] = ['template' => 'core-seo-geo/structured-data/_index.twig'];
                $event->rules['core-seo-geo/structured-data'] = ['template' => 'core-seo-geo/structured-data/_index.twig'];
                $event->rules['core-seo-geo/structured-data/<elementId:\d+>'] = 'elements/edit';

                $event->rules['core-seo-geo/sitemap'] = ['template' => 'core-seo-geo/sitemap/_index.twig'];
                $event->rules['core-seo-geo/redirects'] = ['template' => 'core-seo-geo/redirects/_index.twig'];

                $event->rules['core-seo-geo/settings'] = ['template' => 'core-seo-geo/settings/global-seo.twig'];
                $event->rules['core-seo-geo/settings/global-seo'] = ['template' => 'core-seo-geo/settings/global-seo.twig'];
                $event->rules['core-seo-geo/settings/global-geo'] = ['template' => 'core-seo-geo/settings/global-geo.twig'];
                $event->rules['core-seo-geo/settings/tracking-scripts'] = ['template' => 'core-seo-geo/settings/tracking-scripts.twig'];
                $event->rules['core-seo-geo/settings/plugin'] = ['template' => 'core-seo-geo/settings/plugin.twig'];
                $event->rules['core-seo-geo/settings/structured-data'] = ['template' => 'core-seo-geo/settings/structured-data.twig'];
            }
        );
    }

    /**
     * @return void
     */
    protected function registerCpAssets(): void {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event) {
                $view = Craft::$app->getView();
                $view->registerAssetBundle(AdminAssetBundle::class);

                CoreSeoGeo::getInstance()->getVite()->register('src/web/assets/admin/admin.js');

                $this->registerSchemaRegistryAsset($view);
            }
        );
    }

    /**
     * @param View $view
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function registerSchemaRegistryAsset(View $view): void {
        $bundle = $view->registerAssetBundle(SchemaAssetBundle::class);

        $view->registerJs(
            'window.CoreSeoGeoSchemaRegistryUrl = ' . Json::encode("{$bundle->baseUrl}/registry.json", JSON_UNESCAPED_SLASHES) . ';',
            View::POS_HEAD,
        );
    }
}
