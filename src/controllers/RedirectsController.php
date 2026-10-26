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

use Carbon\Carbon;

use Craft;
use craft\enums\Color;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\models\RedirectMatch;
use digitalastronaut\craftcoreseogeo\records\NotFoundLogEntry;
use digitalastronaut\craftcoreseogeo\records\Redirect;
use digitalastronaut\craftcoreseogeo\services\RedirectsService;

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
        return $this->renderTemplate('core-seo-geo/redirects/_index.twig', [
            'chartData' => $this->_activityChartData('redirects'),
            'chartTitle' => Craft::t('core-seo-geo', 'Redirect activity, last 30 days'),
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
            'chartData' => $this->_activityChartData('notFound'),
            'chartTitle' => Craft::t('core-seo-geo', '404 activity, last 30 days'),
            'chartColorOffset' => 2,
        ]);
    }

    /**
     * JSON data for the "Redirects" tab's `Craft.VueAdminTable` - `_index.twig`'s
     * `tableDataEndpoint`. `page`/`per_page`/`search`/`sort.0.field`/`sort.0.direction` are
     * the component's own query params; `sort.0.field` is matched against the `__slot:title`
     * column's `name` the same way `craft\controllers\EntryTypesController::actionTableData()`
     * matches `__slot:handle`. With no sort applied, this defaults to `hitCount` descending -
     * the same "most-used first" default the old, un-paginated listing used.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.18.0
     */
    public function actionTableData(): Response {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $searchTerm = $request->getParam('search');
        $sortField = $request->getParam('sort.0.field');
        $orderBy = $sortField === '__slot:title' ? 'sourceUri' : 'hitCount';
        $sortDir = match ($request->getParam('sort.0.direction')) {
            'asc' => SORT_ASC,
            'desc' => SORT_DESC,
            default => $sortField === null ? SORT_DESC : SORT_ASC,
        };

        [$pagination, $redirects] = CoreSeoGeo::getInstance()->getRedirects()->getRedirectsTableData($page, $limit, $searchTerm, $orderBy, $sortDir);

        return $this->asSuccess(data: [
            'pagination' => $pagination,
            'data' => $this->_redirectsTableRows($redirects),
        ]);
    }

    /**
     * `actionTableData()`'s exact counterpart for the "404s" tab's `Craft.VueAdminTable`
     * (`_not-found-log.twig`'s `tableDataEndpoint`), sorting by `uri` instead of `sourceUri`.
     *
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.18.0
     */
    public function actionNotFoundLogTableData(): Response {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $searchTerm = $request->getParam('search');
        $sortField = $request->getParam('sort.0.field');
        $orderBy = $sortField === '__slot:title' ? 'uri' : 'hitCount';
        $sortDir = match ($request->getParam('sort.0.direction')) {
            'asc' => SORT_ASC,
            'desc' => SORT_DESC,
            default => $sortField === null ? SORT_DESC : SORT_ASC,
        };

        [$pagination, $entries] = CoreSeoGeo::getInstance()->getRedirects()->getNotFoundLogTableData($page, $limit, $searchTerm, $orderBy, $sortDir);

        return $this->asSuccess(data: [
            'pagination' => $pagination,
            'data' => $this->_notFoundLogTableRows($entries),
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
     * `matchedNotFoundLogEntries` - the "404s this solves" table - is only built for an
     * already-saved redirect, same as `metadataHtml`/`hitChartData`: a brand new, unsaved one
     * has no `sourceUri` worth matching 404s against yet.
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
        // A non-null $redirect here is always actionSave()'s redispatch after a failed save -
        // saveRedirect() already populated its `siteIds` with whatever was posted, so that's
        // left untouched below rather than overwritten with the previously-saved value.
        $isFailedSaveRedispatch = $redirect !== null;

        if ($redirect === null) {
            $redirect = $id !== null
                ? CoreSeoGeo::getInstance()->getRedirects()->getRedirectById($id)
                : new Redirect();
        }

        if ($redirect === null) throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No redirect exists with that ID.'));

        if ($id === null && $redirect->sourceUri === null) $redirect->sourceUri = Craft::$app->getRequest()->getQueryParam('source');

        $isMultiSite = Craft::$app->getIsMultiSite();

        // A brand new record's `siteIds` defaults to `[]` (every site), and a failed-save
        // redispatch's was already set by saveRedirect() - only a normal load of an existing,
        // already-saved redirect needs it fetched here.
        if ($isMultiSite && $redirect->id !== null && !$isFailedSaveRedispatch) $redirect->siteIds = CoreSeoGeo::getInstance()->getRedirects()->getSiteIdsForRedirect($redirect->id);

        return $this->renderTemplate('core-seo-geo/redirects/_edit.twig', [
            'redirect' => $redirect,
            'matchTypeOptions' => Redirect::getMatchTypeOptions(),
            'statusCodeOptions' => Redirect::getStatusCodeOptions(),
            'isMultiSite' => $isMultiSite,
            'sites' => $isMultiSite ? Craft::$app->getSites()->getAllSites() : [],
            'metadataHtml' => $redirect->id ? $this->_metadataHtml($redirect) : null,
            'hitChartData' => $redirect->id ? $this->_metricHitChartData(RedirectsService::METRIC_TYPE_REDIRECT_HIT, $redirect->id) : null,
            'matchedNotFoundLogEntries' => $redirect->id ? $this->_matchedNotFoundLogEntriesTableRows($redirect) : null,
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

        if ($redirect === null) throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No redirect exists with that ID.'));

        $siteIds = [];

        // `_edit.twig`'s "Enabled for all sites" switch posts nothing of its own - like
        // Craft's own entry editor, it's a pure aggregate/cascade control over the per-site
        // switches below it (see that template's JS), so the sites this redirect applies to
        // are read entirely from those.
        if (Craft::$app->getIsMultiSite()) {
            $allSiteIds = array_map(fn($site) => $site->id, Craft::$app->getSites()->getAllSites());

            $selectedSiteIds = array_map('intval', array_keys(array_filter(
                (array)$request->getBodyParam('siteIds', []),
                fn($value) => (bool)$value,
            )));

            if (empty($selectedSiteIds)) {
                $redirect->addError('siteIds', Craft::t('core-seo-geo', 'Select at least one site.'));
                $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the redirect."));

                Craft::$app->getUrlManager()->setRouteParams(['redirect' => $redirect]);

                return null;
            }

            // Every current site individually selected means the same thing as "every
            // site" - stored that way so a site added later is covered automatically too,
            // rather than silently snapshotting today's site list.
            $siteIds = array_diff($allSiteIds, $selectedSiteIds) === [] ? [] : $selectedSiteIds;
        }

        $saved = CoreSeoGeo::getInstance()->getRedirects()->saveRedirect(
            redirect: $redirect,
            sourceUri: (string)$request->getBodyParam('sourceUri', ''),
            matchType: (string)$request->getBodyParam('matchType', Redirect::MATCH_TYPE_EXACT),
            destinationUri: $request->getBodyParam('destinationUri'),
            statusCode: (int)$request->getBodyParam('statusCode', Redirect::STATUS_CODE_MOVED_PERMANENTLY),
            enabled: (bool)$request->getBodyParam('enabled', false),
            siteIds: $siteIds,
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

        if ($redirect === null) throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No redirect exists with that ID.'));

        if (!CoreSeoGeo::getInstance()->getRedirects()->deleteRedirect($redirect)) return $this->asFailure(Craft::t('core-seo-geo', "Couldn't delete the redirect."));

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

        if ($entry === null) throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No log entry exists with that ID.'));

        if (!CoreSeoGeo::getInstance()->getRedirects()->deleteNotFoundLogEntry($entry)) return $this->asFailure(Craft::t('core-seo-geo', "Couldn't delete the log entry."));

        return $this->asSuccess(Craft::t('core-seo-geo', 'Log entry deleted.'));
    }

    /**
     * The "404s" tab's detail page for one `coreseogeo_404s` row - `actionEdit()`'s
     * counterpart for a `NotFoundLogEntry`. Everything shown is observed (hits, referrer,
     * the per-entry hit chart, the "Matching redirects" table), not authored, apart from the
     * "Manually fixed" lightswitch `_not-found-log-edit.twig` posts back to
     * `actionSaveNotFoundLogEntry()` - there's no equivalent of `actionEdit()`'s failed-save
     * redispatch, since that lightswitch is the only field on the page and has nothing to
     * validate.
     *
     * `$match` is `findMatch()`'s own winner (if any) for this entry's `siteId` - the same
     * lookup `_notFoundLogTableRows()`'s `handled` column already re-runs - computed once here
     * and threaded into `_notFoundLogMetadataHtml()`'s own "Status" row (the same chip that
     * column renders), the "Matching redirects" table built from `findAllMatchingRedirects()`'s
     * wider, unscoped result (so that table can flag which one of those rows, if any, is
     * actually the one in effect here), and `showCreateRedirect` - the page's own action
     * button, shown under the exact same condition `_notFoundLogTableRows()`'s own
     * `createRedirect` button is: nothing already covers this URI, and it isn't manually
     * marked fixed either.
     *
     * @param int $id
     * @return Response
     * @throws NotFoundHttpException if `$id` doesn't match an existing row
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    public function actionEditNotFoundLogEntry(int $id): Response {
        $redirects = CoreSeoGeo::getInstance()->getRedirects();
        $entry = $redirects->getNotFoundLogEntryById($id);

        if ($entry === null) throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No log entry exists with that ID.'));

        $match = $redirects->findMatch($entry->siteId, $entry->uri);

        return $this->renderTemplate('core-seo-geo/redirects/_not-found-log-edit.twig', [
            'entry' => $entry,
            'metadataHtml' => $this->_notFoundLogMetadataHtml($entry, $match),
            'hitChartData' => $this->_metricHitChartData(RedirectsService::METRIC_TYPE_NOTFOUND_HIT, $entry->id),
            'isMultiSite' => Craft::$app->getIsMultiSite(),
            'matchingRedirects' => $this->_matchingRedirectsTableRows($redirects->findAllMatchingRedirects($entry->uri), $match?->redirectId),
            'showCreateRedirect' => $match === null && !$entry->resolved,
        ]);
    }

    /**
     * Persists the "Manually fixed" lightswitch posted from `_not-found-log-edit.twig`.
     *
     * @return Response|null
     * @throws NotFoundHttpException if the posted `id` doesn't match an existing row
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    public function actionSaveNotFoundLogEntry(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = (int)$request->getRequiredBodyParam('id');
        $entry = CoreSeoGeo::getInstance()->getRedirects()->getNotFoundLogEntryById($id);

        if ($entry === null) throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No log entry exists with that ID.'));

        $saved = CoreSeoGeo::getInstance()->getRedirects()->setNotFoundLogEntryResolved($entry, (bool)$request->getBodyParam('resolved', false));

        if (!$saved) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the log entry."));

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'Log entry saved.'));

        return $this->redirectToPostedUrl($entry);
    }

    // Private Methods
    // =========================================================================

    /**
     * Renders a `dateLastHit` column value as a human-readable relative time (e.g. "30 days
     * ago", "1 month ago") via `Carbon::diffForHumans()`, or "Never" when nothing's hit it
     * yet. Shared by `_redirectsTableRows()`, `_notFoundLogTableRows()`, and `_metadataHtml()`
     * so the two tables and the edit page's sidebar all render "Last hit" identically.
     *
     * @param string|null $dateLastHit a `dateLastHit` column value - already normalized to
     * UTC by `craft\helpers\Db::prepareDateForDb()` on the way in
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.21.0
     */
    private function _humanizeLastHit(?string $dateLastHit): string {
        if ($dateLastHit === null) return Craft::t('core-seo-geo', 'Never');

        return Carbon::parse($dateLastHit, 'UTC')->locale(Craft::$app->language)->diffForHumans();
    }

    /**
     * Builds one `Craft.VueAdminTable` row per redirect for `actionTableData()`'s JSON
     * response. `title`/`url` feed the `__slot:title` column; `destination` and `enabled` are
     * pre-rendered HTML (`code`/`Cp::statusLabelHtml()` - the same teal+check/gray+xmark shape
     * `TrackingScriptsController::_enabledStatusHtmlById()` uses for its own on/off states)
     * since the admin table renders every non-slot cell's value as raw HTML, not escaped text.
     * `lastHit` is rendered by `_humanizeLastHit()`, shared with `_notFoundLogTableRows()`.
     * `origin` is `sourceElementId !== null ? 'Auto' : 'Manual'` - its own column rather than a
     * suffix tacked onto `title`, so it sorts/scans like every other column instead of needing
     * to be read out of the source URI's own text.
     *
     * @param Redirect[] $redirects
     * @return array<int, array<string, mixed>>
     *
     * @author      Digitalastronaut
     * @since       v1.18.0
     */
    private function _redirectsTableRows(array $redirects): array {
        $rows = [];

        foreach ($redirects as $redirect) {
            $rows[] = [
                'id' => $redirect->id,
                'title' => '/' . $redirect->sourceUri,
                'url' => UrlHelper::url('core-seo-geo/redirects/' . $redirect->id),
                'destination' => $redirect->destinationUri !== null
                    ? Html::tag('code', Html::encode($redirect->destinationUri))
                    : '&ndash;',
                'matchType' => $redirect->matchType === Redirect::MATCH_TYPE_REGEX
                    ? Craft::t('core-seo-geo', 'Regex')
                    : Craft::t('core-seo-geo', 'Exact'),
                'origin' => $redirect->sourceElementId !== null
                    ? Craft::t('core-seo-geo', 'Auto')
                    : Craft::t('core-seo-geo', 'Manual'),
                'statusCode' => (string)$redirect->statusCode,
                'hits' => $redirect->hitCount,
                'lastHit' => $this->_humanizeLastHit($redirect->dateLastHit),
                'enabled' => Cp::statusLabelHtml([
                    'color' => $redirect->enabled ? Color::Teal : Color::Gray,
                    'label' => $redirect->enabled ? Craft::t('core-seo-geo', 'Enabled') : Craft::t('core-seo-geo', 'Disabled'),
                    'icon' => $redirect->enabled ? 'check' : 'xmark',
                ]),
            ];
        }

        return $rows;
    }

    /**
     * The "404s" tab detail page's "Matching redirects" table - every row `RedirectsService::
     * findAllMatchingRedirects()` found for this entry's `uri`, `$activeRedirectId` (`actionEdit
     * NotFoundLogEntry()`'s own `findMatch()` call) marking whichever one of them is actually
     * in effect for this entry's site. Deliberately not a `Craft.VueAdminTable` the way the two
     * tab-level listings are: this is a small, fixed set scoped to one entry, with nothing to
     * search, sort, paginate, or delete - a plain server-rendered table is all it needs.
     *
     * `sites` is omitted by the caller entirely on a single-site install (nothing to show), so
     * it's left out of each row too rather than computed and discarded.
     *
     * @param Redirect[] $redirects
     * @param int|null $activeRedirectId the redirect actually handling this entry's `uri` on
     * its own site, if any - `RedirectMatch::$redirectId` from `RedirectsService::findMatch()`
     * @return array<int, array<string, mixed>>
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    private function _matchingRedirectsTableRows(array $redirects, ?int $activeRedirectId): array {
        $redirectsService = CoreSeoGeo::getInstance()->getRedirects();
        $isMultiSite = Craft::$app->getIsMultiSite();
        $rows = [];

        foreach ($redirects as $redirect) {
            $row = [
                'id' => $redirect->id,
                'title' => '/' . $redirect->sourceUri,
                'url' => UrlHelper::url('core-seo-geo/redirects/' . $redirect->id),
                'destination' => $redirect->destinationUri !== null
                    ? Html::tag('code', Html::encode($redirect->destinationUri))
                    : '&ndash;',
                'matchType' => $redirect->matchType === Redirect::MATCH_TYPE_REGEX
                    ? Craft::t('core-seo-geo', 'Regex')
                    : Craft::t('core-seo-geo', 'Exact'),
                'statusCode' => (string)$redirect->statusCode,
                'enabled' => Cp::statusLabelHtml([
                    'color' => $redirect->enabled ? Color::Teal : Color::Gray,
                    'label' => $redirect->enabled ? Craft::t('core-seo-geo', 'Enabled') : Craft::t('core-seo-geo', 'Disabled'),
                    'icon' => $redirect->enabled ? 'check' : 'xmark',
                ]),
                'active' => $redirect->id === $activeRedirectId
                    ? Cp::statusLabelHtml(['color' => Color::Teal, 'label' => Craft::t('core-seo-geo', 'Applies here'), 'icon' => 'check'])
                    : '&ndash;',
            ];

            if ($isMultiSite) {
                $siteIds = $redirectsService->getSiteIdsForRedirect($redirect->id);

                $row['sites'] = empty($siteIds) ? Craft::t('core-seo-geo', 'All sites') : $this->_siteNamesHtml($siteIds);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * `_matchingRedirectsTableRows()`'s and `_matchedNotFoundLogEntriesTableRows()`'s `sites`/
     * `site` columns: `$siteIds`' own names, comma-separated.
     *
     * @param int[] $siteIds
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    private function _siteNamesHtml(array $siteIds): string {
        $names = array_map(
            fn(int $siteId) => Craft::$app->getSites()->getSiteById($siteId)?->getName() ?? Craft::t('core-seo-geo', 'Deleted site'),
            $siteIds,
        );

        return Html::encode(implode(', ', $names));
    }

    /**
     * The "Redirects" tab detail page's "404s this solves" table - `_matchingRedirectsTable
     * Rows()`'s exact opposite direction: every row `RedirectsService::findNotFoundLogEntries
     * MatchedBy()` found for this redirect's own rule, `status` reusing `_handledStatusHtml()`
     * (the same chip the "404s" tab's own `handled` column shows) rather than a simple
     * "Applies here" flag - a regex redirect can match many 404 rows at once, and some of
     * them may be shadowed by some *other* redirect (an exact match for the same URI, say) or
     * manually marked fixed, so a plain yes/no here would overstate what this one redirect is
     * actually resolving.
     *
     * @param Redirect $redirect a saved redirect (a non-null `id`)
     * @return array<int, array<string, mixed>>
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    private function _matchedNotFoundLogEntriesTableRows(Redirect $redirect): array {
        $redirectsService = CoreSeoGeo::getInstance()->getRedirects();
        $isMultiSite = Craft::$app->getIsMultiSite();
        $rows = [];

        foreach ($redirectsService->findNotFoundLogEntriesMatchedBy($redirect) as $entry) {
            $match = $redirectsService->findMatch($entry->siteId, $entry->uri);

            $row = [
                'id' => $entry->id,
                'title' => '/' . $entry->uri,
                'url' => UrlHelper::url('core-seo-geo/redirects/not-found-log/' . $entry->id),
                'hits' => $entry->hitCount,
                'lastHit' => $this->_humanizeLastHit($entry->dateLastHit),
                'status' => $this->_handledStatusHtml($match, $entry->resolved),
            ];

            if ($isMultiSite) $row['site'] = $this->_siteNamesHtml([$entry->siteId]);

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * `_redirectsTableRows()`'s exact counterpart for the "404s" tab's `Craft.VueAdminTable`.
     * `createRedirect` is a pre-rendered button linking to `actionEdit()`'s `.../new?source=...`
     * hand-off (see that action's own docblock) - raw HTML for the same reason `referrer`/
     * `lastHit` are plain strings rather than slot fields. `lastHit` is rendered by
     * `_humanizeLastHit()`, shared with `_redirectsTableRows()`.
     *
     * `handled` re-resolves each entry's `uri` against `RedirectsService::findMatch()` - the
     * exact same lookup a live request hits - rather than reading anything stored on the row
     * itself: a log entry is only ever written when `findMatch()` came back empty
     * (`RedirectsService::handleNotFound()`'s docblock), so a redirect added *after* the fact
     * leaves the old row sitting here, still looking unhandled, until it's hit again (or
     * deleted). Re-checking on every read is what lets this column tell "still a real gap"
     * apart from "already fixed, just stale" without needing a cache invalidation of its own.
     * A manually-`resolved` entry (`actionEditNotFoundLogEntry()`'s detail page) falls back
     * to this same re-check the moment it 404s again - see `RedirectsService::
     * _recordNotFound()` - so `$entry->resolved` only ever means "still true as of its last hit".
     *
     * `title`/`url` feed the `__slot:title` column, linking to that same detail page - the
     * `_redirectsTableRows()` counterpart for `coreseogeo_404s`.
     *
     * @param NotFoundLogEntry[] $entries
     * @return array<int, array<string, mixed>>
     *
     * @author      Digitalastronaut
     * @since       v1.18.0
     */
    private function _notFoundLogTableRows(array $entries): array {
        $redirects = CoreSeoGeo::getInstance()->getRedirects();
        $rows = [];

        foreach ($entries as $entry) {
            $match = $redirects->findMatch($entry->siteId, $entry->uri);

            $rows[] = [
                'id' => $entry->id,
                'title' => '/' . $entry->uri,
                'url' => UrlHelper::url('core-seo-geo/redirects/not-found-log/' . $entry->id),
                'type' => $entry->isAsset ? Craft::t('core-seo-geo', 'Asset') : Craft::t('core-seo-geo', 'Page'),
                'hits' => $entry->hitCount,
                'lastHit' => $this->_humanizeLastHit($entry->dateLastHit),
                'referrer' => $entry->referrer !== null ? $this->_truncatedReferrerHtml($entry->referrer) : '&ndash;',
                'handled' => $this->_handledStatusHtml($match, $entry->resolved),
                'createRedirect' => $match === null && !$entry->resolved
                    ? Html::a(
                        Craft::t('core-seo-geo', 'Create redirect'),
                        UrlHelper::url('core-seo-geo/redirects/new', ['source' => $entry->uri]),
                        ['class' => 'btn small'],
                    )
                    : '',
            ];
        }

        return $rows;
    }

    /**
     * `_notFoundLogTableRows()`'s `referrer` column: `$referrer` cut down to 60 characters so
     * one long URL can't blow out the column's width, with the full, untruncated value left
     * in a `title` attribute so it's still reachable on hover.
     *
     * @param string $referrer
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    private function _truncatedReferrerHtml(string $referrer): string {
        $truncated = mb_strlen($referrer) > 30 ? mb_substr($referrer, 0, 30) . '…' : $referrer;

        return Html::tag('span', Html::encode($truncated), ['title' => $referrer]);
    }

    /**
     * `_notFoundLogTableRows()`'s `handled` column - three possible states, in priority order:
     *
     * - `$match` non-null: a teal "Handled" label linking to the redirect that now covers this
     *   entry's `uri` - the same color/icon language `_redirectsTableRows()` uses for its own
     *   `enabled` column.
     * - `$match` `null` but `$resolved`: a teal "Manually fixed" label - `actionEdit
     *   NotFoundLogEntry()`'s detail page lightswitch, for a gap no redirect can cover (a
     *   deleted asset re-uploaded under the same path, say).
     * - neither: a gray "Needs attention" label - still a real, unaddressed gap.
     *
     * @param RedirectMatch|null $match `RedirectsService::findMatch()`'s result for this entry's `uri`
     * @param bool $resolved the entry's own `resolved` column
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    private function _handledStatusHtml(?RedirectMatch $match, bool $resolved): string {
        if ($match !== null) {
            return Html::a(
                Cp::statusLabelHtml(['color' => Color::Teal, 'label' => Craft::t('core-seo-geo', 'Handled'), 'icon' => 'check']),
                UrlHelper::url('core-seo-geo/redirects/' . $match->redirectId),
            );
        }

        if ($resolved) {
            return Cp::statusLabelHtml(['color' => Color::Teal, 'label' => Craft::t('core-seo-geo', 'Manually fixed'), 'icon' => 'check']);
        }

        return Cp::statusLabelHtml(['color' => Color::Gray, 'label' => Craft::t('core-seo-geo', 'Needs attention'), 'icon' => 'xmark']);
    }

    /**
     * The activity chart shown above the "Redirects" and "404s" tables - two bars per day
     * over the last 30 days, oldest first, in Chart.js's own `{ labels, datasets }` shape.
     * `$type` picks which half of the request flow to plot: `'redirects'` for new redirects
     * and redirect hits, `'notFound'` for new 404s and 404 hits - each page only shows its
     * own data, since the two tables below it are never both visible at once.
     *
     * @param 'redirects'|'notFound' $type
     * @return array{labels: string[], datasets: array<int, array{label: string, yAxisID: string, data: int[]}>}
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _activityChartData(string $type): array {
        $redirects = CoreSeoGeo::getInstance()->getRedirects();
        $formatter = Craft::$app->getFormatter();

        [$newType, $hitType, $newLabel, $hitLabel] = $type === 'redirects'
            ? [
                RedirectsService::METRIC_TYPE_REDIRECT_NEW,
                RedirectsService::METRIC_TYPE_REDIRECT_HIT,
                Craft::t('core-seo-geo', 'New redirects'),
                Craft::t('core-seo-geo', 'Redirect hits'),
            ]
            : [
                RedirectsService::METRIC_TYPE_NOTFOUND_NEW,
                RedirectsService::METRIC_TYPE_NOTFOUND_HIT,
                Craft::t('core-seo-geo', 'New 404s'),
                Craft::t('core-seo-geo', '404 hits'),
            ];

        $newCounts = $redirects->getMetricCountsByDay($newType, 30);
        $hitCounts = $redirects->getMetricCountsByDay($hitType, 30);

        return [
            'labels' => array_map(fn(string $day) => $formatter->asDate($day, 'short'), array_keys($newCounts)),
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => $newLabel,
                    'yAxisID' => 'yCount',
                    'data' => array_values($newCounts),
                ],
                [
                    'type' => 'bar',
                    'label' => $hitLabel,
                    'yAxisID' => 'yHits',
                    'data' => array_values($hitCounts),
                ],
            ],
        ];
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
            Craft::t('core-seo-geo', 'Last hit') => $this->_humanizeLastHit($redirect->dateLastHit),
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

    /**
     * `_metadataHtml()`'s counterpart for `actionEditNotFoundLogEntry()`'s detail page:
     * `$match`/`$entry->resolved` rendered through `_handledStatusHtml()` - the same chip
     * `_notFoundLogTableRows()`'s `handled` column shows, so the detail page states this
     * entry's status the same way the list it was opened from already did - then hit count,
     * last hit, whether `$entry->uri` looks like an asset or a page request, its referrer (if
     * any), the site it 404'd on (multi-site installs only - a single-site install's own site
     * is implied), and its created/updated dates.
     *
     * @param NotFoundLogEntry $entry
     * @param RedirectMatch|null $match `RedirectsService::findMatch()`'s result for this entry's `uri`
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    private function _notFoundLogMetadataHtml(NotFoundLogEntry $entry, ?RedirectMatch $match): string {
        $rows = [
            Craft::t('core-seo-geo', 'Hits') => (string)$entry->hitCount,
            Craft::t('core-seo-geo', 'Last hit') => $this->_humanizeLastHit($entry->dateLastHit),
            Craft::t('core-seo-geo', 'Type') => $entry->isAsset ? Craft::t('core-seo-geo', 'Asset') : Craft::t('core-seo-geo', 'Page'),
            Craft::t('core-seo-geo', 'Status') => $this->_handledStatusHtml($match, $entry->resolved),
        ];

        if ($entry->referrer !== null) $rows[Craft::t('core-seo-geo', 'Referrer')] = Html::encode($entry->referrer);

        if (Craft::$app->getIsMultiSite()) {
            $site = Craft::$app->getSites()->getSiteById($entry->siteId);

            $rows[Craft::t('core-seo-geo', 'Site')] = $site !== null ? Html::encode($site->getName()) : Craft::t('core-seo-geo', 'Deleted site');
        }

        $formatter = Craft::$app->getFormatter();
        $rows[Craft::t('core-seo-geo', 'Date created')] = $formatter->asDatetime($entry->dateCreated, 'short', true);
        $rows[Craft::t('core-seo-geo', 'Date updated')] = $formatter->asDatetime($entry->dateUpdated, 'short', true);

        return Cp::metadataHtml($rows);
    }

    /**
     * The per-entity mini chart in `_edit.twig`'s and `_not-found-log-edit.twig`'s details
     * sidebars - one bar per day over the last 30 days, oldest first, in the same Chart.js
     * `{ labels, datasets }` shape `_activityChartData()` builds. Reads `coreseogeo_metrics`
     * filtered to one specific `$entityId` (a redirect's own id, or a `NotFoundLogEntry`'s)
     * rather than summed across every row of that type, and across every site (`$siteId:
     * null`) since a redirect's own `hitCount`/`dateLastHit` - what the metadata table right
     * below this chart already shows - are themselves summed across every site it applies to.
     *
     * @param string $metricType `RedirectsService::METRIC_TYPE_REDIRECT_HIT` or `::METRIC_TYPE_NOTFOUND_HIT`
     * @param int $entityId the redirect's, or log entry's, own `id`
     * @return array{labels: string[], datasets: array<int, array{type: string, label: string, data: int[]}>}
     *
     * @author      Digitalastronaut
     * @since       v1.20.0
     */
    private function _metricHitChartData(string $metricType, int $entityId): array {
        $formatter = Craft::$app->getFormatter();
        $counts = CoreSeoGeo::getInstance()->getRedirects()->getMetricCountsByDay($metricType, 30, null, $entityId);

        return [
            'labels' => array_map(fn(string $day) => $formatter->asDate($day, 'short'), array_keys($counts)),
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => Craft::t('core-seo-geo', 'Hits'),
                    'data' => array_values($counts),
                ],
            ],
        ];
    }
}
