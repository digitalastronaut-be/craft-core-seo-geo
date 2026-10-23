<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo;

use Throwable;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ElementEvent;
use craft\events\ExceptionEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\SectionEvent;
use craft\events\TemplateEvent;
use craft\helpers\App;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Entries;
use craft\services\Fields;
use craft\web\ErrorHandler;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;

use yii\base\Event;
use yii\web\HttpException;

use digitalastronaut\craftcoreseogeo\elements\StructuredData;
use digitalastronaut\craftcoreseogeo\fields\SeoField;
use digitalastronaut\craftcoreseogeo\fields\StructuredDataField;
use digitalastronaut\craftcoreseogeo\models\Settings;
use digitalastronaut\craftcoreseogeo\seochecks\CanonicalUrlCheck;
use digitalastronaut\craftcoreseogeo\seochecks\DuplicateContentCheck;
use digitalastronaut\craftcoreseogeo\seochecks\IndexableCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoImageCheck;
use digitalastronaut\craftcoreseogeo\seochecks\ValidDescriptionCheck;
use digitalastronaut\craftcoreseogeo\seochecks\ValidTitleCheck;
use digitalastronaut\craftcoreseogeo\services\MarkdownService;
use digitalastronaut\craftcoreseogeo\services\SeoScoreService;
use digitalastronaut\craftcoreseogeo\twig\CoreSeoGeoExtension;
use digitalastronaut\craftcoreseogeo\variables\CoreSeoGeoVariable;
use digitalastronaut\craftcoreseogeo\web\assets\admin\AdminAssetBundle;
use digitalastronaut\craftcoreseogeo\web\assets\schema\SchemaAssetBundle;

/**
 * Class PluginTrait
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
trait PluginTrait {
    /**
     * @return void
     */
    protected function registerEvents(): void {
        $this->registerSharedEvents();

        if (Craft::$app->request->isCpRequest) $this->registerCpEvents();
        if (Craft::$app->request->isConsoleRequest) $this->controllerNamespace = 'digitalastronaut\\craftcoreseogeo\\console\\controllers';
        if (Craft::$app->request->isSiteRequest) $this->registerSiteEvents();
    }

    /**
     * @return void
     */
    protected function registerSharedEvents(): void {
        $this->registerFieldTypes();
        $this->registerElementTypes();
        $this->registerTemplateVariable();
        $this->registerSeoScoreChecks();
        $this->registerSeoScoreCalculation();
        $this->registerContentSeoSync();
        $this->registerStructuredDataSync();
    }

    /**
     * Keeps `coreseogeo_seodata` (`ContentSeoService`) in sync with the site's actual sections.
     * Registered unconditionally (not just for CP requests) since `EVENT_AFTER_SAVE_SECTION`/
     * `EVENT_AFTER_DELETE_SECTION` fire from console requests too - a `project-config/apply` on
     * deploy saves/deletes sections the same way the CP does, and needs the same reaction.
     *
     * The full reconciliation (`syncSections()`) only runs for CP/console requests: it's an
     * admin-facing safety net against drift, not something an anonymous site visitor's GET
     * request should be paying the extra queries (or risking a write against a read-only DB
     * replica) for on every page load - the targeted save/delete handlers above already keep the
     * table current for everything that goes through normal section save/delete.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    protected function registerContentSeoSync(): void {
        Event::on(
            Entries::class,
            Entries::EVENT_AFTER_SAVE_SECTION,
            function(SectionEvent $event) {
                CoreSeoGeo::getInstance()->getContentSeo()->ensureRowsForSection($event->section);
            }
        );

        Event::on(
            Entries::class,
            Entries::EVENT_AFTER_DELETE_SECTION,
            function(SectionEvent $event) {
                CoreSeoGeo::getInstance()->getContentSeo()->deleteRowsForSection($event->section->handle);
            }
        );

        if (!Craft::$app->request->isSiteRequest) {
            CoreSeoGeo::getInstance()->getContentSeo()->syncSections();
        }
    }

    /**
     * @return void
     */
    protected function registerFieldTypes(): void {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = SeoField::class;
                $event->types[] = StructuredDataField::class;
            }
        );
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function registerElementTypes(): void {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = StructuredData::class;
            }
        );
    }

    /**
     * Registers the built-in SEO score checks. Adding or removing one is purely a matter of
     * adding or removing a line here -- nothing else in the plugin references this list.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    protected function registerSeoScoreChecks(): void {
        Event::on(
            SeoScoreService::class,
            SeoScoreService::EVENT_REGISTER_SEO_SCORE_CHECKS,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = ValidTitleCheck::class;
                $event->types[] = ValidDescriptionCheck::class;
                $event->types[] = SeoImageCheck::class;
                $event->types[] = CanonicalUrlCheck::class;
                $event->types[] = IndexableCheck::class;
                $event->types[] = DuplicateContentCheck::class;
            }
        );
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    protected function registerSeoScoreCalculation(): void {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $element = $event->element;

                if ($element->getIsDraft() || $element->getIsRevision()) {
                    return;
                }

                $seoScore = CoreSeoGeo::getInstance()->getSeoScore();

                foreach ($this->_getSeoFields($element) as $field) {
                    $seoScore->markPending($element, (int)$field->id);
                    $seoScore->calculate($element, (int)$field->id);
                }
            }
        );
    }

    /**
     * Keeps `coreseogeo_structureddata` in sync with every entry's own section mappings -
     * independent of whether that entry type's field layout even has a `StructuredDataField` to
     * display the result, since the computation itself no longer belongs to any one field
     * instance (see `StructuredDataMappingService::syncEntry()`). Registered unconditionally
     * (not just for CP requests), the same reasoning as `registerContentSeoSync()`: a front-end
     * form or a console `project-config/apply`/`resave/entries` can save entries too.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    protected function registerStructuredDataSync(): void {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $element = $event->element;

                if (!$element instanceof Entry || $element->getIsRevision()) return;

                CoreSeoGeo::getInstance()->getStructuredDataMappings()->syncEntry($element);
            }
        );
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    protected function registerSeoScoreSidebar(): void {
        Event::on(
            Element::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function(DefineHtmlEvent $event) {
                /** @var ElementInterface $element */
                $element = $event->sender;
                $seoScore = CoreSeoGeo::getInstance()->getSeoScore();

                foreach ($this->_getSeoFields($element) as $field) {
                    $event->html .= $this->_seoScoreSidebarHtml($field, $element, $seoScore);
                }
            }
        );
    }

    /**
     * @param ElementInterface $element
     * @return SeoField[]
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    private function _getSeoFields(ElementInterface $element): array {
        return SeoField::getFieldsOn($element);
    }

    /**
     * @param SeoField $field
     * @param ElementInterface $element
     * @param SeoScoreService $seoScore
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    private function _seoScoreSidebarHtml(SeoField $field, ElementInterface $element, SeoScoreService $seoScore): string {
        $score = $seoScore->getScore($element, (int)$field->id);

        $rows = [
            Craft::t('core-seo-geo', 'Score') => match (true) {
                $score === null => Craft::t('core-seo-geo', 'Not calculated'),
                $score->pending && $score->score === null => Craft::t('core-seo-geo', 'Calculating...'),
                $score->pending => Craft::t('core-seo-geo', '{score}% (recalculating...)', ['score' => $score->score]),
                default => "{$score->score}%",
            },
        ];

        foreach ($score?->checks ?? [] as $check) {
            $status = match (true) {
                !$check['applicable'] => Craft::t('core-seo-geo', 'N/A'),
                $check['valid'] => Craft::t('core-seo-geo', 'Pass'),
                default => Craft::t('core-seo-geo', 'Fail'),
            };

            $rows[$check['label']] = Html::encode("{$status}: {$check['message']}");
        }

        return Html::tag('fieldset',
            Html::tag('legend', Html::encode(Craft::t('core-seo-geo', '{field} Score', [
                'field' => $field->name,
            ])), ['class' => 'h6', 'style' => 'padding-block: 16px;']) .
            Cp::metadataHtml($rows)
        );
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    protected function registerMarkdownSidebar(): void {
        Event::on(
            Element::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function(DefineHtmlEvent $event) {
                $element = $event->sender;

                if (!$element instanceof Entry || $element->uri === null) {
                    return;
                }

                $event->html .= $this->_markdownSidebarHtml($element);
            }
        );
    }

    /**
     * @param Entry $entry
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    private function _markdownSidebarHtml(Entry $entry): string {
        $result = CoreSeoGeo::getInstance()->getMarkdown()->getCachedResult($entry);

        $rows = [
            Craft::t('core-seo-geo', 'Status') => match (true) {
                $result === null => Craft::t('core-seo-geo', 'Not yet generated'),
                $result['failureReason'] !== null => Craft::t('core-seo-geo', 'Failed: {reason}', [
                    'reason' => $result['failureReason'],
                ]),
                default => Craft::t('core-seo-geo', 'Cached'),
            },
        ];

        if ($result !== null) {
            $rows[Craft::t('core-seo-geo', 'Generated')] = Craft::$app->getFormatter()->asRelativeTime($result['date']);
        }

        if ($result !== null && $result['failureReason'] === null) {
            $rows[Craft::t('core-seo-geo', 'Tokens (est.)')] = (string)MarkdownService::estimateTokens($result['markdown']);
        }

        return Html::tag('fieldset',
            Html::tag('legend', Craft::t('core-seo-geo', 'Markdown'), ['class' => 'h6', 'style' => 'padding-block: 16px;']) .
            Cp::metadataHtml($rows)
        );
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function registerTemplateVariable(): void {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('coreSeoGeo', CoreSeoGeoVariable::class);
            }
        );
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    protected function registerSiteEvents(): void {
        $this->registerSiteTemplateRoot();
        $this->registerSeoMetaInjection();
        $this->registerTrackingScriptsInjection();
        $this->registerTwigExtension();
        $this->registerSiteUrlRules();
        $this->registerRedirectHandling();
    }

    /**
     * Registers the 404-interception listener: on every unhandled 404, try to resolve it
     * against `coreseogeo_redirects` first, falling back to logging it in `coreseogeo_404s`
     * when nothing matches. See `redirects-roadmap.md` Step 2 for why `ErrorHandler::
     * EVENT_BEFORE_HANDLE_EXCEPTION` is the right (and only) point to catch this - Craft's
     * router doesn't know a request is unroutable until deep inside normal controller/
     * template resolution, by which point this exception already exists. Already site-
     * request-gated by `registerEvents()` calling `registerSiteEvents()` only for one, so
     * this never registers at all for a CP or console request - no extra guard needed here.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    protected function registerRedirectHandling(): void {
        if (!CoreSeoGeo::getInstance()->getSettings()->enableRedirects) return;

        Event::on(
            ErrorHandler::class,
            ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION,
            function(ExceptionEvent $event) {
                $this->_handleNotFoundException($event->exception);
            }
        );
    }

    /**
     * Resolves one 404 exception: a matched redirect issues the actual redirect/410 response
     * and ends the request right here, via `Craft::$app->end()`, the exact same mechanism
     * Craft's own built-in `config/redirects.php` rules use to short-circuit from this same
     * event - so Craft's normal 404 error page never renders for a URI this plugin handled.
     * No match falls through silently, letting `parent::handleException()` render Craft's
     * normal 404 page right after this listener returns.
     *
     * @param Throwable $exception
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    private function _handleNotFoundException(Throwable $exception): void {
        if (!$exception instanceof HttpException || $exception->statusCode !== 404) return;

        $request = Craft::$app->getRequest();

        // A Live Preview iframe hitting a stale URL shouldn't log or redirect.
        if ($request->getIsPreview() || $request->getIsLivePreview()) return;

        try {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
            $uri = $request->getUrl();
        } catch (Throwable) {
            return;
        }

        $match = CoreSeoGeo::getInstance()->getRedirects()->handleNotFound($uri, $siteId, $request->getReferrer());

        if ($match === null) return;

        $response = Craft::$app->getResponse();

        if ($match->destinationUri === null) {
            // `Redirect::STATUS_CODE_GONE` - no destination to send the visitor to.
            $response->setStatusCode($match->statusCode);
        } else {
            $url = UrlHelper::isAbsoluteUrl($match->destinationUri)
                ? $match->destinationUri
                : UrlHelper::siteUrl($match->destinationUri, null, null, $siteId);

            $response->redirect($url, $match->statusCode);
        }

        Craft::$app->end();
    }

    /**
     * Registers every front-end route this plugin adds, as a single
     * `EVENT_REGISTER_SITE_URL_RULES` listener.
     *
     * - `<uri>.md` and `sitemap-<group>.xml` both keep their suffix inside the placeholder's
     *   own capture (and so exclude `.` from what it can match) rather than as a literal
     *   top-level suffix - confirmed empirically that a bare, top-level rule placing the suffix
     *   outside the capture (e.g. `<slug:[^.]+>\.md`) never matches under Craft's routing, while
     *   the exact same suffix placed inside the capture's pattern does. The same
     *   suffix-outside-the-capture form works fine once nested under a literal path segment, so
     *   this only bites a rule that's nothing but a placeholder at the top level.
     * - `sitemap.xml`/`sitemap-<group>.xml` are skipped entirely (not even registered as a route
     *   that 404s) when either `disallowRobots` (Craft's "don't let search engines see this
     *   environment" signal, skipped in dev mode since there's no public crawler to protect and
     *   developers still need to see it) or `enableSitemap` says they shouldn't exist: a crawler
     *   can still fetch a public URL even when every page on it carries a noindex header -
     *   robots directives govern indexing, not access - so relying on the header alone while
     *   still serving the file would leave a staging site's full URL inventory enumerable.
     * - `robots.txt` inverts that: `disallowRobots` *forces* it to register rather than removing
     *   it, since a disallowed environment still needs to actively say "stay out" - a missing
     *   file (or a 404) is commonly read by crawlers as "no restrictions", the opposite of what
     *   hiding the sitemap achieves.
     * - `security.txt` registers at both `.well-known/security.txt` (the location RFC 9116
     *   prefers) and the root (kept only for older tooling that still checks there - RFC 9116 §3
     *   calls root-only placement deprecated).
     * - `humans.txt`/`ads.txt`/`trust.txt`/`llms.txt`/`llms-full.txt` have no indexing
     *   consequence, so each is gated purely on its own settings toggle.
     *
     * @return void
     *
     * @author      Digitalastronaut
     */
    protected function registerSiteUrlRules(): void {
        $settings = CoreSeoGeo::getInstance()->getSettings();
        $robotsDisallowed = Craft::$app->getConfig()->getGeneral()->disallowRobots && !App::devMode();

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) use ($settings, $robotsDisallowed) {
                $event->rules['<slug:[^.]+\.md>'] = 'core-seo-geo/markdown/render';
                $event->rules['manifest.webmanifest'] = 'core-seo-geo/favicon/manifest';

                if (!$robotsDisallowed && $settings->enableSitemap) {
                    $event->rules['sitemap.xml'] = 'core-seo-geo/sitemap/index';
                    $event->rules['sitemap-<group:[\w-]+\.xml>'] = 'core-seo-geo/sitemap/group';
                }

                if ($robotsDisallowed || $settings->enableRobotsTxt) {
                    $event->rules['robots.txt'] = 'core-seo-geo/robots/index';
                }

                foreach ([
                    'enableHumansTxt' => ['humans.txt' => 'core-seo-geo/humans/index'],
                    'enableAdsTxt' => ['ads.txt' => 'core-seo-geo/ads/index'],
                    'enableSecurityTxt' => [
                        '.well-known/security.txt' => 'core-seo-geo/security/index',
                        'security.txt' => 'core-seo-geo/security/index',
                    ],
                    'enableTrustTxt' => ['trust.txt' => 'core-seo-geo/trust/index'],
                    'enableLlmsTxt' => ['llms.txt' => 'core-seo-geo/llms/index'],
                    'enableLlmsFullTxt' => ['llms-full.txt' => 'core-seo-geo/llms-full/index'],
                ] as $flag => $rules) {
                    if ($settings->$flag) $event->rules = array_merge($event->rules, $rules);
                }
            }
        );
    }

    /**
     * Registers this plugin's Twig extension (`CoreSeoGeoExtension`), currently the
     * `{% llmContent %}`/`{% excludeLlmContent %}` tags templates use to mark which
     * regions of a page are its Markdown content.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    protected function registerTwigExtension(): void {
        Craft::$app->getView()->registerTwigExtension(new CoreSeoGeoExtension());
    }

    /**
     * The base `Plugin` class only registers this plugin's `templates/` directory as a CP
     * template root (see `craft\base\Plugin::init()`), so `core-seo-geo/...` template paths
     * resolve in the control panel but not on the front end. The `seo` hook below renders one
     * of this plugin's templates from a site request, so it needs that same root registered
     * for site requests too.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    protected function registerSiteTemplateRoot(): void {
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';

                if (is_dir($baseDir)) $event->roots[$this->id] = $baseDir;
            }
        );
    }

    /**
     * Automatically injects the current entry's `SeoField` tags into `<head>`, with no template
     * markup required: it inspects the full rendered page output after an entry's own template
     * has rendered (`entry` is set in `$event->variables` the same way `Entry::getRoute()` sets
     * it) and splices the tags in just before the first `</head>`.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    protected function registerSeoMetaInjection(): void {
        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event) {
                $tags = $this->_renderSeoTags($event->variables['entry'] ?? null);

                if ($tags === null) return;

                $pos = stripos($event->output, '</head>');

                if ($pos === false) return;

                $event->output = substr_replace($event->output, $tags, $pos, 0);
            }
        );
    }

    /**
     * Splices every enabled tracking script's snippet into the rendered page, each at the DOM
     * location it was configured for - the actual splicing is `TrackingScriptsService::injectIntoHtml()`'s
     * job, this just wires it into the same full-output hook `registerSeoMetaInjection()` uses.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    protected function registerTrackingScriptsInjection(): void {
        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event) {
                $event->output = CoreSeoGeo::getInstance()->getTrackingScripts()->injectIntoHtml($event->output);
            }
        );
    }

    /**
     * @param mixed $element
     * @return string|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    private function _renderSeoTags(mixed $element): ?string {
        if (!$element instanceof ElementInterface) return null;

        $settings = CoreSeoGeo::getInstance()->getSettings();
        $seo = CoreSeoGeo::getInstance()->getContentSeo()->resolveSeoData($element);

        if ($seo === null) return null;

        $hreflangAlternates = $settings->enableHreflang
            ? CoreSeoGeo::getInstance()->getHreflang()->getAlternates($element)
            : [];

        $image = $seo->getImage()->one() ?? CoreSeoGeo::getInstance()->getContentSeo()->getFallbackImage($element);

        return Craft::$app->getView()->renderTemplate('core-seo-geo/meta/_tags', [
            'seo' => $seo,
            'element' => $element,
            'image' => $image,
            'hreflangAlternates' => $hreflangAlternates,
            'favicon' => $this->_getFaviconTags($settings),
            'settings' => $settings,
        ]);
    }

    /**
     * @param Settings $settings
     * @return array{pngUrl: string, faviconIcoUrl: string|null, appleTouchIconUrl: string, manifestUrl: string}|null
     * `null` if no favicon asset is configured.
     *
     * @author      Digitalastronaut
     * @since       v1.0.8
     */
    private function _getFaviconTags(Settings $settings): ?array {
        $asset = $settings->getFaviconAsset();

        if ($asset === null) {
            return null;
        }

        $favicon = CoreSeoGeo::getInstance()->getFavicon();

        return [
            'pngUrl' => $favicon->getFaviconPngUrl($asset),
            'faviconIcoUrl' => $favicon->getFaviconIcoUrl($asset),
            'appleTouchIconUrl' => $favicon->getAppleTouchIconUrl($asset),
            'manifestUrl' => UrlHelper::siteUrl('manifest.webmanifest'),
        ];
    }

    /**
     * @return void
     */
    protected function registerCpEvents(): void {
        $this->registerCpAssets();
        $this->registerCpUrlRules();
        $this->registerSeoScoreSidebar();
        $this->registerMarkdownSidebar();
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function registerCpUrlRules(): void {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['core-seo-geo'] = ['template' => 'core-seo-geo/structured-data/_index.twig'];
                $event->rules['core-seo-geo/structured-data'] = ['template' => 'core-seo-geo/structured-data/_index.twig'];
                $event->rules['core-seo-geo/structured-data/<elementId:\d+>'] = 'elements/edit';

                $event->rules['core-seo-geo/sitemap'] = ['template' => 'core-seo-geo/sitemap/_index.twig'];

                $event->rules['core-seo-geo/redirects'] = 'core-seo-geo/redirects/index';
                $event->rules['core-seo-geo/redirects/not-found-log'] = 'core-seo-geo/redirects/not-found-log';
                $event->rules['core-seo-geo/redirects/new'] = 'core-seo-geo/redirects/edit';
                $event->rules['core-seo-geo/redirects/<id:\d+>'] = 'core-seo-geo/redirects/edit';

                $event->rules['core-seo-geo/settings'] = 'core-seo-geo/content-seo/global';
                $event->rules['core-seo-geo/settings/content-seo'] = 'core-seo-geo/content-seo/index';
                $event->rules['core-seo-geo/settings/content-seo/<sectionHandle:[\w]+>'] = 'core-seo-geo/content-seo/section';
                $event->rules['core-seo-geo/settings/content-seo/<sectionHandle:[\w]+>/mappings/new'] = 'core-seo-geo/content-seo/edit-mapping';
                $event->rules['core-seo-geo/settings/content-seo/<sectionHandle:[\w]+>/mappings/<mappingId:\d+>'] = 'core-seo-geo/content-seo/edit-mapping';
                $event->rules['core-seo-geo/settings/global-seo'] = 'core-seo-geo/content-seo/global';
                $event->rules['core-seo-geo/settings/global/<type:organization|website|local\-business|person>'] = 'core-seo-geo/global-structured-data/edit';
                $event->rules['core-seo-geo/settings/txt/robots'] = ['route' => 'core-seo-geo/txt-files/edit', 'params' => ['type' => 'robots']];
                $event->rules['core-seo-geo/settings/txt/humans'] = ['route' => 'core-seo-geo/txt-files/edit', 'params' => ['type' => 'humans']];
                $event->rules['core-seo-geo/settings/txt/ads'] = ['route' => 'core-seo-geo/txt-files/edit', 'params' => ['type' => 'ads']];
                $event->rules['core-seo-geo/settings/txt/security'] = ['route' => 'core-seo-geo/txt-files/edit', 'params' => ['type' => 'security']];
                $event->rules['core-seo-geo/settings/txt/trust'] = ['route' => 'core-seo-geo/txt-files/edit', 'params' => ['type' => 'trust']];
                $event->rules['core-seo-geo/settings/txt/llms'] = ['route' => 'core-seo-geo/txt-files/edit', 'params' => ['type' => 'llms']];
                $event->rules['core-seo-geo/settings/txt/llms-full'] = ['route' => 'core-seo-geo/txt-files/edit', 'params' => ['type' => 'llms-full']];
                $event->rules['core-seo-geo/settings/tracking-scripts'] = 'core-seo-geo/tracking-scripts/index';
                $event->rules['core-seo-geo/settings/tracking-scripts/new'] = 'core-seo-geo/tracking-scripts/edit';
                $event->rules['core-seo-geo/settings/tracking-scripts/<id:\d+>'] = 'core-seo-geo/tracking-scripts/edit';
                $event->rules['core-seo-geo/settings/appearance'] = 'core-seo-geo/appearance/edit';
                $event->rules['core-seo-geo/settings/structured-data'] = ['template' => 'core-seo-geo/settings/structured-data.twig'];
            }
        );
    }

    /**
     * @return void
     */
    protected function registerCpAssets(): void {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function() {
                $this->registerAdminAssets();
            }
        );
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function registerAdminAssets(): void {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(AdminAssetBundle::class);

        CoreSeoGeo::getInstance()->getVite()->register('src/web/assets/admin/admin.js', false);

        $this->registerSchemaRegistryAsset($view);
    }

    /**
     * @param View $view
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function registerSchemaRegistryAsset(View $view): void {
        $bundle = $view->registerAssetBundle(SchemaAssetBundle::class);

        $view->registerJs(
            'window.CoreSeoGeoSchemaRegistryUrl = ' . Json::encode("{$bundle->baseUrl}/registry.json", JSON_UNESCAPED_SLASHES) . ';',
            View::POS_HEAD,
        );
    }
}
