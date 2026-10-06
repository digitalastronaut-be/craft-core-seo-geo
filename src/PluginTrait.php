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
use craft\events\TemplateEvent;
use craft\services\Fields;
use craft\web\View;

use yii\base\Event;

use digitalastronaut\craftcoreseogeo\fields\SeoField;
use digitalastronaut\craftcoreseogeo\fields\StructuredDataField;
use digitalastronaut\craftcoreseogeo\web\assets\admin\AdminAssetBundle;

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
    }

    /**
     * @return void
     */
    protected function registerSharedEvents(): void {
        $this->registerFieldTypes();
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
     */
    protected function registerCpEvents(): void {
        $this->registerCpAssets();
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
            }
        );
    }
}
