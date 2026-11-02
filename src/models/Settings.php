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
     * Keys ({@see \digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckInterface::getKey()})
     * of the SEO score checks an admin has switched off from `global-seo.twig`'s "SEO Score
     * Checks" table. Stored as an opt-out list rather than an opt-in one so a check a module
     * registers later ({@see \digitalastronaut\craftcoreseogeo\services\
     * SeoScoreService::EVENT_REGISTER_SEO_SCORE_CHECKS}) starts out enabled without needing a
     * migration. A disabled check is skipped entirely by `SeoScoreService::process()` -- it
     * contributes no weight and shows no row in an element's checklist, the same as if it had
     * never been registered.
     *
     * @var string[]
     * @since v1.28.0
     */
    public array $disabledSeoScoreChecks = [];

    /**
     * Whether `humans.txt` is served at all. Off by default - unlike `robots.txt`, there's no
     * useful default content for an unconfigured team-credits file, so publishing one is an
     * explicit opt-in.
     *
     * @since v1.11.0
     */
    public bool $enableHumansTxt = false;

    /**
     * Whether `ads.txt` is served at all. Off by default, same reasoning as `humans.txt` -
     * there's nothing useful to serve until at least one record is configured.
     *
     * @since v1.12.0
     */
    public bool $enableAdsTxt = false;

    /**
     * Whether `security.txt` is served at all (at both `.well-known/security.txt` and, for
     * backwards compatibility, the root `security.txt` - see `PluginTrait::registerSiteUrlRules()`). Off
     * by default, same reasoning as `humans.txt`/`ads.txt`. Even when on, `TxtFilesService`
     * won't actually serve content until the two fields RFC 9116 requires - at least one
     * contact entry and an expiry date, held on `TxtFileContent` - are both set; serving a file
     * that's missing either would be actively non-compliant, worse than not serving one.
     *
     * @since v1.13.0
     */
    public bool $enableSecurityTxt = false;

    /**
     * Whether `trust.txt` is served at all. Off by default - there's nothing useful to serve
     * until at least one field is configured. Note trust.txt v0.1
     * ({@link https://journallist.net/trust-txt/}) is a far less formal spec than the others
     * this plugin generates: no RFC, no IAB backing, maintained by a single organization
     * (JournalList) mainly for news publishers and the ad-supply-chain trust signaling ads.txt
     * doesn't cover.
     *
     * @since v1.14.0
     */
    public bool $enableTrustTxt = false;

    /**
     * Whether an entry's Markdown representation is served at all, at its own URL plus
     * `.md` ({@see \digitalastronaut\craftcoreseogeo\controllers\MarkdownController::actionRender()})
     * and shown in the entry edit page's sidebar ({@see \digitalastronaut\craftcoreseogeo\
     * PluginTrait::registerMarkdownSidebar()}). On by default, unlike the txt-file toggles below
     * - this plugin already served `.md` pages before this setting existed, so defaulting it off
     * would silently remove a feature from every existing install. A section can still opt back
     * out on its own even while this stays on - see `ContentSeo::$markdownEnabled` - mirroring
     * `$enableSitemap`'s own two-tier (sitewide, then per-section) shape.
     *
     * @since v1.39.0
     */
    public bool $enableMarkdown = true;

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
        if (isset($values['disabledSeoScoreChecks'])) {
            $values['disabledSeoScoreChecks'] = \is_array($values['disabledSeoScoreChecks'])
                ? array_values(array_unique(array_map('strval', $values['disabledSeoScoreChecks'])))
                : [];
        }

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

        parent::setAttributes($values, $safeOnly);
    }
}
