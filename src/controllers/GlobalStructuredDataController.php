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
use craft\base\Model;
use craft\helpers\Cp;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class GlobalStructuredDataController
 *
 * The settings pages for the plugin's built-in, site-wide structured data objects -
 * Organization, WebSite, LocalBusiness, Person - reachable as sub-pages of "Global"
 * (`core-seo-geo/settings/global/<slug>`, `<slug>` being one of
 * `GlobalStructuredDataService::TYPE_SLUGS`). Unlike `ContentSeoController`'s mapping editor,
 * there's nothing configurable about *how* a page's fields become JSON-LD - only the field
 * values themselves - so a single generic edit/save pair of actions covers all four types,
 * with `GlobalStructuredDataService` supplying the type-specific model and `global-<slug>.twig`
 * supplying the type-specific fields.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class GlobalStructuredDataController extends Controller {
    /**
     * @inheritdoc
     *
     * @since v1.5.0
     */
    public function beforeAction($action): bool {
        $this->requireAdmin(false);

        return parent::beforeAction($action);
    }

    /**
     * The settings page for one built-in type, on the currently-selected site.
     *
     * @param string $type the URL slug, e.g. `organization` - see `GlobalStructuredDataService::TYPE_SLUGS`
     * @return Response
     * @throws ForbiddenHttpException if the current user can't edit any site
     * @throws NotFoundHttpException if `$type` isn't a recognized slug
     *
     * @since v1.5.0
     */
    public function actionEdit(string $type): Response {
        $site = Cp::requestedSite();

        if ($site === null) {
            throw new ForbiddenHttpException(Craft::t('core-seo-geo', "You don't have permission to edit any sites."));
        }

        $service = CoreSeoGeo::getInstance()->getGlobalStructuredData();
        $structuredType = $service->getTypeForSlug($type);

        if ($structuredType === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No structured data type exists with the slug "{slug}".', [
                'slug' => $type,
            ]));
        }

        return $this->renderTemplate("core-seo-geo/settings/global-{$type}.twig", [
            'site' => $site,
            'model' => $service->getModel($structuredType, $site->id),
            'addressFieldHtml' => $service->getAddressFieldHtml($structuredType, $site->id),
        ]);
    }

    /**
     * Persists the model posted from one of the `global-<slug>.twig` pages. Posted fields are
     * namespaced under `settings`, matching every other page in this plugin's own CP nav.
     *
     * @return Response|null
     * @throws BadRequestHttpException if `type`/`siteId` wasn't posted, `type` isn't a
     * recognized slug, or `siteId` doesn't match a real site
     *
     * @since v1.5.0
     */
    public function actionSave(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $service = CoreSeoGeo::getInstance()->getGlobalStructuredData();

        $type = (string)$request->getRequiredBodyParam('type');
        $structuredType = $service->getTypeForSlug($type);

        if ($structuredType === null) {
            throw new BadRequestHttpException("Unknown structured data type \"{$type}\".");
        }

        $siteId = (int)$request->getRequiredBodyParam('siteId');

        if (Craft::$app->getSites()->getSiteById($siteId) === null) {
            throw new BadRequestHttpException("No site exists with the ID \"{$siteId}\".");
        }

        $model = $service->getModel($structuredType, $siteId);

        Craft::configure($model, $this->_normalizeArrayAttributes($model, $this->_arrayBodyParam('settings')));

        if (!$model->validate() || !$service->save($structuredType, $siteId, $model)) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the {type} settings.", [
                'type' => $service->getTypeLabel($structuredType),
            ]));

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', '{type} settings saved.', [
            'type' => $service->getTypeLabel($structuredType),
        ]));

        return $this->redirectToPostedUrl();
    }

    /**
     * `editableTableField` pairs its real input with a same-named hidden fallback, so a table
     * with every row deleted still posts something - as an empty string rather than an empty
     * array. Normalizes that back to `[]` for whichever of `$model`'s properties are actually
     * typed `array`, so `Craft::configure()` doesn't choke assigning a string to one of them.
     *
     * @param Model $model
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @since v1.5.0
     */
    private function _normalizeArrayAttributes(Model $model, array $data): array {
        $reflection = new \ReflectionClass($model);

        foreach ($data as $name => $value) {
            if ($value !== '' || !$reflection->hasProperty($name)) continue;

            $type = $reflection->getProperty($name)->getType();

            if ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                $data[$name] = [];
            }
        }

        return $data;
    }

    /**
     * Craft's `multiselect`/`editableTable` inputs pair their real field with a same-named
     * hidden fallback input, so the field still posts something when nothing's selected/no rows
     * exist - as an empty string rather than an empty array. Normalizes that back to `[]`.
     *
     * @param string $name
     * @return array
     *
     * @since v1.5.0
     */
    private function _arrayBodyParam(string $name): array {
        $value = Craft::$app->getRequest()->getBodyParam($name, []);

        return is_array($value) ? $value : [];
    }
}
