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

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\Site;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\db\Table;
use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\records\TxtFileContent;

use yii\caching\TagDependency;

/**
 * Class TxtFilesService
 *
 * Generates every one of this plugin's text files - robots.txt, humans.txt, ads.txt,
 * security.txt, trust.txt, llms.txt, llms-full.txt - and holds the slug/label metadata
 * (`TYPE_SLUGS`) shared between `TxtFilesController`'s settings pages and the "Text Files" table
 * on the Global SEO/GEO settings page. Each file used to get its own service; they're merged
 * here since none of the generation logic is actually shared work worth separating - every
 * `get*Txt()` method below is independent of every other, grouped only by the fact that they're
 * all small, settings-driven text generators with no component of their own to justify.
 *
 * `getContent()`/`saveContent()` own the single global `TxtFileContent` row that every one of
 * these files (`robots.txt` included) reads its own content from - see that record's own
 * docblock for why this content lives in the database rather than on `Settings`/project config.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.10.0
 */
class TxtFilesService extends Component {
    /**
     * @var array<string, string> URL slug => display label
     */
    public const array TYPE_SLUGS = [
        'robots' => 'robots.txt',
        'humans' => 'humans.txt',
        'ads' => 'ads.txt',
        'security' => 'security.txt',
        'trust' => 'trust.txt',
        'llms' => 'llms.txt',
        'llms-full' => 'llms-full.txt',
    ];

    private const string CACHE_TAG_LLMS = 'core-seo-geo-llms';
    private const string CACHE_TAG_LLMS_FULL = 'core-seo-geo-llms-full';

    /**
     * @var bool|null memoized {@see _tableExists()} result
     *
     * @since v1.32.0
     */
    private ?bool $_tableExists = null;

    /**
     * @var TxtFileContent|null memoized {@see getContent()} result
     *
     * @since v1.33.0
     */
    private ?TxtFileContent $_content = null;

    /**
     * @param string $slug
     * @return string|null `null` if `$slug` isn't a recognized key of {@see TYPE_SLUGS}
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    public function getTypeLabel(string $slug): ?string {
        return self::TYPE_SLUGS[$slug] ?? null;
    }

    /**
     * `disallowRobots` (outside dev mode) always wins over every setting below it - the same
     * environment gate `PluginTrait::registerSiteUrlRules()` uses for the sitemap, but inverted: a sitemap hides itself
     * entirely when disallowed, while `robots.txt` has to actively say so instead, since a
     * missing file or a 404 is commonly read by crawlers as "no restrictions."
     *
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    public function getRobotsTxt(int $siteId): string {
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        if ($generalConfig->disallowRobots && !App::devMode()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $content = $this->getContent();
        $groups = [];

        if ($content->robotsTxtDisallowCp) {
            $groups['*'][] = "Disallow: /{$generalConfig->cpTrigger}/";
        }

        foreach ($this->_decodeRows($content->robotsTxtRules) as $rule) {
            $directive = ($rule['type'] ?? TxtFileContent::ROBOTS_RULE_DISALLOW) === TxtFileContent::ROBOTS_RULE_ALLOW
                ? 'Allow'
                : 'Disallow';
            $userAgent = ($rule['userAgent'] ?? '') !== '' ? $rule['userAgent'] : '*';

            $groups[$userAgent][] = "{$directive}: {$rule['path']}";
        }

        // The valid allow-all floor: never emit a group with nothing under it, and never emit
        // no groups at all.
        if ($groups === []) {
            $groups['*'] = ['Disallow:'];
        }

        $lines = [];

        foreach ($groups as $userAgent => $directives) {
            $lines[] = "User-agent: {$userAgent}";
            array_push($lines, ...$directives);
            $lines[] = '';
        }

        foreach ($this->_decodeRows($content->robotsTxtBlockedBots) as $bot) {
            $lines[] = "User-agent: {$bot}";
            $lines[] = 'Disallow: /';
            $lines[] = '';
        }

        if (CoreSeoGeo::getInstance()->getSettings()->enableSitemap) {
            $lines[] = 'Sitemap: ' . UrlHelper::siteUrl('sitemap.xml', null, null, $siteId);
            $lines[] = '';
        }

        if (trim((string)$content->robotsTxtExtra) !== '') {
            $lines[] = trim($content->robotsTxtExtra);
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /**
     * Generates `humans.txt` (humanstxt.org) - unlike `robots.txt`, nothing here is machine-parsed,
     * so there's no safe default content and no environment gate: an unconfigured install simply
     * produces an empty file, and that's fine, nothing reads it but people.
     *
     * Deliberately does not credit Craft CMS or list installed plugins - that's a stack-fingerprint
     * handed to anyone looking for a known CVE to target, for no real benefit.
     *
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.11.0
     */
    public function getHumansTxt(): string {
        $content = $this->getContent();
        $team = $this->_decodeRows($content->humansTxtTeam);
        $thanks = $this->_decodeRows($content->humansTxtThanks);
        $sections = [];

        if ($team !== []) {
            $sections[] = "/* TEAM */\n\n" . $this->_renderHumansTxtPeople($team);
        }

        if ($thanks !== []) {
            $sections[] = "/* THANKS */\n\n" . $this->_renderHumansTxtPeople($thanks);
        }

        $siteLines = [];

        // Read directly rather than through `DateTimeHelper::toDateTime()`: the column already
        // holds a plain `Y-m-d` calendar date with no time-of-day meaning, and round-tripping a
        // bare date string through that helper assumes midnight UTC, then converts to the app's
        // configured timezone - shifting the date itself back a day for anyone west of UTC.
        if ($content->humansTxtLastUpdated !== null) {
            $siteLines[] = "Last update: {$content->humansTxtLastUpdated}";
        }

        if (trim((string)$content->humansTxtStandards) !== '') {
            $siteLines[] = 'Standards: ' . trim($content->humansTxtStandards);
        }

        if ($siteLines !== []) {
            $sections[] = "/* SITE */\n\n    " . implode("\n    ", $siteLines);
        }

        if (trim((string)$content->humansTxtExtra) !== '') {
            $sections[] = trim($content->humansTxtExtra);
        }

        if ($sections === []) return '';

        return implode("\n\n", $sections) . "\n";
    }

    /**
     * Generates `ads.txt` per the IAB Tech Lab ads.txt v1.0.3 spec
     * ({@link https://iabtechlab.com/ads-txt/}) - the data records from section 2.1 (one
     * `<domain>, <account ID>, <relationship>[, <certification authority ID>]` line per
     * authorized seller), followed by the optional file-level variables from section 2.2
     * (`OWNERDOMAIN`, `MANAGERDOMAIN`, `CONTACT`, `SUBDOMAIN`). Like `humans.txt`, there's no safe
     * default content, so an unconfigured install just produces an empty file.
     *
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.12.0
     */
    public function getAdsTxt(): string {
        $content = $this->getContent();
        $blocks = [];

        $records = $this->_decodeRows($content->adsTxtRecords);

        if ($records !== []) {
            $lines = [];

            foreach ($records as $record) {
                $parts = [
                    $record['domain'] ?? '',
                    $record['accountId'] ?? '',
                    $record['relationship'] ?? '',
                ];

                if (($record['certAuthorityId'] ?? '') !== '') {
                    $parts[] = $record['certAuthorityId'];
                }

                $lines[] = implode(', ', $parts);
            }

            $blocks[] = implode("\n", $lines);
        }

        $variableLines = [];

        if (($content->adsTxtOwnerDomain ?? '') !== '') {
            $variableLines[] = "OWNERDOMAIN={$content->adsTxtOwnerDomain}";
        }

        foreach ($this->_decodeRows($content->adsTxtManagerDomains) as $manager) {
            $value = ($manager['country'] ?? '') !== ''
                ? "{$manager['domain']}, {$manager['country']}"
                : $manager['domain'];

            $variableLines[] = "MANAGERDOMAIN={$value}";
        }

        foreach ($this->_decodeRows($content->adsTxtContacts) as $contact) {
            $variableLines[] = "CONTACT={$contact['value']}";
        }

        foreach ($this->_decodeRows($content->adsTxtSubdomains) as $subdomain) {
            $variableLines[] = "SUBDOMAIN={$subdomain['value']}";
        }

        if ($variableLines !== []) {
            $blocks[] = implode("\n", $variableLines);
        }

        if (trim((string)$content->adsTxtExtra) !== '') {
            $blocks[] = trim($content->adsTxtExtra);
        }

        if ($blocks === []) return '';

        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * `Contact` and `Expires` are both mandatory per RFC 9116 §2.5.3/§2.5.5 - a file missing
     * either would be non-compliant, which is worse than not serving one at all, so this
     * returns an empty string rather than a partial file when either is missing (regardless of
     * `Settings::$enableSecurityTxt`; the serving action 404s on an empty result).
     *
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.13.0
     */
    public function getSecurityTxt(int $siteId): string {
        $content = $this->getContent();
        $contacts = $this->_decodeRows($content->securityTxtContacts);
        $expires = DateTimeHelper::toDateTime($content->securityTxtExpires) ?: null;

        if ($contacts === [] || $expires === null) {
            return '';
        }

        $lines = [];

        foreach ($contacts as $contact) {
            $lines[] = "Contact: {$contact['value']}";
        }

        $lines[] = 'Expires: ' . $expires->format(\DateTime::ATOM);

        foreach ($this->_decodeRows($content->securityTxtEncryption) as $row) {
            $lines[] = "Encryption: {$row['value']}";
        }

        foreach ($this->_decodeRows($content->securityTxtAcknowledgments) as $row) {
            $lines[] = "Acknowledgments: {$row['value']}";
        }

        if (($content->securityTxtPreferredLanguages ?? '') !== '') {
            $lines[] = "Preferred-Languages: {$content->securityTxtPreferredLanguages}";
        }

        foreach ($this->getSecurityTxtCanonicalUrls($siteId) as $url) {
            $lines[] = "Canonical: {$url}";
        }

        foreach ($this->_decodeRows($content->securityTxtPolicy) as $row) {
            $lines[] = "Policy: {$row['value']}";
        }

        foreach ($this->_decodeRows($content->securityTxtHiring) as $row) {
            $lines[] = "Hiring: {$row['value']}";
        }

        if (trim((string)$content->securityTxtExtra) !== '') {
            foreach (preg_split('/\r\n|\r|\n/', trim($content->securityTxtExtra)) as $extraLine) {
                $lines[] = $extraLine;
            }
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * The URL(s) `security.txt` is actually authoritative for - both the preferred
     * `.well-known/security.txt` location and the deprecated-but-still-checked root copy, per
     * whichever of those `PluginTrait::registerSiteUrlRules()` has registered.
     *
     * `Canonical` isn't a `Settings` property at all - unlike every other `security.txt` field,
     * it's not admin-authored content, it's simply the URL(s) this plugin is actually serving the
     * file from, which it already knows. Auto-deriving it is the same reasoning as `robots.txt`'s
     * `Sitemap:` line: asking an admin to type their own site's URL back to it would be pure
     * boilerplate.
     *
     * @param int $siteId
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.13.0
     */
    public function getSecurityTxtCanonicalUrls(int $siteId): array {
        return [
            UrlHelper::siteUrl('.well-known/security.txt', null, null, $siteId),
            UrlHelper::siteUrl('security.txt', null, null, $siteId),
        ];
    }

    /**
     * Generates `trust.txt` per the JournalList trust.txt v0.1 spec
     * ({@link https://journallist.net/trust-txt/}) - `field=value` lines, one per declared value,
     * `#` comments (none generated). Like `humans.txt`/`ads.txt`, there's no safe default content
     * and no field is actually mandatory, so an unconfigured install just produces an empty file.
     *
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.14.0
     */
    public function getTrustTxt(): string {
        $content = $this->getContent();
        $lines = [];

        foreach ($this->_decodeRows($content->trustTxtBelongTo) as $row) {
            $lines[] = "belongto={$row['value']}";
        }

        foreach ($this->_decodeRows($content->trustTxtControl) as $row) {
            $lines[] = "control={$row['value']}";
        }

        foreach ($this->_decodeRows($content->trustTxtMember) as $row) {
            $lines[] = "member={$row['value']}";
        }

        foreach ($this->_decodeRows($content->trustTxtSocial) as $row) {
            $lines[] = "social={$row['value']}";
        }

        foreach ($this->_decodeRows($content->trustTxtContact) as $row) {
            $lines[] = "contact={$row['value']}";
        }

        foreach ($this->_decodeRows($content->trustTxtDisclosure) as $row) {
            $lines[] = "disclosure={$row['value']}";
        }

        foreach ($this->_decodeRows($content->trustTxtCredential) as $row) {
            $lines[] = "credential={$row['value']}";
        }

        foreach ($this->_decodeRows($content->trustTxtCopyright) as $row) {
            $lines[] = "copyright={$row['value']}";
        }

        if ($content->trustTxtDataTrainingAllowed !== null) {
            $lines[] = "datatrainingallowed={$content->trustTxtDataTrainingAllowed}";
        }

        if (trim((string)$content->trustTxtExtra) !== '') {
            $lines[] = trim($content->trustTxtExtra);
        }

        if ($lines === []) return '';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Generates `llms.txt` (the emerging, unofficial llmstxt.org convention) - a curated,
     * Markdown-formatted index of this site's content for AI agents to read instead of crawling
     * rendered HTML. Eligibility reuses exactly the same content `SitemapService::getGroups()`/
     * `getQueryForGroup()` already considers indexable, so this plugin isn't maintaining two
     * separate definitions of "what counts as this site's public content" - the one addition on
     * top is excluding `ROBOTS_NOAI` as well as `ROBOTS_NOINDEX`, since this file is explicitly
     * AI-facing in a way the sitemap isn't.
     *
     * Cached as one aggregate `TagDependency` built from every eligible element's own cache info
     * (same mechanism `MarkdownService` uses per page) - a save to any element, section, or the
     * `ContentSeo` global row that would have invalidated an individual page's cache also
     * invalidates this one, automatically, with no pending/queued flags to maintain.
     *
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function getLlmsTxt(int $siteId): string {
        $cacheKey = [self::CACHE_TAG_LLMS, 'llms-txt', $siteId];
        $cached = Craft::$app->getCache()->get($cacheKey);

        if (\is_string($cached)) {
            return $cached;
        }

        $elements = Craft::$app->getElements();
        $elements->startCollectingCacheInfo();

        $txt = $this->_generateLlmsTxt($siteId);

        [$dependency, $duration] = $elements->stopCollectingCacheInfo();
        $dependency ??= new TagDependency();
        $dependency->tags[] = self::CACHE_TAG_LLMS;

        Craft::$app->getCache()->set($cacheKey, $txt, $duration ?? 0, $dependency);

        return $txt;
    }

    /**
     * Invalidates every site's cached llms.txt. The `TagDependency` built into `getLlmsTxt()`
     * already invalidates itself when an eligible *element* changes - this covers the cases that
     * aren't an element save at all: `Settings::$enableLlmsTxt`/`ContentSeo::$llmsTxtTitle`/
     * `$llmsTxtSummary` being saved. Called from `ContentSeoService::saveLlmsTxtConfig()`.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function clearLlmsCache(): void {
        TagDependency::invalidate(Craft::$app->getCache(), self::CACHE_TAG_LLMS);
    }

    /**
     * Generates `llms-full.txt`, the llmstxt.org companion to `llms.txt`: instead of a link and a
     * one-line description per page, it concatenates every eligible page's *full* Markdown content
     * in sequence. Eligibility is exactly `getEligibleEntriesByGroup()`'s set - this plugin isn't
     * maintaining a third definition of "what counts as this site's public content" - and the
     * per-page content itself is `MarkdownService::getMarkdownForElement()`, the same cached,
     * self-fetched Markdown the `.md` route already serves per page.
     *
     * The aggregate result is cached the same way `getLlmsTxt()` caches its own - one
     * `TagDependency` built by collecting cache info across every eligible element - on top of
     * `MarkdownService`'s own per-page cache, so a cold cache still means one self-fetch per
     * eligible page the first time this is requested (there's no warming job yet, by design).
     *
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function getLlmsFullTxt(int $siteId): string {
        $cacheKey = [self::CACHE_TAG_LLMS_FULL, 'llms-full-txt', $siteId];
        $cached = Craft::$app->getCache()->get($cacheKey);

        if (\is_string($cached)) {
            return $cached;
        }

        $elements = Craft::$app->getElements();
        $elements->startCollectingCacheInfo();

        $txt = $this->_generateLlmsFullTxt($siteId);

        [$dependency, $duration] = $elements->stopCollectingCacheInfo();
        $dependency ??= new TagDependency();
        $dependency->tags[] = self::CACHE_TAG_LLMS_FULL;

        Craft::$app->getCache()->set($cacheKey, $txt, $duration ?? 0, $dependency);

        return $txt;
    }

    /**
     * Invalidates every site's cached llms-full.txt. Same reasoning as `clearLlmsCache()` -
     * covers the `ContentSeo`/`Settings` save cases an eligible element's own `TagDependency`
     * can't.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function clearLlmsFullCache(): void {
        TagDependency::invalidate(Craft::$app->getCache(), self::CACHE_TAG_LLMS_FULL);
    }

    /**
     * Every eligible element for `$siteId`, grouped by the same section/singles/categories
     * labels `SitemapService::getGroups()` uses, in that same order. Shared by `getLlmsTxt()` and
     * `getLlmsFullTxt()` so both files agree on exactly the same eligible set.
     *
     * @param int $siteId
     * @return array<string, array<int, array{title: string, url: string, description: string, element: \craft\base\ElementInterface}>>
     * group label => its entries
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function getEligibleEntriesByGroup(int $siteId): array {
        $sitemap = CoreSeoGeo::getInstance()->getSitemap();
        $contentSeo = CoreSeoGeo::getInstance()->getContentSeo();
        $groups = [];

        foreach ($sitemap->getGroups($siteId) as $handle => $label) {
            $query = $sitemap->getQueryForGroup($siteId, $handle);

            // `custom-urls` has no backing element to resolve a title/description/robots from.
            if ($query === null) continue;

            $entries = [];

            foreach ($query->each() as $element) {
                $seo = $contentSeo->resolveSeoData($element);
                if ($seo === null) continue;

                $robots = $seo->robots !== [] ? $seo->robots : $contentSeo->getFallbackRobots($element);
                if (array_intersect($robots, [SeoData::ROBOTS_NOINDEX, SeoData::ROBOTS_NOAI]) !== []) continue;

                $url = $element->getUrl();
                if ($url === null) continue;

                $entries[] = [
                    'title' => ($seo->metaTitle ?? '') !== '' ? $seo->metaTitle : (string)$element->title,
                    'url' => $url,
                    'description' => $seo->metaDescription ?? '',
                    'element' => $element,
                ];
            }

            if ($entries !== []) {
                $groups[$label] = $entries;
            }
        }

        return $groups;
    }

    /**
     * The `# Title`/`> Summary` llms.txt/llms-full.txt open with - the site's own configured
     * `llmsTxtTitle`/`llmsTxtSummary` (per-site, on the `ContentSeo` global row), falling back
     * to the site's own name when no title was set.
     *
     * @param int $siteId
     * @return array{0: string, 1: string} title, summary (summary is `''` when unset)
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function getTitleAndSummary(int $siteId): array {
        $row = CoreSeoGeo::getInstance()->getContentSeo()->getGlobalRow($siteId);

        $title = $row?->llmsTxtTitle;
        $title ??= Craft::$app->getSites()->getSiteById($siteId)?->name ?? Craft::$app->getSystemName();

        return [$title, trim((string)($row?->llmsTxtSummary ?? ''))];
    }

    /**
     * The other sites in `$siteId`'s own site group (same brand, other locales - e.g.
     * `pluginTestEn`/`pluginTestNl` both sitting in the same group), each pointing at their own
     * copy of the given file. Since this is per-site, an agent that's only ever seen the EN
     * `llms.txt` has no way to discover the NL one otherwise exists - this is the llms.txt
     * equivalent of the hreflang alternates `HreflangService::getAlternates()` adds to a page's
     * `<head>`, just at the site level instead of the per-element one that service handles.
     * `[]` when `$siteId`'s group has no other sites.
     *
     * @param int $siteId
     * @param string $filename `llms.txt` or `llms-full.txt`
     * @return array<int, array{label: string, url: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function getLanguageAlternates(int $siteId, string $filename): array {
        $sites = Craft::$app->getSites();
        $site = $sites->getSiteById($siteId);

        if ($site === null) return [];

        return array_values(array_map(
            static fn(Site $groupSite): array => [
                'label' => $groupSite->name,
                'url' => UrlHelper::siteUrl($filename, null, null, $groupSite->id),
            ],
            array_filter(
                $sites->getSitesByGroupId($site->groupId),
                static fn(Site $groupSite): bool => $groupSite->id !== $siteId,
            ),
        ));
    }

    /**
     * The ready-to-insert `Languages: [Label](url) | ...` line built from
     * `getLanguageAlternates()` - `null` when there's nothing to link to, so a caller can skip
     * adding it (and the blank line around it) entirely rather than checking emptiness itself.
     * Shared by `_generateLlmsTxt()` and `_generateLlmsFullTxt()`.
     *
     * @param int $siteId
     * @param string $filename `llms.txt` or `llms-full.txt`
     * @return string|null
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function getLanguagesLine(int $siteId, string $filename): ?string {
        $alternates = $this->getLanguageAlternates($siteId, $filename);

        if ($alternates === []) return null;

        return 'Languages: ' . implode(' | ', array_map(
            static fn(array $alternate): string => "[{$alternate['label']}]({$alternate['url']})",
            $alternates,
        ));
    }

    /**
     * The single global `TxtFileContent` row holding every one of these files' content - a
     * fresh, unsaved record (every column at its column/`init()` default) when the table
     * doesn't exist yet or no row has been saved. There's never more than one row, so this is a
     * get-or-new lookup rather than a get-or-create one - `saveContent()` is the only thing that
     * ever actually inserts it.
     *
     * Memoized per request: `PluginTrait::registerSiteUrlRules()` now reads `$enableRobotsTxt`
     * off this row on every single request (to decide whether to register the `robots.txt` URL
     * rule at all), so without memoizing, a request that goes on to actually serve one of these
     * files - or render one of their settings pages - would query this row twice over.
     *
     * @return TxtFileContent
     *
     * @author      Digitalastronaut
     * @since       v1.32.0
     */
    public function getContent(): TxtFileContent {
        if (isset($this->_content)) return $this->_content;

        if (!$this->_tableExists()) return $this->_content = new TxtFileContent();

        return $this->_content = TxtFileContent::find()->one() ?? new TxtFileContent();
    }

    /**
     * Persists whatever of `humans.txt`/`ads.txt`/`security.txt`/`trust.txt`'s content fields
     * were posted from one of the `txt-<slug>.twig` pages into the single global
     * `TxtFileContent` row, creating it on first save. Each page posts only its own fields under
     * the shared `settings[...]` namespace alongside the `Settings`-model fields
     * `TxtFilesController::actionSave()` hands to `CoreSeoGeo::savePartialSettings()` separately
     * - unlike that project-config save, a DB record update only touches the columns actually
     * assigned here, so (unlike `Settings`) there's no need to merge over the row's current
     * values first.
     *
     * @param array $posted this one page's posted `settings[...]` fields
     * @return bool whether the save succeeded
     *
     * @author      Digitalastronaut
     * @since       v1.32.0
     */
    public function saveContent(array $posted): bool {
        if (!$this->_tableExists()) return false;

        $content = $this->getContent();

        if (array_key_exists('enableRobotsTxt', $posted)) {
            $content->enableRobotsTxt = (bool)$posted['enableRobotsTxt'];
        }

        if (array_key_exists('robotsTxtDisallowCp', $posted)) {
            $content->robotsTxtDisallowCp = (bool)$posted['robotsTxtDisallowCp'];
        }

        // A multiselect - its hidden fallback posts `''` (not `[]`) when nothing's selected.
        if (array_key_exists('robotsTxtBlockedBots', $posted)) {
            $content->robotsTxtBlockedBots = Json::encode(
                \is_array($posted['robotsTxtBlockedBots']) ? array_values($posted['robotsTxtBlockedBots']) : [],
            );
        }

        if (array_key_exists('robotsTxtRules', $posted)) {
            $content->robotsTxtRules = Json::encode(\is_array($posted['robotsTxtRules'])
                ? array_values(array_filter(array_map(
                    fn(array $row): array => [
                        'path' => trim((string)($row['path'] ?? '')),
                        'type' => ($row['type'] ?? TxtFileContent::ROBOTS_RULE_DISALLOW) === TxtFileContent::ROBOTS_RULE_ALLOW
                            ? TxtFileContent::ROBOTS_RULE_ALLOW
                            : TxtFileContent::ROBOTS_RULE_DISALLOW,
                        'userAgent' => trim((string)($row['userAgent'] ?? '')) ?: '*',
                    ],
                    $posted['robotsTxtRules'],
                ), static fn(array $row): bool => $row['path'] !== ''))
                : []);
        }

        if (array_key_exists('robotsTxtExtra', $posted)) {
            $content->robotsTxtExtra = trim((string)$posted['robotsTxtExtra']) ?: null;
        }

        if (array_key_exists('humansTxtTeam', $posted)) {
            $content->humansTxtTeam = Json::encode($this->_normalizeHumansTxtPersonRows($posted['humansTxtTeam']));
        }

        if (array_key_exists('humansTxtThanks', $posted)) {
            $content->humansTxtThanks = Json::encode($this->_normalizeHumansTxtPersonRows($posted['humansTxtThanks']));
        }

        if (array_key_exists('humansTxtStandards', $posted)) {
            $content->humansTxtStandards = trim((string)$posted['humansTxtStandards']) ?: null;
        }

        if (array_key_exists('humansTxtLastUpdated', $posted)) {
            // A plain calendar date with no time-of-day meaning - formatted directly rather than
            // through `Db::prepareDateForDb()`, which converts to UTC and would shift the date
            // itself back a day for anyone west of UTC.
            $date = DateTimeHelper::toDateTime($posted['humansTxtLastUpdated']) ?: null;
            $content->humansTxtLastUpdated = $date?->format('Y-m-d');
        }

        if (array_key_exists('humansTxtExtra', $posted)) {
            $content->humansTxtExtra = trim((string)$posted['humansTxtExtra']) ?: null;
        }

        if (array_key_exists('adsTxtRecords', $posted)) {
            $content->adsTxtRecords = Json::encode(\is_array($posted['adsTxtRecords'])
                ? array_values(array_filter(array_map(
                    fn(array $row): array => [
                        'domain' => $this->_cleanAdsTxtDomain((string)($row['domain'] ?? '')),
                        'accountId' => trim((string)($row['accountId'] ?? '')),
                        'relationship' => ($row['relationship'] ?? TxtFileContent::ADS_RELATIONSHIP_DIRECT) === TxtFileContent::ADS_RELATIONSHIP_RESELLER
                            ? TxtFileContent::ADS_RELATIONSHIP_RESELLER
                            : TxtFileContent::ADS_RELATIONSHIP_DIRECT,
                        'certAuthorityId' => trim((string)($row['certAuthorityId'] ?? '')),
                    ],
                    $posted['adsTxtRecords'],
                ), static fn(array $row): bool => $row['domain'] !== '' && $row['accountId'] !== ''))
                : []);
        }

        if (array_key_exists('adsTxtOwnerDomain', $posted)) {
            $content->adsTxtOwnerDomain = $this->_cleanAdsTxtDomain((string)$posted['adsTxtOwnerDomain']) ?: null;
        }

        if (array_key_exists('adsTxtManagerDomains', $posted)) {
            $content->adsTxtManagerDomains = Json::encode(\is_array($posted['adsTxtManagerDomains'])
                ? array_values(array_filter(array_map(
                    fn(array $row): array => [
                        'domain' => $this->_cleanAdsTxtDomain((string)($row['domain'] ?? '')),
                        'country' => strtoupper(trim((string)($row['country'] ?? ''))),
                    ],
                    $posted['adsTxtManagerDomains'],
                ), static fn(array $row): bool => $row['domain'] !== ''))
                : []);
        }

        if (array_key_exists('adsTxtContacts', $posted)) {
            $content->adsTxtContacts = Json::encode($this->_normalizeSingleValueRows($posted['adsTxtContacts']));
        }

        if (array_key_exists('adsTxtSubdomains', $posted)) {
            $content->adsTxtSubdomains = Json::encode(\is_array($posted['adsTxtSubdomains'])
                ? array_values(array_filter(array_map(
                    fn(array $row): array => ['value' => $this->_cleanAdsTxtDomain((string)($row['value'] ?? ''))],
                    $posted['adsTxtSubdomains'],
                ), static fn(array $row): bool => $row['value'] !== ''))
                : []);
        }

        if (array_key_exists('adsTxtExtra', $posted)) {
            $content->adsTxtExtra = trim((string)$posted['adsTxtExtra']) ?: null;
        }

        if (array_key_exists('securityTxtContacts', $posted)) {
            $content->securityTxtContacts = Json::encode($this->_normalizeSingleValueRows($posted['securityTxtContacts']));
        }

        if (array_key_exists('securityTxtExpires', $posted)) {
            $date = DateTimeHelper::toDateTime($posted['securityTxtExpires']) ?: null;
            $content->securityTxtExpires = $date !== null ? Db::prepareDateForDb($date) : null;
        }

        if (array_key_exists('securityTxtEncryption', $posted)) {
            $content->securityTxtEncryption = Json::encode($this->_normalizeSingleValueRows($posted['securityTxtEncryption']));
        }

        if (array_key_exists('securityTxtAcknowledgments', $posted)) {
            $content->securityTxtAcknowledgments = Json::encode($this->_normalizeSingleValueRows($posted['securityTxtAcknowledgments']));
        }

        if (array_key_exists('securityTxtPreferredLanguages', $posted)) {
            $content->securityTxtPreferredLanguages = trim((string)$posted['securityTxtPreferredLanguages']) ?: null;
        }

        if (array_key_exists('securityTxtPolicy', $posted)) {
            $content->securityTxtPolicy = Json::encode($this->_normalizeSingleValueRows($posted['securityTxtPolicy']));
        }

        if (array_key_exists('securityTxtHiring', $posted)) {
            $content->securityTxtHiring = Json::encode($this->_normalizeSingleValueRows($posted['securityTxtHiring']));
        }

        if (array_key_exists('securityTxtExtra', $posted)) {
            $content->securityTxtExtra = trim((string)$posted['securityTxtExtra']) ?: null;
        }

        foreach (['trustTxtBelongTo', 'trustTxtControl', 'trustTxtMember'] as $domainField) {
            if (array_key_exists($domainField, $posted)) {
                $content->$domainField = Json::encode(\is_array($posted[$domainField])
                    ? array_values(array_filter(array_map(
                        fn(array $row): array => ['value' => $this->_cleanAdsTxtDomain((string)($row['value'] ?? ''))],
                        $posted[$domainField],
                    ), static fn(array $row): bool => $row['value'] !== ''))
                    : []);
            }
        }

        foreach (['trustTxtSocial', 'trustTxtContact', 'trustTxtDisclosure', 'trustTxtCredential', 'trustTxtCopyright'] as $urlField) {
            if (array_key_exists($urlField, $posted)) {
                $content->$urlField = Json::encode($this->_normalizeSingleValueRows($posted[$urlField]));
            }
        }

        if (array_key_exists('trustTxtDataTrainingAllowed', $posted)) {
            $content->trustTxtDataTrainingAllowed = $posted['trustTxtDataTrainingAllowed'] === 'yes' || $posted['trustTxtDataTrainingAllowed'] === 'no'
                ? $posted['trustTxtDataTrainingAllowed']
                : null;
        }

        if (array_key_exists('trustTxtExtra', $posted)) {
            $content->trustTxtExtra = trim((string)$posted['trustTxtExtra']) ?: null;
        }

        return $content->save();
    }

    /**
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    private function _generateLlmsTxt(int $siteId): string {
        $lines = [];

        [$title, $summary] = $this->getTitleAndSummary($siteId);

        $lines[] = "# {$title}";

        if ($summary !== '') {
            $lines[] = '';
            $lines[] = "> {$summary}";
        }

        $languagesLine = $this->getLanguagesLine($siteId, 'llms.txt');

        if ($languagesLine !== null) {
            $lines[] = '';
            $lines[] = $languagesLine;
        }

        foreach ($this->getEligibleEntriesByGroup($siteId) as $label => $entries) {
            $lines[] = '';
            $lines[] = "## {$label}";
            $lines[] = '';

            foreach ($entries as $entry) {
                $line = "- [{$entry['title']}]({$entry['url']})";

                if ($entry['description'] !== '') {
                    $line .= ": {$entry['description']}";
                }

                $lines[] = $line;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    private function _generateLlmsFullTxt(int $siteId): string {
        $markdown = CoreSeoGeo::getInstance()->getMarkdown();

        [$title, $summary] = $this->getTitleAndSummary($siteId);

        $lines = ["# {$title}"];

        if ($summary !== '') {
            $lines[] = '';
            $lines[] = "> {$summary}";
        }

        $languagesLine = $this->getLanguagesLine($siteId, 'llms-full.txt');

        if ($languagesLine !== null) {
            $lines[] = '';
            $lines[] = $languagesLine;
        }

        foreach ($this->getEligibleEntriesByGroup($siteId) as $label => $entries) {
            $lines[] = '';
            $lines[] = "## {$label}";

            foreach ($entries as $entry) {
                $pageMarkdown = $markdown->getMarkdownForElement($entry['element']);

                // A page with no `{% llmContent %}` blocks (or that failed to fetch) has
                // nothing to add here - it still gets its link-and-description line in
                // llms.txt, just not a content section in this file.
                if ($pageMarkdown === '') continue;

                $lines[] = '';
                $lines[] = '---';
                $lines[] = '';
                // Deliberately bold text, not a `#`-heading: the page's own content can (and
                // often does) open with its own real heading, and a heading here immediately
                // above that one reads as "this page has nothing in it" - a blank-looking
                // heading followed immediately by another heading - even though content
                // follows right after. Bold text can never be confused with an empty section.
                $lines[] = "**{$entry['title']}** ({$entry['url']})";
                $lines[] = '';
                $lines[] = $pageMarkdown;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Renders a list of credited people - `TxtFileContent::$humansTxtTeam`/`$humansTxtThanks`,
     * same row shape either way - as the indented `<role>: <name>` blocks shared by both the
     * TEAM and THANKS sections, one blank line between people.
     *
     * @param array<int, array{role: string, name: string, contact: string, from: string}> $people
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.11.0
     */
    private function _renderHumansTxtPeople(array $people): string {
        $lines = [];

        foreach ($people as $person) {
            $role = $person['role'] ?? '';
            $name = $person['name'] ?? '';

            $lines[] = '    ' . ($role !== '' ? "{$role}: {$name}" : $name);

            foreach (['contact' => 'Contact', 'from' => 'From'] as $key => $label) {
                if (($person[$key] ?? '') !== '') {
                    $lines[] = "    {$label}: {$person[$key]}";
                }
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    /**
     * Decodes one of `TxtFileContent`'s JSON-encoded columns back into its row array - `[]` for
     * `null` (an unsaved column, or a brand new record from `getContent()`).
     *
     * @param string|null $json
     * @return array
     *
     * @author      Digitalastronaut
     * @since       v1.32.0
     */
    private function _decodeRows(?string $json): array {
        return $json !== null ? Json::decode($json) : [];
    }

    /**
     * Normalizes a posted `humansTxtTeam`/`humansTxtThanks` editable table - both are a list of
     * credited people with the same row shape, only the section header they end up under
     * differs ({@see getHumansTxt()}). Rows with no name are dropped entirely; a row with a name
     * but nothing else is still kept.
     *
     * @param mixed $value
     * @return array<int, array{role: string, name: string, contact: string, from: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.11.0
     */
    private function _normalizeHumansTxtPersonRows(mixed $value): array {
        if (!\is_array($value)) return [];

        return array_values(array_filter(array_map(
            static fn(array $row): array => [
                'role' => trim((string)($row['role'] ?? '')),
                'name' => trim((string)($row['name'] ?? '')),
                'contact' => trim((string)($row['contact'] ?? '')),
                'from' => trim((string)($row['from'] ?? '')),
            ],
            $value,
        ), static fn(array $row): bool => $row['name'] !== ''));
    }

    /**
     * Strips a pasted scheme/path off an ads.txt domain value (`https://google.com/` ->
     * `google.com`) and lowercases it - the spec's domain fields are compared case-insensitively
     * but conventionally serialized lowercase, and pasting a full URL instead of a bare domain
     * is the single most common mistake when copying these values from an ad network's docs.
     *
     * @param string $domain
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.12.0
     */
    private function _cleanAdsTxtDomain(string $domain): string {
        $domain = trim($domain);
        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $domain) ?? $domain;
        $domain = explode('/', $domain, 2)[0];

        return strtolower($domain);
    }

    /**
     * Normalizes a posted single-column editable table (`{value: string}` rows) - the shape
     * shared by `adsTxtContacts` and every repeatable `security.txt`/`trust.txt` field. Rows
     * with an empty value are dropped.
     *
     * @param mixed $value
     * @return array<int, array{value: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.13.0
     */
    private function _normalizeSingleValueRows(mixed $value): array {
        if (!\is_array($value)) return [];

        return array_values(array_filter(array_map(
            static fn(array $row): array => ['value' => trim((string)($row['value'] ?? ''))],
            $value,
        ), static fn(array $row): bool => $row['value'] !== ''));
    }

    /**
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.32.0
     */
    private function _tableExists(): bool {
        return $this->_tableExists ??= Craft::$app->getDb()->tableExists(Table::TXTFILECONTENT);
    }
}
