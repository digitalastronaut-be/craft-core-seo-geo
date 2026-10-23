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

use DateTime;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\Site;

use digitalastronaut\craftcoreseogeo\models\RedirectMatch;
use digitalastronaut\craftcoreseogeo\records\NotFoundLogEntry;
use digitalastronaut\craftcoreseogeo\records\Redirect;

use yii\db\Expression;

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
     * Every redirect, ordered by hit count descending so the most-used ones surface first -
     * the CP listing's default sort.
     *
     * @return Redirect[]
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function getAllRedirects(): array {
        return Redirect::find()
            ->orderBy(['hitCount' => SORT_DESC])
            ->all();
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
     * Persists the given redirect's posted attributes. Enforces exact-match uniqueness
     * (`siteId` + `sourceUri`) as an application-level check rather than a DB constraint -
     * see `redirects-roadmap.md` Step 1 for why a unique index can't do this for regex rows
     * too. Normalizes `sourceUri`/`destinationUri` for an exact-match row the same way
     * `findMatch()`'s incoming URI is normalized, so a saved row and a live request are
     * always compared on equal footing; a regex row's `sourceUri` is trimmed only, since its
     * leading `/`, if any, is meaningful to the pattern itself.
     *
     * @param Redirect $redirect the record to update - a brand new, unsaved one for a create
     * @param string $sourceUri
     * @param string $matchType one of `Redirect::MATCH_TYPES`
     * @param string|null $destinationUri required unless `$statusCode` is `Redirect::STATUS_CODE_GONE`
     * @param int $statusCode one of `Redirect::STATUS_CODES`
     * @param bool $enabled
     * @param int|null $siteId `null` applies to every site
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
        ?int $siteId,
        ?int $sourceElementId = null,
    ): bool {
        $sourceUri = $matchType === Redirect::MATCH_TYPE_EXACT
            ? self::normalizeUri($sourceUri)
            : trim($sourceUri);
        $destinationUri = $statusCode === Redirect::STATUS_CODE_GONE
            ? null
            : ($destinationUri !== null ? trim($destinationUri) : null);

        if ($matchType === Redirect::MATCH_TYPE_REGEX && @preg_match('#' . $sourceUri . '#', '') === false) {
            $redirect->addError('sourceUri', Craft::t('core-seo-geo', 'This isn’t a valid regular expression.'));

            return false;
        }

        if ($matchType === Redirect::MATCH_TYPE_EXACT && $this->_exactSourceUriInUse($sourceUri, $siteId, $redirect->id)) {
            $redirect->addError('sourceUri', Craft::t('core-seo-geo', 'A redirect for this source URI (on this site) already exists.'));

            return false;
        }

        $redirect->sourceUri = $sourceUri;
        $redirect->matchType = $matchType;
        $redirect->destinationUri = $destinationUri;
        $redirect->statusCode = $statusCode;
        $redirect->enabled = $enabled;
        $redirect->siteId = $siteId;
        $redirect->sourceElementId = $sourceElementId;

        $saved = $redirect->save();

        if ($saved) $this->clearCache();

        return $saved;
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

        if (($row = $lookup['exact'][$uri] ?? null) !== null) {
            return new RedirectMatch((int)$row['id'], $row['destinationUri'], (int)$row['statusCode']);
        }

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
     * Atomically increments a redirect's own hit counter - called once Step 2's 404 handler
     * has resolved a request to this redirect. A single `UPDATE ... SET hitCount = hitCount +
     * 1` round-trip, not a read-then-save cycle: under concurrent requests for the same
     * popular redirect, a read/increment/save in PHP can lose increments to a race, where an
     * increment expressed inside the database itself can't.
     *
     * @param int $redirectId
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function incrementHitCount(int $redirectId): void {
        Craft::$app->getDb()->createCommand()->update(
            Redirect::tableName(),
            [
                'hitCount' => new Expression('[[hitCount]] + 1'),
                'dateLastHit' => Db::prepareDateForDb(new DateTime()),
            ],
            ['id' => $redirectId],
        )->execute();
    }

    /**
     * The 404 entry point - called once per unhandled 404 (see `PluginTrait::
     * registerRedirectHandling()`). Tries to resolve `$uri` against `findMatch()` first; a
     * match's own `hitCount` is incremented and the match returned so the caller can issue
     * the actual redirect/410 response. No match means this is a genuine gap: a row is
     * upserted into `coreseogeo_404s` instead, and `null` is returned so the caller falls
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
            $this->incrementHitCount($match->redirectId);

            return $match;
        }

        $this->_recordNotFound($siteId, $uri, $referrer);

        return null;
    }

    /**
     * Every distinct 404'd URI that's never matched a redirect, ordered by hit count
     * descending so the most-hit gaps surface first - the CP listing's default sort.
     *
     * @return NotFoundLogEntry[]
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function getAllNotFoundLogEntries(): array {
        return NotFoundLogEntry::find()
            ->orderBy(['hitCount' => SORT_DESC])
            ->all();
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
     * @param int $siteId
     * @param string $uri already normalized
     * @param string|null $referrer
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _recordNotFound(int $siteId, string $uri, ?string $referrer): void {
        $now = Db::prepareDateForDb(new DateTime());
        $uri = mb_substr($uri, 0, 255);
        $referrer = $referrer !== null ? mb_substr($referrer, 0, 2000) : null;

        Craft::$app->getDb()->createCommand()->upsert(
            NotFoundLogEntry::tableName(),
            [
                'siteId' => $siteId,
                'uri' => $uri,
                'referrer' => $referrer,
                'hitCount' => 1,
                'dateLastHit' => $now,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ],
            [
                'hitCount' => new Expression('[[hitCount]] + 1'),
                'referrer' => $referrer,
                'dateLastHit' => $now,
                'dateUpdated' => $now,
            ],
        )->execute();
    }

    /**
     * @param string $sourceUri already normalized
     * @param int|null $siteId
     * @param int|null $excludeId the redirect being saved, excluded from its own uniqueness check
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _exactSourceUriInUse(string $sourceUri, ?int $siteId, ?int $excludeId): bool {
        $query = Redirect::find()
            ->where([
                'matchType' => Redirect::MATCH_TYPE_EXACT,
                'sourceUri' => $sourceUri,
                'siteId' => $siteId,
            ]);

        if ($excludeId !== null) {
            $query->andWhere(['!=', 'id', $excludeId]);
        }

        return $query->exists();
    }

    /**
     * Builds (or reads from cache) the enabled-redirect lookup for one site: an `sourceUri =>
     * row` map for exact matches, and an `id`-ordered list of rows for regex matches. Both
     * include that site's own rows and every all-sites (`siteId = null`) row.
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
            ->andWhere(['or', ['siteId' => null], ['siteId' => $siteId]])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all();

        $exact = [];
        $regex = [];

        // `asArray()` rows come back from PDO with `siteId` as a numeric string (or `null`),
        // not an int - normalized here once so every comparison below is `null` vs. `int`,
        // never a string/int mismatch that a strict `!==` would get wrong.
        foreach ($rows as &$row) {
            $row['siteId'] = $row['siteId'] !== null ? (int)$row['siteId'] : null;
        }
        unset($row);

        // Two passes - all-sites rows first, then this site's own - so a site-specific exact
        // redirect always overwrites a same-key all-sites one below, regardless of which of
        // the two was actually created first.
        foreach ([null, $siteId] as $scopeSiteId) {
            foreach ($rows as $row) {
                if ($row['matchType'] !== Redirect::MATCH_TYPE_EXACT) continue;
                if ($row['siteId'] !== $scopeSiteId) continue;

                $exact[$row['sourceUri']] = $row;
            }
        }

        foreach ($rows as $row) {
            if ($row['matchType'] !== Redirect::MATCH_TYPE_REGEX) continue;
            if ($row['siteId'] !== null && $row['siteId'] !== $siteId) continue;

            $regex[] = $row;
        }

        $lookup = ['exact' => $exact, 'regex' => $regex];

        $cache->set($cacheKey, $lookup, self::CACHE_DURATION);

        return $lookup;
    }

    /**
     * Invalidates every site's cached lookup. Every site, not just the one a save/delete
     * affected: an all-sites (`siteId = null`) row's change needs every site's cache cleared,
     * and there are too few sites on any real install for that to be worth optimizing around.
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
}
