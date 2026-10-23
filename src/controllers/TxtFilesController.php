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
use craft\helpers\Cp;
use craft\models\Site;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\models\Settings;
use digitalastronaut\craftcoreseogeo\services\TxtFilesService;

use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class TxtFilesController
 *
 * The settings pages for the plugin's generated text files - robots.txt, humans.txt, ads.txt,
 * llms.txt, llms-full.txt - reachable as sub-pages of "Global"
 * (`core-seo-geo/settings/txt/<slug>`, `<slug>` being one of `TxtFilesService::TYPE_SLUGS`).
 * The five have nothing in common field-wise, so each gets its own template
 * (`txt-<slug>.twig`), but a single edit action still covers all five - same reasoning as
 * `GlobalStructuredDataController` for its four structured data types.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.10.0
 */
class TxtFilesController extends Controller {
    /**
     * @inheritdoc
     *
     * @since v1.10.0
     */
    public function beforeAction($action): bool {
        $this->requireAdmin(false);

        return parent::beforeAction($action);
    }

    /**
     * The settings page for one generated text file, on the currently-selected site.
     *
     * @param string $type the URL slug, e.g. `robots` - see `TxtFilesService::TYPE_SLUGS`
     * @return Response
     * @throws ForbiddenHttpException if the current user can't edit any site
     * @throws NotFoundHttpException if `$type` isn't a recognized slug
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    public function actionEdit(string $type): Response {
        $site = Cp::requestedSite();

        if ($site === null) {
            throw new ForbiddenHttpException(Craft::t('core-seo-geo', "You don't have permission to edit any sites."));
        }

        if (!isset(TxtFilesService::TYPE_SLUGS[$type])) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No text file exists with the slug "{slug}".', [
                'slug' => $type,
            ]));
        }

        return $this->renderTemplate("core-seo-geo/settings/txt-{$type}.twig", [
            'site' => $site,
            'settings' => CoreSeoGeo::getInstance()->getSettings(),
            ...$this->_extraVars($type, $site),
        ]);
    }

    /**
     * Persists whatever `settings[...]` fields were posted from one of the `txt-<slug>.twig`
     * pages. Every one of the five files' settings lives directly on the plugin's own
     * `Settings` model (none of them are per-site or involve a separate record), so there's
     * nothing type-specific about saving - unlike `actionEdit()`'s per-type extra template
     * variables, this one action covers all five without needing to know which page posted to
     * it.
     *
     * @return Response|null
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    public function actionSave(): ?Response {
        $this->requirePostRequest();

        $saved = CoreSeoGeo::getInstance()->savePartialSettings($this->_arrayBodyParam('settings'));

        if (!$saved) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the settings."));

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * The per-type template variables `actionEdit()` needs on top of `settings` - whatever a
     * given page's fields require that isn't just a `Settings` property it can read directly.
     *
     * @param string $type
     * @param Site $site
     * @return array<string, mixed>
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    private function _extraVars(string $type, Site $site): array {
        return match ($type) {
            'robots' => [
                'cpTrigger' => Craft::$app->getConfig()->getGeneral()->cpTrigger,
                'robotsRuleTypeOptions' => Settings::getRobotsRuleTypeOptions(),
                'knownAiBotOptions' => array_map(
                    static fn(string $bot): array => ['label' => $bot, 'value' => $bot],
                    CoreSeoGeo::getInstance()->getBotDetection()->getKnownBots(),
                ),
                'robotsTxtPreview' => CoreSeoGeo::getInstance()->getRobots()->getRobotsTxt($site->id),
            ],
            'humans' => [
                'humansTxtPreview' => CoreSeoGeo::getInstance()->getHumans()->getHumansTxt(),
            ],
            'ads' => [
                'adsTxtRelationshipOptions' => Settings::getAdsTxtRelationshipOptions(),
                'adsTxtPreview' => CoreSeoGeo::getInstance()->getAds()->getAdsTxt(),
            ],
            'security' => [
                'securityTxtPreview' => CoreSeoGeo::getInstance()->getSecurity()->getSecurityTxt($site->id),
            ],
            'trust' => [
                'trustTxtDataTrainingAllowedOptions' => Settings::getTrustTxtDataTrainingAllowedOptions(),
                'trustTxtPreview' => CoreSeoGeo::getInstance()->getTrust()->getTrustTxt(),
            ],
            'llms' => [
                'llmsRow' => CoreSeoGeo::getInstance()->getContentSeo()->getGlobalRow($site->id),
                'llmsTxtPreview' => CoreSeoGeo::getInstance()->getLlms()->getLlmsTxt($site->id),
            ],
            'llms-full' => [
                'llmsFullTxtPreview' => CoreSeoGeo::getInstance()->getLlmsFull()->getLlmsFullTxt($site->id),
            ],
            default => [],
        };
    }

    /**
     * `editableTable`/`multiselect` inputs pair their real field with a same-named hidden
     * fallback, so the field still posts something when nothing's selected/no rows exist - as
     * an empty string rather than an empty array. Normalizes that back to `[]`. Same helper
     * `GlobalStructuredDataController::_arrayBodyParam()` has, kept local rather than shared
     * since there's no base controller for the plugin's own actions to hang it off of.
     *
     * @param string $name
     * @return array
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    private function _arrayBodyParam(string $name): array {
        $value = Craft::$app->getRequest()->getBodyParam($name, []);

        return is_array($value) ? $value : [];
    }
}
