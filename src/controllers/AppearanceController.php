<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\controllers;

use Craft;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

use yii\web\Response;

/**
 * Class AppearanceController
 *
 * The "Appearance" settings page (`core-seo-geo/settings/appearance`) - currently just
 * `Settings::$pluginName`, the override for this plugin's own top-level CP nav label.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.16.0
 */
class AppearanceController extends Controller {
    /**
     * @inheritdoc
     *
     * @since v1.16.0
     */
    public function beforeAction($action): bool {
        $this->requireAdmin(false);

        return parent::beforeAction($action);
    }

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function actionEdit(): Response {
        return $this->renderTemplate('core-seo-geo/settings/appearance.twig', [
            'settings' => CoreSeoGeo::getInstance()->getSettings(),
        ]);
    }

    /**
     * Persists whatever `settings[...]` fields were posted from `appearance.twig`.
     *
     * @return Response|null
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function actionSave(): ?Response {
        $this->requirePostRequest();

        $posted = Craft::$app->getRequest()->getBodyParam('settings', []);
        $saved = CoreSeoGeo::getInstance()->savePartialSettings(is_array($posted) ? $posted : []);

        if (!$saved) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the settings."));

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
