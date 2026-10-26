<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\services;

use Carbon\Carbon;

use Craft;
use craft\base\Component;
use craft\db\Table as CraftTable;
use craft\elements\Entry;
use craft\helpers\AdminTable;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\Site;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\db\Table;
use digitalastronaut\craftcoreseogeo\models\RedirectMatch;
use digitalastronaut\craftcoreseogeo\records\Metric;
use digitalastronaut\craftcoreseogeo\records\NotFoundLogEntry;
use digitalastronaut\craftcoreseogeo\records\Redirect;

use Throwable;
use yii\db\Expression;
use yii\db\Query;

/**
 * Class RedirectsService
 *
 * Reads and writes `coreseogeo_redirects` rows, and resolves a 404'd URI against them
 * (`findMatch()`). See `redirects-roadmap.md` for the full plan - this class currently
 * covers Step 1/3/7's CRUD and matching engine; the `coreseogeo_404s` log (Step 2) and the
 * auto-redirect-on-URL-change listener (Step 5) land on this same class as those steps are
 * built, rather than splitting into a second service - the two concerns are tightly
 * coupled by design (a 404 log row's whole purpose is to become a redirect).
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.16.0
 */
class RedirectsService extends Component {
    /**
     * How long a site's resolved redirect lookup (`_getCachedLookup()`) stays cached for.
     * Explicitly invalidated by `clearCache()` on every save/delete, so this duration is
     * only ever a backstop - the same role `SitemapService::CACHE_DURATION` plays.
     *
     * @since v1.16.0
     */
    private const int CACHE_DURATION = 86400;
    private const string CACHE_KEY_PREFIX = 'coreseogeo_redirects_';

    /**
     * `coreseogeo_metrics`' bucket width - every metric's timestamp is rounded down to the
     * start of its 5-minute window before being upserted, so the table holds at most one row
     * per bucket per `(type, entityId, siteId)` (288/day each) no matter how much traffic
     * lands in it.
     *
     * @since v1.17.0
     */
    private const int HIT_BUCKET_MINUTES = 5;

    /**
     * `coreseogeo_metrics` row `type`s - see `Metric`'s own docblock for why these all share
     * one table instead of one each. `*_NEW` fires once, the moment the entity itself is
     * created (`entityId` is that entity's own id); `*_HIT` fires on every hit (`entityId` is
     * the redirect, or `NotFoundLogEntry`, that was hit). Public so `RedirectsController::
     * _activityChartData()` can pass them into `getMetricCountsByDay()`.
     *
     * @since v1.20.0
     */
    public const string METRIC_TYPE_NOTFOUND_NEW = 'notfound_new';
    public const string METRIC_TYPE_NOTFOUND_HIT = 'notfound_hit';
    public const string METRIC_TYPE_REDIRECT_NEW = 'redirect_new';
    public const string METRIC_TYPE_REDIRECT_HIT = 'redirect_hit';

    /**
     * Bot-probe paths that would otherwise flood `coreseogeo_404s` with nothing actionable -
     * see `redirects-roadmap.md` Step 2. Hardcoded rather than a `Settings` field: if this
     * list ever needs to grow, that's a code change, not new CP configuration nobody
     * remembers to revisit.
     *
     * @since v1.16.0
     */
    private const array NOISE_PATTERNS = [
        '#^wp-admin(/|$)#i',
        '#^wp-login\.php$#i',
        '#^xmlrpc\.php$#i',
        '#(^|/)\.env$#i',
        '#(^|/)\.git(/|$)#i',
        '#\.(php\d?|asp|aspx|jsp)$#i',
    ];

    /**
     * One page of redirects for the "Redirects" tab's `Craft.VueAdminTable`, optionally
     * filtered by `$searchTerm` against `sourceUri`/`destinationUri`. Mirrors the
     * `page`/`limit`/`orderBy`/`sortDir` shape `craft\services\Entries::getTableData()` uses
     * for its own admin table, including `craft\helpers\AdminTable::paginationLinks()` for the
     * response's `pagination` block.
     *
     * @param int $page 1-indexed
     * @param int $limit rows per page
     * @param string|null $searchTerm
     * @param string $orderBy a `Redirect` attribute name
     * @param int $sortDir `SORT_ASC` or `SORT_DESC`
     * @return array{0: array, 1: Redirect[]} `[pagination, rows]`
     *
     * @author      Digitalastronaut
     * @since       v1.18.0
     */
    public function getRedirectsTableData(int $page, int $limit, ?string $searchTerm, string $orderBy, int $sortDir): array {
        $query = Redirect::find()->orderBy([$orderBy => $sortDir]);

        if ($searchTerm !== null && $searchTerm !== '') {
            $query->andWhere(['or',
                ['like', 'sourceUri', $searchTerm],
                ['like', 'destinationUri', $searchTerm],
            ]);
        }

        $total = (int)$query->count();

        $rows = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        return [AdminTable::paginationLinks($page, $total, $limit), $rows];
    }

    /**
     * @param int $id
     * @return Redirect|null
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function getRedirectById(int $id): ?Redirect {
        return Redirect::findOne($id);
    }

    /**
     * The sites a saved redirect applies to - `_edit.twig`'s per-site lightswitches, and
     * `saveRedirect()`'s own uniqueness check, read this same `coreseogeo_redirect_sites`
     * table. Empty means every site.
     *
     * @param int $redirectId
     * @return int[]
     *
     * @author      Digitalastronaut
     * @since       v1.19.0
     */
    public function getSiteIdsForRedirect(int $redirectId): array {
        return array_map('intval', (new Query())
            ->select('siteId')
            ->from(Table::REDIRECTSITES)
            ->where(['redirectId' => $redirectId])
            ->column());
    }

    /**
     * Persists the given redirect's posted attributes. Enforces exact-match uniqueness
     * (`siteIds` + `sourceUri`) as an application-level check rather than a DB constraint -
     * see `redirects-roadmap.md` Step 1 for why a unique index can't do this for regex rows
     * too. Normalizes `sourceUri`/`destinationUri` for an exact-match row the same way
     * `findMatch()`'s incoming URI is normalized, so a saved row and a live request are
     * always compared on equal footing; a regex row's `sourceUri` is trimmed only, since its
     * leading `/`, if any, is meaningful to the pattern itself.
     *
     * `$redirect->siteIds` is set to the (normalized) posted value up front, before any
     * validation branch below can return early - so `_edit.twig` can redisplay exactly what
     * was posted, including the site selection, on a validation failure.
     *
     * @param Redirect $redirect the record to update - a brand new, unsaved one for a create
     * @param string $sourceUri
     * @param string $matchType one of `Redirect::MATCH_TYPES`
     * @param string|null $destinationUri required unless `$statusCode` is `Redirect::STATUS_CODE_GONE`
     * @param int $statusCode one of `Redirect::STATUS_CODES`
     * @param bool $enabled
     * @param int[] $siteIds the sites this redirect applies to - empty applies to every site
     * @param int|null $sourceElementId set only when this redirect is auto-created from an entry's URL changing
     * @return bool whether the save succeeded
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function saveRedirect(
        Redirect $redirect,
        string $sourceUri,
        string $matchType,
        ?string $destinationUri,
        int $statusCode,
        bool $enabled,
        array $siteIds,
        ?int $sourceElementId = null,
    ): bool {
        $sourceUri = $matchType === Redirect::MATCH_TYPE_EXACT
            ? self::normalizeUri($sourceUri)
            : trim($sourceUri);
        $destinationUri = $statusCode === Redirect::STATUS_CODE_GONE
            ? null
            : ($destinationUri !== null ? trim($destinationUri) : null);
        $siteIds = array_values(array_unique(array_map('intval', $siteIds)));

        $redirect->siteIds = $siteIds;

        if ($matchType === Redirect::MATCH_TYPE_REGEX && @preg_match('#' . $sourceUri . '#', '') === false) {
            $redirect->addError('sourceUri', Craft::t('core-seo-geo', 'This isn’t a valid regular expression.'));

            return false;
        }

        if ($matchType === Redirect::MATCH_TYPE_EXACT && $this->_exactSourceUriInUse($sourceUri, $siteIds, $redirect->id)) {
            $redirect->addError('sourceUri', Craft::t('core-seo-geo', 'A redirect for this source URI (on these sites) already exists.'));

            return false;
        }

        $redirect->sourceUri = $sourceUri;
        $redirect->matchType = $matchType;
        $redirect->destinationUri = $destinationUri;
        $redirect->statusCode = $statusCode;
        $redirect->enabled = $enabled;
        $redirect->sourceElementId = $sourceElementId;

        $isNew = $redirect->getIsNewRecord();

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $saved = $redirect->save();

            if ($saved) $this->_saveSitesForRedirect($redirect->id, $siteIds);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        if ($saved) {
            $this->clearCache();

            if ($isNew) {
                $appliesToSiteIds = !empty($siteIds)
                    ? $siteIds
                    : array_map(fn(Site $site) => $site->id, Craft::$app->getSites()->getAllSites());

                foreach ($appliesToSiteIds as $siteId) $this->_upsertMetric(self::METRIC_TYPE_REDIRECT_NEW, $siteId, $redirect->id);
            }
        }

        return $saved;
    }

    /**
     * Step 5 of `redirects-roadmap.md`: auto-creates (or chain-collapses into) a redirect once
     * `PluginTrait`'s uri-change listener has confirmed `$element`'s URI genuinely changed and
     * every disabled/draft-or-revision/propagating guard has already passed - this is purely
     * the collision check, chain collapsing, and the save itself.
     *
     * `$oldUri`/`$newUri` are raw `elements_sites.uri` values - normalized here via
     * `normalizeUri()` the same way every other comparison in this class normalizes before
     * comparing, so a trailing-slash difference alone never counts as a "real" change. Either
     * one being the homepage's `Entry::HOMEPAGE_URI` sentinel (`'__home__'`, stored literally
     * when a single's URI format resolves to it) is skipped entirely - that's not a real
     * request path to redirect from or to.
     *
     * Two deliberate improvements over Retour's own version of this feature:
     *
     * - **Collision guard** - before creating anything, checks whether the URI being vacated is
     *   now some *other* live element's current URI (slug reuse). If so, this does nothing -
     *   redirecting a real, currently-live page elsewhere would be a bug, not a feature.
     * - **Chain collapsing** - an entry renamed twice in a row (`/a` -> `/b` -> `/c`) would
     *   otherwise leave two separate rows behind: visiting `/a` bounces through two redirect
     *   hops before reaching `/c`, a real SEO/latency cost search engines explicitly discourage.
     *   If an existing auto-created redirect from this same entry (`sourceElementId`) already
     *   points *at* the URI being vacated, its `destinationUri` is repointed to `$newUri`
     *   instead of inserting a second row - so `/a` always lands straight on wherever the entry
     *   lives now, however many times it's moved since.
     *
     * @param Entry $element the entry whose URI just finished changing
     * @param string $oldUri raw, not yet normalized
     * @param string $newUri raw, not yet normalized
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.22.0
     */
    public function handleElementUriChange(Entry $element, string $oldUri, string $newUri): void {
        $oldUri = self::normalizeUri($oldUri);
        $newUri = self::normalizeUri($newUri);

        if ($oldUri === '' || $oldUri === $newUri) return;
        if ($oldUri === Entry::HOMEPAGE_URI || $newUri === Entry::HOMEPAGE_URI) return;

        $siteId = $element->siteId;

        // Joined against `elements` and filtered to `draftId`/`revisionId`/`dateDeleted` all
        // null - every save leaves a revision snapshot behind, and that revision's own
        // `elements_sites` row inherits whatever URI the entry had at that exact moment (the
        // URI being vacated right now, the very next time it's renamed). Without this filter,
        // that revision reads as "some other element already owns this URI" on every single
        // rename after the first, and the guard below would refuse to ever create a redirect.
        $liveElementId = (new Query())
            ->select(['es.elementId'])
            ->from(['es' => CraftTable::ELEMENTS_SITES])
            ->innerJoin(['e' => CraftTable::ELEMENTS], '[[e.id]] = [[es.elementId]]')
            ->where(['es.siteId' => $siteId, 'es.uri' => $oldUri])
            ->andWhere(['!=', 'es.elementId', $element->id])
            ->andWhere(['e.draftId' => null, 'e.revisionId' => null, 'e.dateDeleted' => null])
            ->scalar();

        if ($liveElementId !== false) return;

        // Scoped to rows that apply to this site specifically (its own row, or no
        // `coreseogeo_redirect_sites` rows at all) - the same all-sites-or-this-one shape
        // `_getCachedLookup()` already uses - so a same-destination row auto-created for some
        // *other* site on a multi-site install is never mistaken for this one's own chain.
        $existingId = (new Query())
            ->select(['r.id'])
            ->from(['r' => Redirect::tableName()])
            ->leftJoin(['rs' => Table::REDIRECTSITES], '[[rs.redirectId]] = [[r.id]]')
            ->where([
                'r.sourceElementId' => $element->id,
                'r.matchType' => Redirect::MATCH_TYPE_EXACT,
                'r.destinationUri' => $oldUri,
            ])
            ->andWhere(['or', ['rs.id' => null], ['rs.siteId' => $siteId]])
            ->scalar();

        $existing = $existingId !== false ? Redirect::findOne((int)$existingId) : null;

        $this->saveRedirect(
            redirect: $existing ?? new Redirect(),
            sourceUri: $existing !== null ? $existing->sourceUri : $oldUri,
            matchType: Redirect::MATCH_TYPE_EXACT,
            destinationUri: $newUri,
            statusCode: CoreSeoGeo::getInstance()->getSettings()->defaultRedirectStatusCode,
            enabled: true,
            siteIds: [$siteId],
            sourceElementId: $element->id,
        );
    }

    /**
     * @param Redirect $redirect
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function deleteRedirect(Redirect $redirect): bool {
        $deleted = (bool)$redirect->delete();

        if ($deleted) $this->clearCache();

        return $deleted;
    }

    /**
     * Resolves a 404'd URI against every enabled redirect that applies to `$siteId` (its own
     * site-specific rows plus every all-sites row). Exact matches are tried first via an
     * indexed lookup; a regex match is only attempted when no exact match exists, in the
     * order the rows were created (see `redirects-roadmap.md` Step 3 for why there's no
     * separate priority column). Both branches read through `_getCachedLookup()` rather than
     * querying per call, since a 404 flood from bot traffic shouldn't mean a query flood too.
     *
     * @param int $siteId
     * @param string $uri the raw request path - normalized internally, callers don't need to
     * @return RedirectMatch|null `null` if nothing matched
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function findMatch(int $siteId, string $uri): ?RedirectMatch {
        $uri = self::normalizeUri($uri);
        $lookup = $this->_getCachedLookup($siteId);

        if (($row = $lookup['exact'][$uri] ?? null) !== null) return new RedirectMatch((int)$row['id'], $row['destinationUri'], (int)$row['statusCode']);

        foreach ($lookup['regex'] as $row) {
            // Patterns are stored without delimiters (see `saveRedirect()`'s docblock) so a
            // site owner writing one doesn't need to think about PCRE delimiter escaping - `#`
            // is added here, the one character a URI path can't itself contain unescaped.
            $pattern = '#' . $row['sourceUri'] . '#';

            // Suppressed: a malformed pattern should fail this one row, not spam the log on
            // every single 404 - `saveRedirect()` above already rejects an invalid pattern at
            // save time, so this only guards against a row that slipped through some other way
            // (a direct DB edit, a restored backup from an older version).
            if (@preg_match($pattern, $uri) !== 1) continue;

            $destinationUri = $row['destinationUri'] !== null
                ? preg_replace($pattern, $row['destinationUri'], $uri)
                : null;

            return new RedirectMatch((int)$row['id'], $destinationUri, (int)$row['statusCode']);
        }

        return null;
    }

    /**
     * Every `Redirect` row - enabled or not, scoped to any site - whose own rule would fire
     * for `$uri`: an exact-match row whose `sourceUri` equals it, or a regex row whose pattern
     * matches it. `findMatch()`'s own winner is always somewhere in here too, but this isn't
     * scoped to one site or filtered to `enabled` rows the way that cached lookup is - this is
     * for the "404s" tab detail page's "Matching redirects" table, where a site owner staring
     * at a 404 that still 404s needs to see not just which redirect won (if any), but which
     * *other* rows are close - disabled, scoped to a different site, or merely shadowed by
     * this URI's own exact match - so they can tell at a glance why a redirect they already
     * made isn't actually firing. Exact rows first (in `id` order), then regex rows that match
     * in that same order - the same precedence `findMatch()` resolves a live request in.
     *
     * Reads straight from the database rather than `_getCachedLookup()`'s cache: the detail
     * page this backs is a rarely-visited diagnostic view, not something a 404 flood ever
     * hits, so there's no reason to pay that cache's own staleness-after-save tradeoff here.
     *
     * @param string $uri the 404'd URI - normalized internally, callers don't need to
     * @return Redirect[]
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    public function findAllMatchingRedirects(string $uri): array {
        $uri = self::normalizeUri($uri);

        $exactMatches = Redirect::find()
            ->where(['matchType' => Redirect::MATCH_TYPE_EXACT, 'sourceUri' => $uri])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $regexMatches = array_values(array_filter(
            Redirect::find()->where(['matchType' => Redirect::MATCH_TYPE_REGEX])->orderBy(['id' => SORT_ASC])->all(),
            // Suppressed for the same reason `findMatch()`'s own regex loop suppresses it - a
            // malformed pattern should be skipped here, not break this whole diagnostic table.
            fn(Redirect $redirect) => @preg_match('#' . $redirect->sourceUri . '#', $uri) === 1,
        ));

        return array_merge($exactMatches, $regexMatches);
    }

    /**
     * `findAllMatchingRedirects()`'s exact opposite direction: every `coreseogeo_404s` row
     * whose own `uri` `$redirect`'s rule would fire for - exact equality for an exact-match
     * redirect, or a `preg_match()` test for a regex one, where a single regex redirect can
     * of course match many distinct 404 rows at once. The "Redirects" tab detail page's own
     * "404s this solves" table.
     *
     * Not scoped to the sites `$redirect` applies to, and not filtered to rows this redirect
     * is actually the live winner for - like `findAllMatchingRedirects()`, this is a
     * diagnostic view: a 404 on a site this redirect doesn't cover, or one shadowed by some
     * *other* redirect for the same URI, is still worth showing (the caller re-runs
     * `findMatch()` per row for its own status column) so a site owner can tell why a URI
     * that textually matches still isn't actually being resolved by this one.
     *
     * @param Redirect $redirect a saved redirect (a non-null `id`)
     * @return NotFoundLogEntry[]
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    public function findNotFoundLogEntriesMatchedBy(Redirect $redirect): array {
        if ($redirect->matchType === Redirect::MATCH_TYPE_EXACT) {
            return NotFoundLogEntry::find()
                ->where(['uri' => $redirect->sourceUri])
                ->orderBy(['hitCount' => SORT_DESC])
                ->all();
        }

        $pattern = '#' . $redirect->sourceUri . '#';

        return array_values(array_filter(
            NotFoundLogEntry::find()->orderBy(['hitCount' => SORT_DESC])->all(),
            // Suppressed for the same reason `findAllMatchingRedirects()`'s own regex filter
            // suppresses it - a malformed pattern should be skipped here, not break this
            // whole diagnostic table.
            fn(NotFoundLogEntry $entry) => @preg_match($pattern, $entry->uri) === 1,
        ));
    }

    /**
     * Atomically increments a redirect's own hit counter - called once Step 2's 404 handler
     * has resolved a request to this redirect. A single `UPDATE ... SET hitCount = hitCount +
     * 1` round-trip, not a read-then-save cycle: under concurrent requests for the same
     * popular redirect, a read/increment/save in PHP can lose increments to a race, where an
     * increment expressed inside the database itself can't.
     *
     * @param int $redirectId
     * @param Carbon $time the hit's own timestamp - shared with `handleNotFound()`'s own
     * `coreseogeo_metrics` bucket write, so both reflect the exact same instant
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function incrementHitCount(int $redirectId, Carbon $time): void {
        Craft::$app->getDb()->createCommand()->update(
            Redirect::tableName(),
            [
                'hitCount' => new Expression('[[hitCount]] + 1'),
                'dateLastHit' => Db::prepareDateForDb($time),
            ],
            ['id' => $redirectId],
        )->execute();
    }

    /**
     * The 404 entry point - called once per unhandled 404 (see `PluginTrait::
     * registerRedirectHandling()`). Tries to resolve `$uri` against `findMatch()` first; a
     * match's own `hitCount` is incremented, a `coreseogeo_metrics` bucket row is upserted
     * alongside it (the "Redirects" tab chart's data), and the match is returned so the caller
     * can issue the actual redirect/410 response. No match means this is a genuine gap: a row
     * is upserted into `coreseogeo_404s` instead, and `null` is returned so the caller falls
     * through to Craft's normal 404 page.
     *
     * A URI matching `NOISE_PATTERNS` is ignored entirely - neither matched nor logged - so
     * routine bot-probe traffic (`wp-login.php`, `.env`, ...) never reaches either table.
     *
     * @param string $uri the raw request path - normalized internally, callers don't need to
     * @param int $siteId
     * @param string|null $referrer the request's `Referer` header, if any
     * @return RedirectMatch|null `null` if nothing matched (including when `$uri` was noise)
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function handleNotFound(string $uri, int $siteId, ?string $referrer = null): ?RedirectMatch {
        $uri = self::normalizeUri($uri);

        if ($this->_isNoiseUri($uri)) return null;

        $match = $this->findMatch($siteId, $uri);

        if ($match !== null) {
            $time = Carbon::now();
            $this->incrementHitCount($match->redirectId, $time);
            $this->_upsertMetric(self::METRIC_TYPE_REDIRECT_HIT, $siteId, $match->redirectId, $time);

            return $match;
        }

        $this->_recordNotFound($siteId, $uri, $referrer);

        return null;
    }

    /**
     * One page of 404 log entries for the "404s" tab's `Craft.VueAdminTable`, optionally
     * filtered by `$searchTerm` against `uri`/`referrer`. `getRedirectsTableData()`'s exact
     * counterpart for `coreseogeo_404s`.
     *
     * @param int $page 1-indexed
     * @param int $limit rows per page
     * @param string|null $searchTerm
     * @param string $orderBy a `NotFoundLogEntry` attribute name
     * @param int $sortDir `SORT_ASC` or `SORT_DESC`
     * @return array{0: array, 1: NotFoundLogEntry[]} `[pagination, rows]`
     *
     * @author      Digitalastronaut
     * @since       v1.18.0
     */
    public function getNotFoundLogTableData(int $page, int $limit, ?string $searchTerm, string $orderBy, int $sortDir): array {
        $query = NotFoundLogEntry::find()->orderBy([$orderBy => $sortDir]);

        if ($searchTerm !== null && $searchTerm !== '') {
            $query->andWhere(['or',
                ['like', 'uri', $searchTerm],
                ['like', 'referrer', $searchTerm],
            ]);
        }

        $total = (int)$query->count();

        $rows = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        return [AdminTable::paginationLinks($page, $total, $limit), $rows];
    }

    /**
     * @param int $id
     * @return NotFoundLogEntry|null
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function getNotFoundLogEntryById(int $id): ?NotFoundLogEntry {
        return NotFoundLogEntry::findOne($id);
    }

    /**
     * Daily volume for one `self::METRIC_TYPE_*`, on each of the last `$days` days (including
     * today), oldest first - the "Redirects"/"404s" tab charts' data, and `Metric`'s own
     * 5-minute buckets summed up into days. Used both for a `*_HIT` type's hit volume and a
     * `*_NEW` type's creation count - the two are counted identically (an upsert-increment per
     * bucket via `_upsertMetric()`), so they're read identically too.
     *
     * @param string $type one of `self::METRIC_TYPE_*`
     * @param int $days
     * @param int|null $siteId one site's own metrics only, or `null` to sum across every site
     * - the existing sitewide activity charts aren't scoped to a site, so this defaults to `null`
     * @param int|null $entityId a specific entity's own metrics only, or `null` to sum across
     * every entity (what the sitewide activity charts want)
     * @return array<string, int> `['Y-m-d' => hitCount]`, one entry per day, zero-filled
     *
     * @author      Digitalastronaut
     * @since       v1.20.0
     */
    public function getMetricCountsByDay(string $type, int $days = 30, ?int $siteId = null, ?int $entityId = null): array {
        $today = Carbon::today();
        $since = $today->copy()->subDays($days - 1);

        $counts = [];
        for ($date = $since->copy(); $date->lte($today); $date->addDay()) $counts[$date->format('Y-m-d')] = 0;

        $query = Metric::find()
            ->select(['timestamp', 'hitCount'])
            ->where(['type' => $type])
            ->andWhere(['>=', 'timestamp', Db::prepareDateForDb($since)]);

        if ($siteId !== null) $query->andWhere(['siteId' => $siteId]);
        if ($entityId !== null) $query->andWhere(['entityId' => $entityId]);

        foreach ($query->asArray()->all() as $row) {
            $day = substr($row['timestamp'], 0, 10);

            if (isset($counts[$day])) $counts[$day] += (int)$row['hitCount'];
        }

        return $counts;
    }

    /**
     * Manually removes a 404 log entry nobody intends to fix. No cache to invalidate - unlike
     * `coreseogeo_redirects`, this table is never read through `_getCachedLookup()`.
     *
     * @param NotFoundLogEntry $entry
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function deleteNotFoundLogEntry(NotFoundLogEntry $entry): bool {
        return (bool)$entry->delete();
    }

    /**
     * Sets `$entry`'s `resolved` flag - the "404s" tab detail page's "Manually fixed"
     * lightswitch, for a 404 that isn't (and never will be) covered by a `Redirect`, such as a
     * deleted asset re-uploaded under the same path. Stays `true` until this exact `(siteId,
     * uri)` 404s again, at which point `_recordNotFound()` flips it back to `false` on its own
     * - see that method's own docblock.
     *
     * @param NotFoundLogEntry $entry
     * @param bool $resolved
     * @return bool whether the save succeeded
     *
     * @author      Digitalastronaut
     * @since       v1.23.0
     */
    public function setNotFoundLogEntryResolved(NotFoundLogEntry $entry, bool $resolved): bool {
        $entry->resolved = $resolved;

        return $entry->save();
    }

    /**
     * Normalizes a request path/source URI the same way everywhere this plugin compares one:
     * strips the query string, decodes percent-encoding, and trims both a leading *and*
     * trailing slash - so a saved `Redirect::$sourceUri` and an incoming request path are
     * always compared on equal footing regardless of which one a caller happened to pass in
     * with a leading `/`, a trailing `/`, or a trailing `?utm_source=...`. Matches how Craft
     * itself stores `element.uri` (never trailing-slashed, `addTrailingSlashesToUrls` aside).
     *
     * @param string $uri
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public static function normalizeUri(string $uri): string {
        $uri = urldecode($uri);
        $uri = explode('?', $uri, 2)[0];

        return trim($uri, '/');
    }

    // Private Methods
    // =========================================================================

    /**
     * @param string $uri already normalized
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _isNoiseUri(string $uri): bool {
        foreach (self::NOISE_PATTERNS as $pattern) {
            if (preg_match($pattern, $uri) === 1) return true;
        }

        return false;
    }

    /**
     * Atomically upserts one `coreseogeo_404s` row - a single `INSERT ... ON DUPLICATE KEY
     * UPDATE` round-trip keyed on the table's `(siteId, uri)` unique index, not a read-then-
     * write cycle: under concurrent requests for the same dead URL (routine under bot
     * traffic), a read/increment/save in PHP can lose increments to a race, where an
     * increment expressed inside the database itself can't. `$uri`/`$referrer` are truncated
     * to their column widths first, since both come from untrusted request data that could
     * otherwise trip a strict-mode truncation error on insert.
     *
     * `dateCreated`/`dateUpdated`/`uid` are set here by hand (mirroring what `craft\db\
     * ActiveRecord::init()` would do for a normal `save()`) because this bypasses the active
     * record entirely - there's no `beforeSave()` lifecycle to populate them for us on the
     * insert branch.
     *
     * Whether this URI has been seen before is checked up front, rather than inferred from
     * the upsert's own result (MySQL's affected-row count for `ON DUPLICATE KEY UPDATE` isn't
     * a portable way to tell "inserted" from "updated"), so `METRIC_TYPE_NOTFOUND_NEW` is only
     * recorded the one time a URI first appears. That same pre-upsert lookup also doubles as
     * this row's own id for an existing row - `_upsertMetric()`'s `entityId`, so the "404s"
     * detail page can chart one entry's own hits rather than every 404 site-wide - while a
     * brand new row's id is only known once the upsert itself has created it.
     *
     * `resolved` is force-set to `false` on every call, insert or update: this only ever runs
     * when `findMatch()` just came back empty, i.e. this URI is 404ing right now - if a prior
     * call here had this row manually marked "fixed" from the detail page, that's no longer
     * true, so the lightswitch needs to fall back off on its own rather than keep claiming a
     * gap that's still open.
     *
     * @param int $siteId
     * @param string $uri already normalized
     * @param string|null $referrer
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _recordNotFound(int $siteId, string $uri, ?string $referrer): void {
        $time = Carbon::now();
        $now = Db::prepareDateForDb($time);
        $uri = mb_substr($uri, 0, 255);
        $referrer = $referrer !== null ? mb_substr($referrer, 0, 2000) : null;

        $existingId = (new Query())
            ->select('id')
            ->from(NotFoundLogEntry::tableName())
            ->where(['siteId' => $siteId, 'uri' => $uri])
            ->scalar();

        $isNew = $existingId === false;

        Craft::$app->getDb()->createCommand()->upsert(
            NotFoundLogEntry::tableName(),
            [
                'siteId' => $siteId,
                'uri' => $uri,
                'referrer' => $referrer,
                'hitCount' => 1,
                'resolved' => false,
                'dateLastHit' => $now,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ],
            [
                'hitCount' => new Expression('[[hitCount]] + 1'),
                'referrer' => $referrer,
                'resolved' => false,
                'dateLastHit' => $now,
                'dateUpdated' => $now,
            ],
        )->execute();

        $entryId = $isNew
            ? (int)(new Query())->select('id')->from(NotFoundLogEntry::tableName())->where(['siteId' => $siteId, 'uri' => $uri])->scalar()
            : (int)$existingId;

        $this->_upsertMetric(self::METRIC_TYPE_NOTFOUND_HIT, $siteId, $entryId, $time);

        if ($isNew) $this->_upsertMetric(self::METRIC_TYPE_NOTFOUND_NEW, $siteId, $entryId, $time);
    }

    /**
     * Atomically upserts one 5-minute-bucket row into `coreseogeo_metrics` for the bucket
     * `$time` falls into - the same single-round-trip `INSERT ... ON DUPLICATE KEY UPDATE`
     * pattern as the upsert above, keyed on `(type, entityId, siteId, timestamp)`.
     *
     * @param string $type one of `self::METRIC_TYPE_*`
     * @param int $siteId
     * @param int $entityId `0` when this metric isn't about one specific entity
     * @param Carbon|null $time the metric's own timestamp, not yet rounded down to a bucket
     * - defaults to now
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.17.0
     */
    private function _upsertMetric(string $type, int $siteId, int $entityId = 0, ?Carbon $time = null): void {
        $time ??= Carbon::now();
        $now = Db::prepareDateForDb($time);
        $bucketStart = Db::prepareDateForDb($this->_bucketStart($time));

        Craft::$app->getDb()->createCommand()->upsert(
            Metric::tableName(),
            [
                'type' => $type,
                'entityId' => $entityId,
                'siteId' => $siteId,
                'timestamp' => $bucketStart,
                'hitCount' => 1,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ],
            [
                'hitCount' => new Expression('[[hitCount]] + 1'),
                'dateUpdated' => $now,
            ],
        )->execute();
    }

    /**
     * Rounds `$time` down to the start of its `self::HIT_BUCKET_MINUTES`-wide bucket - in
     * PHP rather than a DB-specific `DATE_FORMAT()`/`date_trunc()` call, the same
     * portability choice `getMetricCountsByDay()`'s own date-grouping already makes.
     *
     * @param Carbon $time
     * @return Carbon a new instance - `$time` itself is left untouched
     *
     * @author      Digitalastronaut
     * @since       v1.17.0
     */
    private function _bucketStart(Carbon $time): Carbon {
        $bucket = $time->copy();
        $minute = (int)(floor($bucket->minute / self::HIT_BUCKET_MINUTES) * self::HIT_BUCKET_MINUTES);

        $bucket->setTime($bucket->hour, $minute, 0);

        return $bucket;
    }

    /**
     * Whether some *other* exact-match redirect for `$sourceUri` already covers at least one
     * of `$siteIds` - or, if `$siteIds` is itself empty (applies to every site), whether any
     * other exact-match redirect for `$sourceUri` exists at all, since an all-sites redirect
     * necessarily overlaps every other one for the same source URI regardless of its own
     * scope.
     *
     * @param string $sourceUri already normalized
     * @param int[] $siteIds the sites the redirect being saved applies to - empty for every site
     * @param int|null $excludeId the redirect being saved, excluded from its own uniqueness check
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _exactSourceUriInUse(string $sourceUri, array $siteIds, ?int $excludeId): bool {
        $query = Redirect::find()
            ->select('id')
            ->where([
                'matchType' => Redirect::MATCH_TYPE_EXACT,
                'sourceUri' => $sourceUri,
            ]);

        if ($excludeId !== null) $query->andWhere(['!=', 'id', $excludeId]);

        $candidateIds = $query->column();

        if (empty($candidateIds)) return false;
        if (empty($siteIds)) return true;

        $existingSiteIdsByRedirect = [];

        foreach (
            (new Query())
                ->select(['redirectId', 'siteId'])
                ->from(Table::REDIRECTSITES)
                ->where(['redirectId' => $candidateIds])
                ->all() as $row
        ) {
            $existingSiteIdsByRedirect[(int)$row['redirectId']][] = (int)$row['siteId'];
        }

        foreach ($candidateIds as $id) {
            $existingSiteIds = $existingSiteIdsByRedirect[$id] ?? [];

            // No rows at all means that other redirect applies to every site, which overlaps
            // every one of our own selected sites.
            if (empty($existingSiteIds) || array_intersect($existingSiteIds, $siteIds)) return true;
        }

        return false;
    }

    /**
     * Builds (or reads from cache) the enabled-redirect lookup for one site: an `sourceUri =>
     * row` map for exact matches, and an `id`-ordered list of rows for regex matches. Both
     * include that site's own rows and every all-sites (no `coreseogeo_redirect_sites` rows
     * at all) row - the left join below keeps a redirect in the result set if it either has
     * no site rows whatsoever (`rs.id` comes back `null`) or has one matching `$siteId`.
     *
     * A site-specific exact redirect wins over an all-sites one targeting the same source URI
     * - built here as "insert the all-sites rows first, then the site-specific ones", so the
     * later assignment simply overwrites the earlier one at the same array key, rather than a
     * second query or an `ORDER BY` tie-break. Regex rows carry no such precedence - they stay
     * in creation order regardless of scope, per `findMatch()`'s docblock.
     *
     * @param int $siteId
     * @return array{exact: array<string, array<string, mixed>>, regex: array<int, array<string, mixed>>}
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _getCachedLookup(int $siteId): array {
        $cache = Craft::$app->getCache();
        $cacheKey = self::CACHE_KEY_PREFIX . $siteId;
        $cached = $cache->get($cacheKey);

        if ($cached !== false) return $cached;

        $rows = Redirect::find()
            ->where(['enabled' => true])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all();

        $siteIdsByRedirect = [];

        if (!empty($rows)) {
            foreach (
                (new Query())
                    ->select(['redirectId', 'siteId'])
                    ->from(Table::REDIRECTSITES)
                    ->where(['redirectId' => array_column($rows, 'id')])
                    ->all() as $siteRow
            ) {
                $siteIdsByRedirect[(int)$siteRow['redirectId']][] = (int)$siteRow['siteId'];
            }
        }

        // Keep only rows that apply to `$siteId`: either every site (no rows in the join
        // table at all) or this one specifically.
        $rows = array_values(array_filter($rows, function(array $row) use ($siteIdsByRedirect, $siteId) {
            $existingSiteIds = $siteIdsByRedirect[(int)$row['id']] ?? [];

            return empty($existingSiteIds) || in_array($siteId, $existingSiteIds, true);
        }));

        $exact = [];
        $regex = [];

        // Two passes - all-sites rows first, then this site's own - so a site-specific exact
        // redirect always overwrites a same-key all-sites one below, regardless of which of
        // the two was actually created first.
        foreach ([true, false] as $wantAllSites) {
            foreach ($rows as $row) {
                if ($row['matchType'] !== Redirect::MATCH_TYPE_EXACT) continue;

                $isAllSites = empty($siteIdsByRedirect[(int)$row['id']] ?? []);
                if ($isAllSites !== $wantAllSites) continue;

                $exact[$row['sourceUri']] = $row;
            }
        }

        foreach ($rows as $row) {
            if ($row['matchType'] !== Redirect::MATCH_TYPE_REGEX) continue;

            $regex[] = $row;
        }

        $lookup = ['exact' => $exact, 'regex' => $regex];

        $cache->set($cacheKey, $lookup, self::CACHE_DURATION);

        return $lookup;
    }

    /**
     * Invalidates every site's cached lookup. Every site, not just the one a save/delete
     * affected: an all-sites redirect's change needs every site's cache cleared, and there
     * are too few sites on any real install for that to be worth optimizing around.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function clearCache(): void {
        $cache = Craft::$app->getCache();

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            /** @var Site $site */
            $cache->delete(self::CACHE_KEY_PREFIX . $site->id);
        }
    }

    /**
     * Resyncs `coreseogeo_redirect_sites` for one redirect to exactly `$siteIds` - every
     * existing row for it is deleted first, then one row per site is reinserted, rather than
     * diffing old vs. new: there's no per-row state worth preserving (no timestamps or
     * attributes of its own beyond which site it names), so a diff would only save a handful
     * of single-row writes on an operation that's already wrapped in `saveRedirect()`'s own
     * transaction.
     *
     * @param int $redirectId
     * @param int[] $siteIds empty clears every row, meaning "every site"
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.19.0
     */
    private function _saveSitesForRedirect(int $redirectId, array $siteIds): void {
        $db = Craft::$app->getDb();

        $db->createCommand()->delete(Table::REDIRECTSITES, ['redirectId' => $redirectId])->execute();

        if (empty($siteIds)) return;

        $now = Db::prepareDateForDb(Carbon::now());
        $rows = [];

        foreach ($siteIds as $siteId) $rows[] = [$redirectId, $siteId, $now, $now, StringHelper::UUID()];

        $db->createCommand()->batchInsert(
            Table::REDIRECTSITES,
            ['redirectId', 'siteId', 'dateCreated', 'dateUpdated', 'uid'],
            $rows,
        )->execute();
    }
}
