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
 * Everything to do with the plugin's generated text files - robots.txt, humans.txt, ads.txt,
 * security.txt, trust.txt, llms.txt, llms-full.txt: both their CP settings pages
 * (`core-seo-geo/settings/txt/<slug>`, `<slug>` being one of `TxtFilesService::TYPE_SLUGS`) and
 * the public front-end actions that actually serve each file. The five settings pages have
 * nothing in common field-wise, so each gets its own template (`txt-<slug>.twig`), but a single
 * edit/save action still covers all of them - same reasoning as `GlobalStructuredDataController`
 * for its four structured data types. The serving actions are kept on this same controller
 * rather than split out per file, since each one is just a one-liner calling `TxtFilesService`
 * and setting a content type - not enough distinct behavior to earn a controller of its own.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.10.0
 */
class TxtFilesController extends Controller {
    /**
     * @var array<int, string> the public, anonymous, front-end serving actions - every other
     * action on this controller is a CP settings page and requires an admin.
     */
    private const array SERVING_ACTIONS = ['robots', 'humans', 'ads', 'security', 'trust', 'llms', 'llms-full'];

    /**
     * @inheritdoc
     *
     * @since v1.10.0
     */
    protected array|bool|int $allowAnonymous = self::SERVING_ACTIONS;

    /**
     * @inheritdoc
     *
     * @since v1.10.0
     */
    public function beforeAction($action): bool {
        if (!\in_array($action->id, self::SERVING_ACTIONS, true)) {
            $this->requireAdmin(false);
        }

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
     * Serves `robots.txt`.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    public function actionRobots(): Response {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $txt = CoreSeoGeo::getInstance()->getTxtFiles()->getRobotsTxt($siteId);

        $this->response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($txt);
    }

    /**
     * Serves `humans.txt`.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.11.0
     */
    public function actionHumans(): Response {
        $txt = CoreSeoGeo::getInstance()->getTxtFiles()->getHumansTxt();

        $this->response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($txt);
    }

    /**
     * Serves `ads.txt`.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.12.0
     */
    public function actionAds(): Response {
        $txt = CoreSeoGeo::getInstance()->getTxtFiles()->getAdsTxt();

        $this->response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($txt);
    }

    /**
     * Serves `security.txt`, at both the location RFC 9116 prefers and the root location kept
     * for backwards compatibility - see `PluginTrait::registerSiteUrlRules()`.
     *
     * @return Response
     * @throws NotFoundHttpException if the required `Contact`/`Expires` fields aren't both set
     * - see `TxtFilesService::getSecurityTxt()`
     *
     * @author      Digitalastronaut
     * @since       v1.13.0
     */
    public function actionSecurity(): Response {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $txt = CoreSeoGeo::getInstance()->getTxtFiles()->getSecurityTxt($siteId);

        if ($txt === '') {
            throw new NotFoundHttpException('security.txt is missing its required Contact/Expires fields.');
        }

        $this->response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($txt);
    }

    /**
     * Serves `trust.txt`.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.14.0
     */
    public function actionTrust(): Response {
        $txt = CoreSeoGeo::getInstance()->getTxtFiles()->getTrustTxt();

        $this->response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');

        return $this->asRaw($txt);
    }

    /**
     * Serves `llms.txt`.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function actionLlms(): Response {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $txt = CoreSeoGeo::getInstance()->getTxtFiles()->getLlmsTxt($siteId);

        $this->response->getHeaders()->set('Content-Type', 'text/markdown; charset=UTF-8');

        return $this->asRaw($txt);
    }

    /**
     * Serves `llms-full.txt`.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function actionLlmsFull(): Response {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $txt = CoreSeoGeo::getInstance()->getTxtFiles()->getLlmsFullTxt($siteId);

        $this->response->getHeaders()->set('Content-Type', 'text/markdown; charset=UTF-8');

        return $this->asRaw($txt);
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
        $txtFiles = CoreSeoGeo::getInstance()->getTxtFiles();

        return match ($type) {
            'robots' => [
                'cpTrigger' => Craft::$app->getConfig()->getGeneral()->cpTrigger,
                'robotsRuleTypeOptions' => Settings::getRobotsRuleTypeOptions(),
                'knownAiBotOptions' => array_map(
                    static fn(string $bot): array => ['label' => $bot, 'value' => $bot],
                    CoreSeoGeo::getInstance()->getBotDetection()->getKnownBots(),
                ),
                'robotsTxtPreview' => $txtFiles->getRobotsTxt($site->id),
            ],
            'humans' => [
                'humansTxtPreview' => $txtFiles->getHumansTxt(),
            ],
            'ads' => [
                'adsTxtRelationshipOptions' => Settings::getAdsTxtRelationshipOptions(),
                'adsTxtPreview' => $txtFiles->getAdsTxt(),
            ],
            'security' => [
                'securityTxtPreview' => $txtFiles->getSecurityTxt($site->id),
            ],
            'trust' => [
                'trustTxtDataTrainingAllowedOptions' => Settings::getTrustTxtDataTrainingAllowedOptions(),
                'trustTxtPreview' => $txtFiles->getTrustTxt(),
            ],
            'llms' => [
                'llmsRow' => CoreSeoGeo::getInstance()->getContentSeo()->getGlobalRow($site->id),
                'llmsTxtPreview' => $txtFiles->getLlmsTxt($site->id),
            ],
            'llms-full' => [
                'llmsFullTxtPreview' => $txtFiles->getLlmsFullTxt($site->id),
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
