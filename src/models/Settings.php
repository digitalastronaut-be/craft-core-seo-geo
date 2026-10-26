<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\models;

use Craft;
use craft\base\Model;
use craft\elements\Asset;
use craft\helpers\DateTimeHelper;

use digitalastronaut\craftcoreseogeo\records\Redirect;

/**
 * Class Settings
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class Settings extends Model {
    /**
     * @since v1.1.1
     */
    public const string REFERRER_NO_REFERRER = 'no-referrer';
    public const string REFERRER_NO_REFERRER_WHEN_DOWNGRADE = 'no-referrer-when-downgrade';
    public const string REFERRER_ORIGIN = 'origin';
    public const string REFERRER_ORIGIN_WHEN_CROSS_ORIGIN = 'origin-when-cross-origin';
    public const string REFERRER_SAME_ORIGIN = 'same-origin';
    public const string REFERRER_STRICT_ORIGIN = 'strict-origin';
    public const string REFERRER_STRICT_ORIGIN_WHEN_CROSS_ORIGIN = 'strict-origin-when-cross-origin';
    public const string REFERRER_UNSAFE_URL = 'unsafe-url';

    /**
     * @since v1.10.0
     */
    public const string ROBOTS_RULE_ALLOW = 'allow';
    public const string ROBOTS_RULE_DISALLOW = 'disallow';

    /**
     * ads.txt v1.0.3's two account-relationship values ({@link https://iabtechlab.com/ads-txt/}
     * section 2.1) - whether the publisher directly controls the account on the named system
     * (`DIRECT`), or has authorized another entity to resell their ad space through it
     * (`RESELLER`).
     *
     * @since v1.12.0
     */
    public const string ADS_RELATIONSHIP_DIRECT = 'DIRECT';
    public const string ADS_RELATIONSHIP_RESELLER = 'RESELLER';

    /**
     * Overrides the label shown for this plugin's own top-level CP nav item - see
     * `CoreSeoGeo::getCpNavItem()`. Empty by default, which falls back to the translated
     * "SEO/GEO" label.
     *
     * @since v1.16.0
     */
    public ?string $pluginName = null;

    public bool $enableHreflang = true;
    public mixed $faviconAssetId = null;
    public bool $enableSitemap = true;
    public array $customUrls = [];

    /**
     * Whether `robots.txt` is served at all. Forced on regardless of this setting whenever
     * `disallowRobots` is active outside dev mode - see `TxtFilesService::getRobotsTxt()`.
     *
     * @since v1.10.0
     */
    public bool $enableRobotsTxt = true;

    /**
     * Adds a `Disallow` rule for the control panel's own path (`getGeneral()->cpTrigger`) to
     * `robots.txt`'s default `User-agent: *` group.
     *
     * @since v1.10.0
     */
    public bool $robotsTxtDisallowCp = true;

    /**
     * AI crawler user agents (from `BotDetectionService::getKnownBots()`) to give their own
     * `Disallow: /` group in `robots.txt`.
     *
     * @var string[]
     * @since v1.10.0
     */
    public array $robotsTxtBlockedBots = [];

    /**
     * Extra `robots.txt` path rules.
     *
     * @var array<int, array{path: string, type: string, userAgent: string}>
     * @since v1.10.0
     */
    public array $robotsTxtRules = [];

    /**
     * Raw text appended verbatim to the end of the generated `robots.txt`.
     *
     * @since v1.10.0
     */
    public ?string $robotsTxtExtra = null;

    /**
     * Whether `humans.txt` is served at all. Off by default - unlike `robots.txt`, there's no
     * useful default content for an unconfigured team-credits file, so publishing one is an
     * explicit opt-in.
     *
     * @since v1.11.0
     */
    public bool $enableHumansTxt = false;

    /**
     * The TEAM section. Same row shape as `$humansTxtThanks` - both are just a list of
     * credited people, the only difference is which section header they're rendered under.
     *
     * @var array<int, array{role: string, name: string, contact: string, from: string}>
     * @since v1.11.0
     */
    public array $humansTxtTeam = [];

    /**
     * The THANKS section. Same row shape as `$humansTxtTeam` - see above.
     *
     * @var array<int, array{role: string, name: string, contact: string, from: string}>
     * @since v1.11.0
     */
    public array $humansTxtThanks = [];

    /**
     * The SITE section's "Standards" line.
     *
     * @since v1.11.0
     */
    public ?string $humansTxtStandards = null;

    /**
     * The SITE section's "Last update" line, stored as `Y-m-d`.
     *
     * @since v1.11.0
     */
    public ?string $humansTxtLastUpdated = null;

    /**
     * Raw text appended verbatim to the end of the generated `humans.txt`.
     *
     * @since v1.11.0
     */
    public ?string $humansTxtExtra = null;

    /**
     * Whether `ads.txt` is served at all. Off by default, same reasoning as `humans.txt` -
     * there's nothing useful to serve until at least one record is configured.
     *
     * @since v1.12.0
     */
    public bool $enableAdsTxt = false;

    /**
     * The core ads.txt data records (spec section 2.1) - who's authorized to sell this site's
     * ad inventory. `certAuthorityId` is the only optional field per record.
     *
     * @var array<int, array{domain: string, accountId: string, relationship: string, certAuthorityId: string}>
     * @since v1.12.0
     */
    public array $adsTxtRecords = [];

    /**
     * ads.txt's `OWNERDOMAIN` variable (spec section 2.2.3) - the domain of the entity that
     * owns this site/app, when that's a different legal entity than the one selling the ads.
     *
     * @since v1.12.0
     */
    public ?string $adsTxtOwnerDomain = null;

    /**
     * ads.txt's `MANAGERDOMAIN` variable (spec section 2.2.4) - the domain of a sales agent
     * authorized to control the accounts above on the publisher's behalf. `country` is an
     * optional ISO 3166-1 alpha-2 code limiting that authority to one country; the spec allows
     * at most one record with no country (an unconditional grant would otherwise be ambiguous
     * alongside a country-scoped one), which this plugin doesn't enforce - same reasoning as
     * `robots.txt`'s rules table not validating path syntax.
     *
     * @var array<int, array{domain: string, country: string}>
     * @since v1.12.0
     */
    public array $adsTxtManagerDomains = [];

    /**
     * ads.txt's `CONTACT` variable (spec section 2.2.1) - contact info (email or URL) for
     * whoever maintains this file, in case a buyer needs to report an issue.
     *
     * @var array<int, array{value: string}>
     * @since v1.12.0
     */
    public array $adsTxtContacts = [];

    /**
     * ads.txt's `SUBDOMAIN` variable (spec section 2.2.2) - delegates authority for one or more
     * subdomains to their own ads.txt files elsewhere, for multi-brand/multi-site operators who
     * centralize declarations under one root domain.
     *
     * @var array<int, array{value: string}>
     * @since v1.12.0
     */
    public array $adsTxtSubdomains = [];

    /**
     * Raw text appended verbatim to the end of the generated `ads.txt`.
     *
     * @since v1.12.0
     */
    public ?string $adsTxtExtra = null;

    /**
     * Whether `security.txt` is served at all (at both `.well-known/security.txt` and, for
     * backwards compatibility, the root `security.txt` - see `PluginTrait::registerSiteUrlRules()`). Off
     * by default, same reasoning as `humans.txt`/`ads.txt`. Even when on, `TxtFilesService`
     * won't actually serve content until the two fields RFC 9116 requires - at least one
     * `$securityTxtContacts` entry and `$securityTxtExpires` - are both set; serving a file
     * that's missing either would be actively non-compliant, worse than not serving one.
     *
     * @since v1.13.0
     */
    public bool $enableSecurityTxt = false;

    /**
     * RFC 9116's required `Contact` field (§3.5.3) - at least one URI (`mailto:`, `https:`,
     * `tel:`, ...) a security researcher can use to report a vulnerability.
     *
     * @var array<int, array{value: string}>
     * @since v1.13.0
     */
    public array $securityTxtContacts = [];

    /**
     * RFC 9116's required `Expires` field (§3.5.5) - an ISO 8601 date-time, stored in
     * `DateTime::ATOM` format, after which this file should be considered stale. The spec
     * recommends staying within a year of the current date to encourage upkeep.
     *
     * @since v1.13.0
     */
    public ?string $securityTxtExpires = null;

    /**
     * RFC 9116's optional `Encryption` field (§3.5.4) - link(s) to a public key (e.g. PGP)
     * security researchers should use for encrypted reports.
     *
     * @var array<int, array{value: string}>
     * @since v1.13.0
     */
    public array $securityTxtEncryption = [];

    /**
     * RFC 9116's optional `Acknowledgments` field (§3.5.2) - link(s) to a page crediting
     * researchers who've reported vulnerabilities.
     *
     * @var array<int, array{value: string}>
     * @since v1.13.0
     */
    public array $securityTxtAcknowledgments = [];

    /**
     * RFC 9116's optional `Preferred-Languages` field (§3.5.6) - a comma-separated list of
     * language codes (e.g. "en, nl") the security team can respond in. Unlike the fields above,
     * the spec says this one MUST NOT appear more than once, hence a single string setting
     * rather than a repeatable table.
     *
     * @since v1.13.0
     */
    public ?string $securityTxtPreferredLanguages = null;

    /**
     * RFC 9116's optional `Policy` field (§3.5.7) - link(s) to the vulnerability disclosure
     * policy.
     *
     * @var array<int, array{value: string}>
     * @since v1.13.0
     */
    public array $securityTxtPolicy = [];

    /**
     * RFC 9116's optional `Hiring` field (§3.5.8) - link(s) to security-related job postings.
     *
     * @var array<int, array{value: string}>
     * @since v1.13.0
     */
    public array $securityTxtHiring = [];

    /**
     * Raw text appended verbatim to the end of the generated `security.txt`.
     *
     * @since v1.13.0
     */
    public ?string $securityTxtExtra = null;

    /**
     * trust.txt v0.1 ({@link https://journallist.net/trust-txt/}) - note this is a far less
     * formal spec than the others this plugin generates: no RFC, no IAB backing, maintained by
     * a single organization (JournalList) mainly for news publishers and the ad-supply-chain
     * trust signaling ads.txt doesn't cover. The field list below is the commonly documented
     * core set; treat it as less authoritative than `robots.txt`/`ads.txt`/`security.txt`.
     *
     * @since v1.14.0
     */
    public bool $enableTrustTxt = false;

    /**
     * trust.txt's `belongto=` directive - domain(s) of the organization/network this site
     * belongs to.
     *
     * @var array<int, array{value: string}>
     * @since v1.14.0
     */
    public array $trustTxtBelongTo = [];

    /**
     * trust.txt's `control=` directive - domain(s) that control (own) this site.
     *
     * @var array<int, array{value: string}>
     * @since v1.14.0
     */
    public array $trustTxtControl = [];

    /**
     * trust.txt's `member=` directive - domain(s) that are members of this site/organization.
     * The reverse of `$trustTxtBelongTo`, used by a network's own trust.txt to list its members.
     *
     * @var array<int, array{value: string}>
     * @since v1.14.0
     */
    public array $trustTxtMember = [];

    /**
     * trust.txt's `social=` directive - official social media profile URL(s).
     *
     * @var array<int, array{value: string}>
     * @since v1.14.0
     */
    public array $trustTxtSocial = [];

    /**
     * trust.txt's `contact=` directive - contact URI(s) for questions about this file/org.
     *
     * @var array<int, array{value: string}>
     * @since v1.14.0
     */
    public array $trustTxtContact = [];

    /**
     * trust.txt's `disclosure=` directive - link(s) to an ownership/disclosure page.
     *
     * @var array<int, array{value: string}>
     * @since v1.14.0
     */
    public array $trustTxtDisclosure = [];

    /**
     * trust.txt's `credential=` directive - link(s) to a credential verifying this
     * organization (e.g. a press association or Trust Project membership page).
     *
     * @var array<int, array{value: string}>
     * @since v1.14.0
     */
    public array $trustTxtCredential = [];

    /**
     * trust.txt's `copyright=` directive - link(s) to the licensing/copyright terms for this
     * site's content.
     *
     * @var array<int, array{value: string}>
     * @since v1.14.0
     */
    public array $trustTxtCopyright = [];

    /**
     * trust.txt's `datatrainingallowed=` directive - whether this organization permits its
     * content to be used for AI training, at the organizational level rather than the per-page
     * level `SeoData::ROBOTS_NOAI` already covers. `null` means not asserted - the line is
     * omitted entirely rather than defaulting to "yes" or "no".
     *
     * @since v1.14.0
     */
    public ?string $trustTxtDataTrainingAllowed = null;

    /**
     * Raw text appended verbatim to the end of the generated `trust.txt`.
     *
     * @since v1.14.0
     */
    public ?string $trustTxtExtra = null;

    /**
     * Whether `llms.txt` is served at all. Off by default, same reasoning as `humans.txt` -
     * there's nothing useful to serve until the per-site title/summary
     * (`ContentSeo::$llmsTxtTitle`/`$llmsTxtSummary`) are set and/or the site has eligible
     * content.
     *
     * @since v1.15.0
     */
    public bool $enableLlmsTxt = false;

    /**
     * Whether `llms-full.txt` (the full-content companion to `llms.txt`) is served. Kept as its
     * own toggle, independent of `$enableLlmsTxt` - this one concatenates every eligible page's
     * full Markdown rather than just a link and a description, which can get large on a big
     * site, a separate decision from wanting the curated index.
     *
     * @since v1.15.0
     */
    public bool $enableLlmsFullTxt = false;

    /**
     * Master toggle for the whole Redirects feature - gates both the `ErrorHandler` listener
     * that resolves/logs 404s and the `$createUriChangeRedirects` listener below. Unlike
     * `$enableSitemap`, this is never forced off by `disallowRobots`/dev mode - redirect
     * handling and 404 capture have nothing to do with search-engine visibility, and should
     * behave identically on staging and production.
     *
     * @since v1.16.0
     */
    public bool $enableRedirects = true;

    /**
     * Whether an entry's URL changing (slug edit, a structure move changing a parent-based
     * URI, a section's URI format changing) automatically creates - or updates, see
     * `RedirectsService`'s chain-collapsing - a redirect from its old URL to its new one.
     * Independent of `$enableRedirects` itself being on.
     *
     * @since v1.16.0
     */
    public bool $createUriChangeRedirects = true;

    /**
     * The status code used for `$createUriChangeRedirects`' auto-created redirects, and
     * pre-selected on the "add redirect" form. `Redirect::STATUS_CODE_GONE` is deliberately
     * not a valid choice here - it has no destination, so it can't be the result of a URI
     * that just moved to a new one.
     *
     * @since v1.16.0
     */
    public int $defaultRedirectStatusCode = Redirect::STATUS_CODE_MOVED_PERMANENTLY;

    /**
     * Rendered as `<head>`'s `<meta name="theme-color">` and as `manifest.webmanifest`'s
     * `theme_color`/`background_color` - see `FaviconService::getManifest()`.
     *
     * @since v1.1.1
     */
    public ?string $themeColor = null;

    /**
     * Rendered as `<head>`'s `<meta name="referrer">`. Defaults to the browsers' own default
     * policy rather than the stricter `no-referrer`, which drops referrer data even between two
     * HTTPS sites and silently breaks analytics attribution for inbound/outbound links.
     *
     * @since v1.1.1
     */
    public string $referrerPolicy = self::REFERRER_NO_REFERRER_WHEN_DOWNGRADE;

    /**
     * @return array<int, array{label: string, value: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.1.1
     */
    public static function getReferrerPolicyOptions(): array {
        return [
            ['label' => Craft::t('core-seo-geo', 'No Referrer'), 'value' => self::REFERRER_NO_REFERRER],
            ['label' => Craft::t('core-seo-geo', 'No Referrer When Downgrade (browser default)'), 'value' => self::REFERRER_NO_REFERRER_WHEN_DOWNGRADE],
            ['label' => Craft::t('core-seo-geo', 'Origin'), 'value' => self::REFERRER_ORIGIN],
            ['label' => Craft::t('core-seo-geo', 'Origin When Cross-Origin'), 'value' => self::REFERRER_ORIGIN_WHEN_CROSS_ORIGIN],
            ['label' => Craft::t('core-seo-geo', 'Same Origin'), 'value' => self::REFERRER_SAME_ORIGIN],
            ['label' => Craft::t('core-seo-geo', 'Strict Origin'), 'value' => self::REFERRER_STRICT_ORIGIN],
            ['label' => Craft::t('core-seo-geo', 'Strict Origin When Cross-Origin'), 'value' => self::REFERRER_STRICT_ORIGIN_WHEN_CROSS_ORIGIN],
            ['label' => Craft::t('core-seo-geo', 'Unsafe URL'), 'value' => self::REFERRER_UNSAFE_URL],
        ];
    }

    /**
     * @return Asset|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    public function getFaviconAsset(): ?Asset {
        $id = \is_array($this->faviconAssetId) ? reset($this->faviconAssetId) : $this->faviconAssetId;

        return is_numeric($id) ? Asset::find()->id((int)$id)->one() : null;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    public static function getRobotsRuleTypeOptions(): array {
        return [
            ['label' => Craft::t('core-seo-geo', 'Disallow'), 'value' => self::ROBOTS_RULE_DISALLOW],
            ['label' => Craft::t('core-seo-geo', 'Allow'), 'value' => self::ROBOTS_RULE_ALLOW],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.12.0
     */
    public static function getAdsTxtRelationshipOptions(): array {
        return [
            ['label' => Craft::t('core-seo-geo', 'Direct'), 'value' => self::ADS_RELATIONSHIP_DIRECT],
            ['label' => Craft::t('core-seo-geo', 'Reseller'), 'value' => self::ADS_RELATIONSHIP_RESELLER],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.14.0
     */
    public static function getTrustTxtDataTrainingAllowedOptions(): array {
        return [
            ['label' => Craft::t('core-seo-geo', 'Not asserted'), 'value' => ''],
            ['label' => Craft::t('core-seo-geo', 'Yes'), 'value' => 'yes'],
            ['label' => Craft::t('core-seo-geo', 'No'), 'value' => 'no'],
        ];
    }

    /**
     * `$defaultRedirectStatusCode`'s options - deliberately only the two codes that make
     * sense as a *default* (see that property's own docblock for why `STATUS_CODE_GONE`
     * is excluded).
     *
     * @return array<int, array{label: string, value: int}>
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public static function getDefaultRedirectStatusCodeOptions(): array {
        return [
            ['label' => Craft::t('core-seo-geo', '301 (Moved Permanently)'), 'value' => Redirect::STATUS_CODE_MOVED_PERMANENTLY],
            ['label' => Craft::t('core-seo-geo', '302 (Found)'), 'value' => Redirect::STATUS_CODE_FOUND],
        ];
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?\DateTime}>
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getEnabledCustomUrls(): array {
        $urls = [];

        foreach ($this->customUrls as $row) {
            if (!($row['enabled'] ?? false)) continue;
            if (($row['url'] ?? '') === '') continue;

            $urls[] = [
                'loc' => $row['url'],
                'lastmod' => DateTimeHelper::toDateTime($row['lastModified'] ?? null) ?: null,
            ];
        }

        return $urls;
    }

    /**
     * @inheritdoc
     *
     * Normalizes posted `customUrls` rows before Craft's own `setAttributes()` runs - its
     * `datetimeAttributes()` based normalization only reaches top-level typed properties, not
     * the `date` editable-table column's `{date, locale, timezone}` shape buried inside this
     * array's rows, and the `lightswitch` column is simply absent from a row's posted data
     * when it's off rather than posting a falsy value.
     *
     * @param array $values
     * @param bool $safeOnly
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function setAttributes($values, $safeOnly = true): void {
        if (isset($values['customUrls']) && \is_array($values['customUrls'])) {
            $values['customUrls'] = array_values(array_filter(array_map(
                static fn(array $row): array => [
                    'url' => trim((string)($row['url'] ?? '')),
                    'lastModified' => ($date = DateTimeHelper::toDateTime($row['lastModified'] ?? null)) !== false
                        ? $date->format('Y-m-d')
                        : null,
                    'enabled' => (bool)($row['enabled'] ?? false),
                ],
                $values['customUrls'],
            ), static fn(array $row): bool => $row['url'] !== ''));
        }

        // `robotsTxtBlockedBots` is a multiselect - its hidden fallback posts `''` (not `[]`)
        // when nothing's selected, same reason `customUrls` needs the array check above.
        if (isset($values['robotsTxtBlockedBots'])) {
            $values['robotsTxtBlockedBots'] = \is_array($values['robotsTxtBlockedBots'])
                ? array_values($values['robotsTxtBlockedBots'])
                : [];
        }

        if (isset($values['robotsTxtRules'])) {
            $values['robotsTxtRules'] = \is_array($values['robotsTxtRules'])
                ? array_values(array_filter(array_map(
                    static fn(array $row): array => [
                        'path' => trim((string)($row['path'] ?? '')),
                        'type' => ($row['type'] ?? self::ROBOTS_RULE_DISALLOW) === self::ROBOTS_RULE_ALLOW
                            ? self::ROBOTS_RULE_ALLOW
                            : self::ROBOTS_RULE_DISALLOW,
                        'userAgent' => trim((string)($row['userAgent'] ?? '')) ?: '*',
                    ],
                    $values['robotsTxtRules'],
                ), static fn(array $row): bool => $row['path'] !== ''))
                : [];
        }

        if (isset($values['humansTxtTeam'])) {
            $values['humansTxtTeam'] = self::_normalizeHumansTxtPersonRows($values['humansTxtTeam']);
        }

        if (isset($values['humansTxtThanks'])) {
            $values['humansTxtThanks'] = self::_normalizeHumansTxtPersonRows($values['humansTxtThanks']);
        }

        if (isset($values['humansTxtLastUpdated'])) {
            $values['humansTxtLastUpdated'] = ($date = DateTimeHelper::toDateTime($values['humansTxtLastUpdated'])) !== false
                ? $date->format('Y-m-d')
                : null;
        }

        if (isset($values['adsTxtRecords'])) {
            $values['adsTxtRecords'] = \is_array($values['adsTxtRecords'])
                ? array_values(array_filter(array_map(
                    static fn(array $row): array => [
                        'domain' => self::_cleanAdsTxtDomain((string)($row['domain'] ?? '')),
                        'accountId' => trim((string)($row['accountId'] ?? '')),
                        'relationship' => ($row['relationship'] ?? self::ADS_RELATIONSHIP_DIRECT) === self::ADS_RELATIONSHIP_RESELLER
                            ? self::ADS_RELATIONSHIP_RESELLER
                            : self::ADS_RELATIONSHIP_DIRECT,
                        'certAuthorityId' => trim((string)($row['certAuthorityId'] ?? '')),
                    ],
                    $values['adsTxtRecords'],
                ), static fn(array $row): bool => $row['domain'] !== '' && $row['accountId'] !== ''))
                : [];
        }

        if (isset($values['adsTxtOwnerDomain'])) {
            $values['adsTxtOwnerDomain'] = self::_cleanAdsTxtDomain((string)$values['adsTxtOwnerDomain']) ?: null;
        }

        if (isset($values['adsTxtManagerDomains'])) {
            $values['adsTxtManagerDomains'] = \is_array($values['adsTxtManagerDomains'])
                ? array_values(array_filter(array_map(
                    static fn(array $row): array => [
                        'domain' => self::_cleanAdsTxtDomain((string)($row['domain'] ?? '')),
                        'country' => strtoupper(trim((string)($row['country'] ?? ''))),
                    ],
                    $values['adsTxtManagerDomains'],
                ), static fn(array $row): bool => $row['domain'] !== ''))
                : [];
        }

        if (isset($values['adsTxtContacts'])) {
            $values['adsTxtContacts'] = self::_normalizeSingleValueRows($values['adsTxtContacts']);
        }

        if (isset($values['adsTxtSubdomains'])) {
            $values['adsTxtSubdomains'] = \is_array($values['adsTxtSubdomains'])
                ? array_values(array_filter(array_map(
                    static fn(array $row): array => ['value' => self::_cleanAdsTxtDomain((string)($row['value'] ?? ''))],
                    $values['adsTxtSubdomains'],
                ), static fn(array $row): bool => $row['value'] !== ''))
                : [];
        }

        if (isset($values['securityTxtContacts'])) {
            $values['securityTxtContacts'] = self::_normalizeSingleValueRows($values['securityTxtContacts']);
        }

        if (isset($values['securityTxtEncryption'])) {
            $values['securityTxtEncryption'] = self::_normalizeSingleValueRows($values['securityTxtEncryption']);
        }

        if (isset($values['securityTxtAcknowledgments'])) {
            $values['securityTxtAcknowledgments'] = self::_normalizeSingleValueRows($values['securityTxtAcknowledgments']);
        }

        if (isset($values['securityTxtPolicy'])) {
            $values['securityTxtPolicy'] = self::_normalizeSingleValueRows($values['securityTxtPolicy']);
        }

        if (isset($values['securityTxtHiring'])) {
            $values['securityTxtHiring'] = self::_normalizeSingleValueRows($values['securityTxtHiring']);
        }

        if (isset($values['securityTxtExpires'])) {
            $values['securityTxtExpires'] = ($date = DateTimeHelper::toDateTime($values['securityTxtExpires'])) !== false
                ? $date->format(\DateTime::ATOM)
                : null;
        }

        foreach (['trustTxtBelongTo', 'trustTxtControl', 'trustTxtMember'] as $domainField) {
            if (isset($values[$domainField])) {
                $values[$domainField] = \is_array($values[$domainField])
                    ? array_values(array_filter(array_map(
                        static fn(array $row): array => ['value' => self::_cleanAdsTxtDomain((string)($row['value'] ?? ''))],
                        $values[$domainField],
                    ), static fn(array $row): bool => $row['value'] !== ''))
                    : [];
            }
        }

        foreach (['trustTxtSocial', 'trustTxtContact', 'trustTxtDisclosure', 'trustTxtCredential', 'trustTxtCopyright'] as $urlField) {
            if (isset($values[$urlField])) {
                $values[$urlField] = self::_normalizeSingleValueRows($values[$urlField]);
            }
        }

        if (isset($values['trustTxtDataTrainingAllowed'])) {
            $values['trustTxtDataTrainingAllowed'] = $values['trustTxtDataTrainingAllowed'] === 'yes' || $values['trustTxtDataTrainingAllowed'] === 'no'
                ? $values['trustTxtDataTrainingAllowed']
                : null;
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * Normalizes a posted `humansTxtTeam`/`humansTxtThanks` editable table - both are a list of
     * credited people with the same row shape, only the section header they end up under
     * differs ({@see \digitalastronaut\craftcoreseogeo\services\TxtFilesService::getHumansTxt()}).
     * Rows with no name are dropped entirely; a row with a name but nothing else is still kept.
     *
     * @param mixed $value
     * @return array<int, array{role: string, name: string, contact: string, from: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.11.0
     */
    private static function _normalizeHumansTxtPersonRows(mixed $value): array {
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
    private static function _cleanAdsTxtDomain(string $domain): string {
        $domain = trim($domain);
        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $domain) ?? $domain;
        $domain = explode('/', $domain, 2)[0];

        return strtolower($domain);
    }

    /**
     * Normalizes a posted single-column editable table (`{value: string}` rows) - the shape
     * shared by `adsTxtContacts` and every repeatable `security.txt` field
     * (`securityTxtEncryption`, `securityTxtAcknowledgments`, `securityTxtPolicy`,
     * `securityTxtHiring`). Rows with an empty value are dropped.
     *
     * @param mixed $value
     * @return array<int, array{value: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.13.0
     */
    private static function _normalizeSingleValueRows(mixed $value): array {
        if (!\is_array($value)) return [];

        return array_values(array_filter(array_map(
            static fn(array $row): array => ['value' => trim((string)($row['value'] ?? ''))],
            $value,
        ), static fn(array $row): bool => $row['value'] !== ''));
    }
}
