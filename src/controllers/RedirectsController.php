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
use craft\helpers\Html;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\records\Redirect;

use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class RedirectsController
 *
 * The "Redirects" CP page: an index listing every `coreseogeo_redirects` row, an add/edit
 * page for a single one, the `coreseogeo_404s` log listing, and delete actions for both
 * tables. See `redirects-roadmap.md` Step 4.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.16.0
 */
class RedirectsController extends Controller {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function beforeAction($action): bool {
        $this->requireAdmin(false);

        return parent::beforeAction($action);
    }

    /**
     * The "Redirects" tab.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function actionIndex(): Response {
        $redirects = CoreSeoGeo::getInstance()->getRedirects()->getAllRedirects();

        return $this->renderTemplate('core-seo-geo/redirects/_index.twig', [
            'redirects' => $redirects,
            'enabledStatusHtmlById' => $this->_enabledStatusHtmlById($redirects),
        ]);
    }

    /**
     * The "404s" tab.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function actionNotFoundLog(): Response {
        return $this->renderTemplate('core-seo-geo/redirects/_not-found-log.twig', [
            'entries' => CoreSeoGeo::getInstance()->getRedirects()->getAllNotFoundLogEntries(),
        ]);
    }

    /**
     * The "Add redirect"/"Edit redirect" page - a new, unsaved `Redirect` record when `$id`
     * is `null` (the `.../new` route), the existing one otherwise.
     *
     * On the `.../new` route, a `source` query param pre-fills `sourceUri` - the hand-off
     * from the 404 log tab's "Create redirect" link (`redirects-roadmap.md` Step 4), so a
     * site owner doesn't have to retype a URI they're already looking at.
     *
     * `$redirect` is never posted directly - it's only ever populated by Craft re-dispatching
     * here after a failed `actionSave()` sets it via `Craft::$app->getUrlManager()->setRouteParams()`,
     * the same way `TrackingScriptsController::actionEdit()`'s `$trackingScript` parameter works.
     * Without it, a validation failure would silently fall back to a brand new, empty record
     * here instead of redisplaying what the user actually typed.
     *
     * @param int|null $id
     * @param Redirect|null $redirect the failed save's record, if we're re-rendering after one
     * @return Response
     * @throws NotFoundHttpException if a non-null `$id` doesn't match an existing row
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function actionEdit(?int $id = null, ?Redirect $redirect = null): Response {
        if ($redirect === null) {
            $redirect = $id !== null
                ? CoreSeoGeo::getInstance()->getRedirects()->getRedirectById($id)
                : new Redirect();
        }

        if ($redirect === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No redirect exists with that ID.'));
        }

        if ($id === null && $redirect->sourceUri === null) {
            $redirect->sourceUri = Craft::$app->getRequest()->getQueryParam('source');
        }

        $isMultiSite = Craft::$app->getIsMultiSite();

        return $this->renderTemplate('core-seo-geo/redirects/_edit.twig', [
            'redirect' => $redirect,
            'matchTypeOptions' => Redirect::getMatchTypeOptions(),
            'statusCodeOptions' => Redirect::getStatusCodeOptions(),
            'isMultiSite' => $isMultiSite,
            'siteOptions' => $isMultiSite ? $this->_siteOptions() : [],
            'metadataHtml' => $redirect->id ? $this->_metadataHtml($redirect) : null,
        ]);
    }

    /**
     * Persists the redirect posted from `_edit.twig`.
     *
     * @return Response|null
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function actionSave(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('id');

        $redirect = $id
            ? CoreSeoGeo::getInstance()->getRedirects()->getRedirectById((int)$id)
            : new Redirect();

        if ($redirect === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No redirect exists with that ID.'));
        }

        $siteId = $request->getBodyParam('siteId');

        $saved = CoreSeoGeo::getInstance()->getRedirects()->saveRedirect(
            redirect: $redirect,
            sourceUri: (string)$request->getBodyParam('sourceUri', ''),
            matchType: (string)$request->getBodyParam('matchType', Redirect::MATCH_TYPE_EXACT),
            destinationUri: $request->getBodyParam('destinationUri'),
            statusCode: (int)$request->getBodyParam('statusCode', Redirect::STATUS_CODE_MOVED_PERMANENTLY),
            enabled: (bool)$request->getBodyParam('enabled', false),
            siteId: $siteId !== null && $siteId !== '' ? (int)$siteId : null,
        );

        if (!$saved) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the redirect."));

            // Sends the posted (but unsaved) record back to actionEdit() for this same URL,
            // so the user's input isn't silently lost when Craft falls back to re-rendering it.
            Craft::$app->getUrlManager()->setRouteParams(['redirect' => $redirect]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'Redirect saved.'));

        return $this->redirectToPostedUrl($redirect);
    }

    /**
     * Deletes one redirect, posted from the delete button in `_index.twig`'s table. Ajax-only
     * (the button posts via `Craft.sendActionRequest()`), so this relies on `asSuccess()`/
     * `asFailure()` for their JSON responses rather than the flash+redirect pattern
     * `actionSave()` uses.
     *
     * @return Response
     * @throws NotFoundHttpException if the posted `id` doesn't match an existing row
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function actionDelete(): Response {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');
        $redirect = CoreSeoGeo::getInstance()->getRedirects()->getRedirectById($id);

        if ($redirect === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No redirect exists with that ID.'));
        }

        if (!CoreSeoGeo::getInstance()->getRedirects()->deleteRedirect($redirect)) {
            return $this->asFailure(Craft::t('core-seo-geo', "Couldn't delete the redirect."));
        }

        return $this->asSuccess(Craft::t('core-seo-geo', 'Redirect deleted.'));
    }

    /**
     * Deletes one `coreseogeo_404s` entry, posted from the delete button in
     * `_not-found-log.twig`'s table. Same ajax-only shape as `actionDelete()`.
     *
     * @return Response
     * @throws NotFoundHttpException if the posted `id` doesn't match an existing row
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function actionDeleteNotFoundLogEntry(): Response {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');
        $entry = CoreSeoGeo::getInstance()->getRedirects()->getNotFoundLogEntryById($id);

        if ($entry === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No log entry exists with that ID.'));
        }

        if (!CoreSeoGeo::getInstance()->getRedirects()->deleteNotFoundLogEntry($entry)) {
            return $this->asFailure(Craft::t('core-seo-geo', "Couldn't delete the log entry."));
        }

        return $this->asSuccess(Craft::t('core-seo-geo', 'Log entry deleted.'));
    }

    // Private Methods
    // =========================================================================

    /**
     * Builds `_index.twig`'s "Enabled" column, keyed by row ID - the same teal+check/gray+xmark
     * status label shape `TrackingScriptsController::_enabledStatusHtmlById()` uses for its own
     * on/off states.
     *
     * @param Redirect[] $redirects
     * @return array<int, string>
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _enabledStatusHtmlById(array $redirects): array {
        $html = [];

        foreach ($redirects as $redirect) {
            $html[$redirect->id] = Cp::statusLabelHtml([
                'color' => $redirect->enabled ? Color::Teal : Color::Gray,
                'label' => $redirect->enabled ? Craft::t('core-seo-geo', 'Enabled') : Craft::t('core-seo-geo', 'Disabled'),
                'icon' => $redirect->enabled ? 'check' : 'xmark',
            ]);
        }

        return $html;
    }

    /**
     * `_edit.twig`'s "Site" select options - `''` for "All sites" (posted back as `siteId =
     * null`) plus every configured site.
     *
     * @return array<int, array{label: string, value: string|int}>
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _siteOptions(): array {
        $options = [
            ['label' => Craft::t('core-seo-geo', 'All sites'), 'value' => ''],
        ];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $options[] = ['label' => $site->getName(), 'value' => $site->id];
        }

        return $options;
    }

    /**
     * The "Details" sidebar content for an existing redirect: hit count, last hit, the entry
     * that triggered it when `sourceElementId` is set, and its created/updated dates, so a
     * site owner can tell at a glance which redirects they added themselves versus which were
     * auto-created.
     *
     * @param Redirect $redirect
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _metadataHtml(Redirect $redirect): string {
        $rows = [
            Craft::t('core-seo-geo', 'Hits') => (string)$redirect->hitCount,
            Craft::t('core-seo-geo', 'Last hit') => $redirect->dateLastHit
                ? Craft::$app->getFormatter()->asRelativeTime($redirect->dateLastHit)
                : Craft::t('core-seo-geo', 'Never'),
        ];

        if ($redirect->sourceElementId !== null) {
            $element = Craft::$app->getElements()->getElementById($redirect->sourceElementId);

            $rows[Craft::t('core-seo-geo', 'Auto-created from')] = $element !== null
                ? Html::a(Html::encode((string)$element), $element->getCpEditUrl())
                : Craft::t('core-seo-geo', 'Deleted entry');
        }

        $formatter = Craft::$app->getFormatter();
        $rows[Craft::t('core-seo-geo', 'Date created')] = $formatter->asDatetime($redirect->dateCreated, 'short', true);
        $rows[Craft::t('core-seo-geo', 'Date updated')] = $formatter->asDatetime($redirect->dateUpdated, 'short', true);

        return Cp::metadataHtml($rows);
    }
}
