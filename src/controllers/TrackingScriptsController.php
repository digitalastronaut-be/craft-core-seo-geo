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
use craft\enums\Color;
use craft\helpers\Cp;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\records\TrackingScript;

use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class TrackingScriptsController
 *
 * The "Tracking Scripts" settings page: an index listing every `coreseogeo_trackingscripts` row,
 * and an add/edit page for a single one.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.9
 */
class TrackingScriptsController extends Controller {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function beforeAction($action): bool {
        $this->requireAdmin(false);

        return parent::beforeAction($action);
    }

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function actionIndex(): Response {
        $trackingScripts = CoreSeoGeo::getInstance()->getTrackingScripts()->getAllTrackingScripts();

        return $this->renderTemplate('core-seo-geo/settings/tracking-scripts.twig', [
            'trackingScripts' => $trackingScripts,
            'enabledStatusHtmlById' => $this->_enabledStatusHtmlById($trackingScripts),
        ]);
    }

    /**
     * The "Add tracking script"/"Edit tracking script" page - a new, unsaved `TrackingScript`
     * record when `$id` is `null` (the `.../new` route), the existing one otherwise.
     *
     * `$trackingScript` is never posted directly - it's only ever populated by Craft re-dispatching
     * here after a failed `actionSave()` sets it via `Craft::$app->getUrlManager()->setRouteParams()`,
     * the same way `GlobalsController::actionEditContent()`'s `$globalSet` parameter works. Without
     * it, a validation failure would silently fall back to a brand new, empty record here instead
     * of redisplaying what the user actually typed.
     *
     * @param int|null $id
     * @param TrackingScript|null $trackingScript the failed save's record, if we're re-rendering after one
     * @return Response
     * @throws NotFoundHttpException if a non-null `$id` doesn't match an existing row
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function actionEdit(?int $id = null, ?TrackingScript $trackingScript = null): Response {
        if ($trackingScript === null) {
            $trackingScript = $id !== null
                ? CoreSeoGeo::getInstance()->getTrackingScripts()->getTrackingScriptById($id)
                : new TrackingScript();
        }

        if ($trackingScript === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No tracking script exists with that ID.'));
        }

        return $this->renderTemplate('core-seo-geo/settings/tracking-script-edit.twig', [
            'trackingScript' => $trackingScript,
            'locationOptions' => TrackingScript::getLocationOptions(),
        ]);
    }

    /**
     * Persists the tracking script posted from `tracking-script-edit.twig`.
     *
     * @return Response|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function actionSave(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('id');

        $trackingScript = $id
            ? CoreSeoGeo::getInstance()->getTrackingScripts()->getTrackingScriptById((int)$id)
            : new TrackingScript();

        if ($trackingScript === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No tracking script exists with that ID.'));
        }

        $saved = CoreSeoGeo::getInstance()->getTrackingScripts()->saveTrackingScript(
            trackingScript: $trackingScript,
            name: (string)$request->getBodyParam('name', ''),
            enabled: (bool)$request->getBodyParam('enabled', false),
            scriptTemplate: $request->getBodyParam('scriptTemplate'),
            scriptLocation: (string)$request->getBodyParam('scriptLocation', TrackingScript::LOCATION_HEAD_END),
            bodyScriptTemplate: $request->getBodyParam('bodyScriptTemplate'),
            bodyScriptLocation: (string)$request->getBodyParam('bodyScriptLocation', TrackingScript::LOCATION_BODY_START),
        );

        if (!$saved) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the tracking script."));

            // Sends the posted (but unsaved) record back to actionEdit() for this same URL,
            // so the user's input isn't silently lost when Craft falls back to re-rendering it.
            Craft::$app->getUrlManager()->setRouteParams(['trackingScript' => $trackingScript]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'Tracking script saved.'));

        return $this->redirectToPostedUrl($trackingScript);
    }

    /**
     * Deletes one tracking script, posted from the delete button in `tracking-scripts.twig`'s
     * table. Ajax-only (the button posts via `Craft.sendActionRequest()`), so this relies on
     * `asSuccess()`/`asFailure()` for their JSON responses rather than the flash+redirect pattern
     * the rest of this controller uses.
     *
     * @return Response
     * @throws NotFoundHttpException if the posted `id` doesn't match an existing row
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function actionDelete(): Response {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');
        $trackingScript = CoreSeoGeo::getInstance()->getTrackingScripts()->getTrackingScriptById($id);

        if ($trackingScript === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No tracking script exists with that ID.'));
        }

        if (!CoreSeoGeo::getInstance()->getTrackingScripts()->deleteTrackingScript($trackingScript)) {
            return $this->asFailure(Craft::t('core-seo-geo', "Couldn't delete the tracking script."));
        }

        return $this->asSuccess(Craft::t('core-seo-geo', 'Tracking script deleted.'));
    }

    /**
     * Builds `tracking-scripts.twig`'s "Enabled" column, keyed by row ID - the same
     * teal+check/gray+xmark status label shape `ContentSeoController::_onOffStatusHtml()` uses
     * for its own on/off states.
     *
     * @param TrackingScript[] $trackingScripts
     * @return array<int, string>
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    private function _enabledStatusHtmlById(array $trackingScripts): array {
        $html = [];

        foreach ($trackingScripts as $trackingScript) {
            $html[$trackingScript->id] = Cp::statusLabelHtml([
                'color' => $trackingScript->enabled ? Color::Teal : Color::Gray,
                'label' => $trackingScript->enabled ? Craft::t('core-seo-geo', 'Enabled') : Craft::t('core-seo-geo', 'Disabled'),
                'icon' => $trackingScript->enabled ? 'check' : 'xmark',
            ]);
        }

        return $html;
    }
}
