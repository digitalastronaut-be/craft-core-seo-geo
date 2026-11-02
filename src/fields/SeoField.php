<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\fields;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\elements\Entry;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\services\ContentSeoService;
use digitalastronaut\craftcoreseogeo\web\assets\images\ImagesAssetBundle;

use yii\db\Schema;
use yii\validators\UrlValidator;

/**
 * Class SeoField
 *
 * The title/description block config it builds its CP input from (and resolves its meta
 * title/description against) comes from the `coreseogeo_seodata` row for the element's section,
 * falling back to the site's global bundle row when the section has none of its own - not from
 * this field's own settings. See {@see _resolveFormatRows()}. Its own settings are which input
 * sections {@see getSettingsHtml()} lets editors see at all - see {@see $enabledInputFields} -
 * and, within the social preview section, which platform tabs are offered - see
 * {@see $enabledPreviewPlatforms}.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class SeoField extends Field implements PreviewableFieldInterface {
    /**
     * @since v1.5.0
     */
    public const string PREVIEW_GOOGLE = 'google';
    public const string PREVIEW_FACEBOOK = 'facebook';
    public const string PREVIEW_TWITTER = 'twitter';
    public const string PREVIEW_LINKEDIN = 'linkedin';
    public const string PREVIEW_WHATSAPP = 'whatsapp';
    public const string PREVIEW_MESSENGER = 'messenger';
    public const string PREVIEW_DISCORD = 'discord';

    /**
     * Every platform `_previews.twig` can show a preview tab for, in display order - the single
     * list both this field's settings checkboxes and the component's own tab list filter
     * against, so neither can drift out of sync with the other.
     *
     * @since v1.5.0
     */
    public const array PREVIEW_PLATFORMS = [
        self::PREVIEW_GOOGLE,
        self::PREVIEW_FACEBOOK,
        self::PREVIEW_TWITTER,
        self::PREVIEW_LINKEDIN,
        self::PREVIEW_WHATSAPP,
        self::PREVIEW_MESSENGER,
        self::PREVIEW_DISCORD,
    ];

    /**
     * Which of {@see PREVIEW_PLATFORMS} this field instance shows a preview tab for. Defaults to
     * every platform, so a field instance saved before this setting existed keeps showing every
     * tab it already did.
     *
     * @since v1.5.0
     */
    public array $enabledPreviewPlatforms = self::PREVIEW_PLATFORMS;

    /**
     * @since v1.38.0
     */
    public const string FIELD_IMAGE = 'image';
    public const string FIELD_TITLE = 'title';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_CANONICAL_URL = 'canonicalUrl';
    public const string FIELD_ROBOTS = 'robots';
    public const string FIELD_EXCLUDE_FROM_SITEMAP = 'excludeFromSitemap';
    public const string FIELD_PREVIEWS = 'previews';

    /**
     * Every input section `_input.twig` can show or hide, in display order - the single list
     * both this field's settings checkboxes and the input template filter against, so neither
     * can drift out of sync with the other.
     *
     * @since v1.38.0
     */
    public const array INPUT_FIELDS = [
        self::FIELD_IMAGE,
        self::FIELD_TITLE,
        self::FIELD_DESCRIPTION,
        self::FIELD_CANONICAL_URL,
        self::FIELD_ROBOTS,
        self::FIELD_EXCLUDE_FROM_SITEMAP,
        self::FIELD_PREVIEWS,
    ];

    /**
     * Which of {@see INPUT_FIELDS} this field instance's input renders at all - e.g. leaving only
     * {@see FIELD_TITLE} and {@see FIELD_DESCRIPTION} enabled restricts editors to just those two,
     * or only {@see FIELD_PREVIEWS} to just the social preview. Defaults to every section, so a
     * field instance saved before this setting existed keeps showing every section it already
     * did.
     *
     * @since v1.38.0
     */
    public array $enabledInputFields = self::INPUT_FIELDS;

    /**
     * Drops the settings this field used to have (`titleFormatRows`/`descriptionFormatRows`/
     * `showCanonicalField`) before they'd otherwise be assigned onto properties that no longer
     * exist - an existing field instance's project config/DB-stored settings still carry them
     * until it's next resaved, and without this, building the field from that stored config
     * (e.g. on every CP request, via `Fields::getAllFields()`) throws an
     * `UnknownPropertyException`. Same pattern `craft\base\Field::__construct()` itself uses to
     * drop its own retired `columnPrefix` setting.
     *
     * @param array $config
     *
     * @author      Digitalastronaut
     * @since       v1.2.0
     */
    public function __construct($config = []) {
        unset($config['titleFormatRows'], $config['descriptionFormatRows'], $config['showCanonicalField']);

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function displayName(): string {
        return Craft::t('core-seo-geo', 'SEO/GEO');
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function icon(): string {
        return '@digitalastronaut/craftcoreseogeo/web/assets/icons/seo-geo-field-icon.svg';
    }

    /**
     * @inheritdoc
     * 
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function phpType(): string {
        return SeoData::class;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function dbType(): string {
        return Schema::TYPE_JSON;
    }

    /**
     * Returns every `SeoField` on the given element's field layout. Shared by
     * `PluginTrait::_getSeoFields()` (score calc, meta injection) and `SitemapService` (sitemap
     * eligibility) so both stay in lockstep with a single definition of "which fields count."
     *
     * @param ElementInterface $element
     * @return SeoField[]
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public static function getFieldsOn(ElementInterface $element): array {
        $fieldLayout = $element->getFieldLayout();

        if ($fieldLayout === null) {
            return [];
        }

        return array_values(array_filter(
            $fieldLayout->getCustomFields(),
            static fn($field): bool => $field instanceof self,
        ));
    }

    /**
     * Static so `ContentSeoController` can reuse this option list for the per-section Content
     * SEO settings page too, which has no `SeoField` instance of its own to call it on.
     *
     * @return array<int, array{label: string, value: string, data: array{hint: string}}>
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function getRobotsOptions(): array {
        return [
            ['optgroup' => Craft::t('core-seo-geo', 'Robot meta tags')],
            [
                'label' => Craft::t('core-seo-geo', 'No Index'),
                'value' => SeoData::ROBOTS_NOINDEX,
                'data' => ['hint' => Craft::t('core-seo-geo', "Hide this page from search results and its cached link.")],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Follow'),
                'value' => SeoData::ROBOTS_NOFOLLOW,
                'data' => ['hint' => Craft::t('core-seo-geo', "Don't follow the links on this page.")],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Archive'),
                'value' => SeoData::ROBOTS_NOARCHIVE,
                'data' => ['hint' => Craft::t('core-seo-geo', 'Hide the cached link in search results.')],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Snippet'),
                'value' => SeoData::ROBOTS_NOSNIPPET,
                'data' => ['hint' => Craft::t('core-seo-geo', 'Hide the text or video snippet. A thumbnail may still show.')],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Translate'),
                'value' => SeoData::ROBOTS_NOTRANSLATE,
                'data' => ['hint' => Craft::t('core-seo-geo', "Don't offer translation of this page.")],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Image Index'),
                'value' => SeoData::ROBOTS_NOIMAGEINDEX,
                'data' => ['hint' => Craft::t('core-seo-geo', "Don't index images on this page.")],
            ],
            ['optgroup' => Craft::t('core-seo-geo', 'AI meta tags')],
            [
                'label' => Craft::t('core-seo-geo', 'No AI'),
                'value' => SeoData::ROBOTS_NOAI,
                'data' => ['hint' => Craft::t('core-seo-geo', "Ask generative AI crawlers not to use this page's text to train or answer prompts.")],
            ],
            [
                'label' => Craft::t('core-seo-geo', 'No Image AI'),
                'value' => SeoData::ROBOTS_NOIMAGEAI,
                'data' => ['hint' => Craft::t('core-seo-geo', "Ask generative AI crawlers not to use this page's images to train or answer prompts.")],
            ],
        ];
    }

    /**
     * Display label for each of {@see PREVIEW_PLATFORMS}, keyed the same way - the single place
     * both this field's settings checkboxes ({@see getSettingsHtml()}) and `_previews.twig`'s own
     * tab list read their labels from.
     *
     * @return array<string, string>
     *
     * @author      Digitalastronaut
     * @since       v1.5.0
     */
    public static function getPreviewPlatformLabels(): array {
        return [
            self::PREVIEW_GOOGLE => Craft::t('core-seo-geo', 'Google'),
            self::PREVIEW_FACEBOOK => Craft::t('core-seo-geo', 'Facebook'),
            self::PREVIEW_TWITTER => Craft::t('core-seo-geo', 'X'),
            self::PREVIEW_LINKEDIN => Craft::t('core-seo-geo', 'LinkedIn'),
            self::PREVIEW_WHATSAPP => Craft::t('core-seo-geo', 'WhatsApp'),
            self::PREVIEW_MESSENGER => Craft::t('core-seo-geo', 'Messenger'),
            self::PREVIEW_DISCORD => Craft::t('core-seo-geo', 'Discord'),
        ];
    }

    /**
     * Display label for each of {@see INPUT_FIELDS}, keyed the same way - the single place both
     * this field's settings checkboxes ({@see getSettingsHtml()}) and `_input.twig`'s own section
     * guards read their labels/keys from.
     *
     * @return array<string, string>
     *
     * @author      Digitalastronaut
     * @since       v1.38.0
     */
    public static function getInputFieldLabels(): array {
        return [
            self::FIELD_IMAGE => Craft::t('core-seo-geo', 'Image'),
            self::FIELD_TITLE => Craft::t('core-seo-geo', 'Title'),
            self::FIELD_DESCRIPTION => Craft::t('core-seo-geo', 'Description'),
            self::FIELD_CANONICAL_URL => Craft::t('core-seo-geo', 'Canonical URL'),
            self::FIELD_ROBOTS => Craft::t('core-seo-geo', 'Robots/AI Visibility'),
            self::FIELD_EXCLUDE_FROM_SITEMAP => Craft::t('core-seo-geo', 'Exclude from sitemap'),
            self::FIELD_PREVIEWS => Craft::t('core-seo-geo', 'Social preview'),
        ];
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.5.0
     */
    public function getSettingsHtml(): ?string {
        $fieldLabels = self::getInputFieldLabels();
        $previewLabels = self::getPreviewPlatformLabels();

        return
            Cp::checkboxGroupFieldHtml([
                'label' => Craft::t('core-seo-geo', 'Enabled Fields'),
                'instructions' => Craft::t('core-seo-geo', 'Which of these sections should editors see on this field?'),
                'name' => 'enabledInputFields',
                'options' => array_map(
                    static fn(string $field, string $label): array => ['label' => $label, 'value' => $field],
                    array_keys($fieldLabels),
                    $fieldLabels,
                ),
                'values' => $this->enabledInputFields,
            ]) .
            Cp::checkboxGroupFieldHtml([
                'label' => Craft::t('core-seo-geo', 'Enabled Previews'),
                'instructions' => Craft::t('core-seo-geo', 'Which social preview tabs should editors be able to select on this field?'),
                'name' => 'enabledPreviewPlatforms',
                'options' => array_map(
                    static fn(string $platform, string $label): array => ['label' => $label, 'value' => $platform],
                    array_keys($previewLabels),
                    $previewLabels,
                ),
                'values' => $this->enabledPreviewPlatforms,
            ]);
    }

    /**
     * @inheritdoc
     *
     * Requires at least one of {@see PREVIEW_PLATFORMS}/{@see INPUT_FIELDS} to stay enabled -
     * with none, the field's previews pane would have no tabs left to show, or the input would
     * have nothing left to render at all.
     *
     * @author      Digitalastronaut
     * @since       v1.5.0
     */
    protected function defineRules(): array {
        $rules = parent::defineRules();

        $rules[] = [['enabledPreviewPlatforms', 'enabledInputFields'], 'required'];
        $rules[] = [['enabledPreviewPlatforms'], 'in', 'range' => self::PREVIEW_PLATFORMS, 'allowArray' => true];
        $rules[] = [['enabledInputFields'], 'in', 'range' => self::INPUT_FIELDS, 'allowArray' => true];

        return $rules;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed {
        if ($value instanceof SeoData) return $value;
        if (\is_string($value)) $value = Json::decodeIfJson($value);
        if (!\is_array($value)) $value = [];

        [$titleFormatRows, $descriptionFormatRows] = self::_resolveFormatRows($element);

        [$titleRows, $metaTitle] = self::_resolveFormat($titleFormatRows, $value['titleRows'] ?? null, $element);
        [$descriptionRows, $metaDescription] = self::_resolveFormat($descriptionFormatRows, $value['descriptionRows'] ?? null, $element);

        return new SeoData([
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'titleRows' => $titleRows,
            'descriptionRows' => $descriptionRows,
            'canonicalUrl' => $this->_normalizeString($value['canonicalUrl'] ?? null),
            'robots' => self::normalizeRobots($value['robots'] ?? []),
            // `imageId` is the pre-rename key: existing stored/posted content may still use it.
            'image' => $this->_normalizeAssetId($value['image'] ?? $value['imageId'] ?? null),
            'excludeFromSitemap' => self::normalizeExcludeFromSitemap($value['excludeFromSitemap'] ?? null),
        ]);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function isValueEmpty(mixed $value, ElementInterface $element): bool {
        if ($value instanceof SeoData) return $value->isEmpty();

        return parent::isValueEmpty($value, $element);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getElementValidationRules(): array {
        return [
            ['validateSeoData'],
        ];
    }

    /**
     * Validates the canonical URL format and the resolved meta title/description length. A
     * blank meta title/description is allowed — that's scored by the SEO score checklist, not
     * blocked at save time. An over-length one is blocked here, rather than at
     * `SeoScoreService`'s own save of its denormalized copy.
     *
     * @param ElementInterface $element the element being validated
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function validateSeoData(ElementInterface $element): void {
        $value = $element->getFieldValue($this->handle);

        if (!$value instanceof SeoData) return;

        /** @var Element $element */
        $this->_validateMetaLength($element, $value->metaTitle, Craft::t('core-seo-geo', 'Meta Title'));
        $this->_validateMetaLength($element, $value->metaDescription, Craft::t('core-seo-geo', 'Meta Description'));

        if ($value->canonicalUrl === null) return;

        $validator = new UrlValidator(['defaultScheme' => 'https']);

        if (!$validator->validate($value->canonicalUrl)) {
            $element->addError($this->handle, Craft::t('core-seo-geo', '{attribute} is not a valid URL.', [
                'attribute' => Craft::t('core-seo-geo', 'Canonical URL'),
            ]));
        }
    }

    /**
     * @param ElementInterface $element
     * @param string|null $value
     * @param string $label
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.7
     */
    private function _validateMetaLength(ElementInterface $element, ?string $value, string $label): void {
        if ($value === null) return;

        $length = mb_strlen($value);

        if ($length > SeoData::MAX_META_LENGTH) {
            $element->addError($this->handle, Craft::t('core-seo-geo', '{attribute} is {length} characters long, but only {max} are allowed.', [
                'attribute' => $label,
                'length' => $length,
                'max' => SeoData::MAX_META_LENGTH,
            ]));
        }
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getSearchKeywords(mixed $value, ElementInterface $element): string {
        if (!$value instanceof SeoData) return '';

        return implode(' ', array_filter([
            $value->metaTitle,
            $value->metaDescription,
        ]));
    }

    /**
     * Shows the element's stored SEO score as a colored "passed/applicable" chip in the element
     * index, when this field is added as a table attribute. The chip markup itself lives on
     * `SeoScoreService::renderChip()`, which the entry edit page's SEO/GEO sidebar pane
     * ({@see \digitalastronaut\craftcoreseogeo\PluginTrait::_seoScoreSidebarHtml()}) also builds
     * on via that service's `renderChipPill()`/`renderChecklist()`, rather than duplicating this.
     *
     * @param mixed $value
     * @param ElementInterface $element
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getPreviewHtml(mixed $value, ElementInterface $element): string {
        /** @var Element $element */
        $seoScore = CoreSeoGeo::getInstance()->getSeoScore();
        $score = $seoScore->getScore($element, (int)$this->id);

        return $seoScore->renderChip($score, (int)$element->id, (int)$element->siteId, (int)$this->id);
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string {
        $view = Craft::$app->getView();
        $id = $view->namespaceInputId(Html::id($this->handle));

        $contentSeo = CoreSeoGeo::getInstance()->getContentSeo();
        $siteId = (int)($element?->siteId ?? Craft::$app->getSites()->getCurrentSite()->id);
        $sectionHandle = $element instanceof Entry ? $element->getSection()?->handle : null;

        $image = $value instanceof SeoData ? $value->getImage()->one() : null;
        // No image of its own - resolve the same section-then-global fallback the live meta tags
        // and the SEO Score check already use, so the editor can see why those show an image
        // when this field's own chooser doesn't.
        $fallbackImage = null;
        $fallbackSource = null;

        if ($image === null) {
            $sectionRow = $sectionHandle !== null ? $contentSeo->getRowForSection($sectionHandle, $siteId) : null;

            $fallbackImage = $contentSeo->getFallbackImage($element);
            if ($fallbackImage !== null) $fallbackSource = $sectionRow?->getImageAsset() ? 'content' : 'global';
        }

        // Same fallback the live meta tags, sitemap, llms.txt, and the SEO Score "Indexable"
        // check all already apply when this field's own robots list is left empty.
        $fallbackRobots = $value instanceof SeoData && $value->robots === []
            ? $contentSeo->getFallbackRobots($element)
            : [];

        $sitemapWarning = self::_sitemapWarning($contentSeo, $sectionHandle, $siteId);

        [$titleFormatRows, $descriptionFormatRows] = self::_resolveFormatRows($element);

        $faviconAsset = CoreSeoGeo::getInstance()->getSettings()->getFaviconAsset();
        $imagesBundle = $view->registerAssetBundle(ImagesAssetBundle::class);

        return $view->renderTemplate('core-seo-geo/fields/seo/_input', [
            'field' => $this,
            'id' => $id,
            'name' => $this->handle,
            'value' => $value,
            'element' => $element,
            'image' => $image,
            'fallbackImage' => $fallbackImage,
            'fallbackSource' => $fallbackSource,
            'fallbackRobots' => $fallbackRobots,
            'sitemapWarning' => $sitemapWarning,
            'titleFormatRows' => $titleFormatRows,
            'descriptionFormatRows' => $descriptionFormatRows,
            'titleRowValues' => self::_matchPostedRows($titleFormatRows, $value instanceof SeoData ? $value->titleRows : null),
            'descriptionRowValues' => self::_matchPostedRows($descriptionFormatRows, $value instanceof SeoData ? $value->descriptionRows : null),
            // For the Google card in `_previews.twig` - reuses the same asset/service pair
            // `PluginTrait::_getFaviconTags()` uses for the real `<link rel="icon">` tag.
            'faviconUrl' => $faviconAsset ? CoreSeoGeo::getInstance()->getFavicon()->getFaviconPngUrl($faviconAsset) : null,
            // Placeholder shown by the social cards in `_previews.twig` when no image is set.
            'noImageUrl' => "{$imagesBundle->baseUrl}/no-image.png",
        ]);
    }

    /**
     * @param mixed $value
     * @return string|null
     *
     * @since       v1.0.0
     */
    private function _normalizeString(mixed $value): ?string {
        if (!\is_string($value)) return null;

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Public (and static) so `SitemapService` can parse a `robots` value straight off an
     * element's raw, not-yet-normalized field data too, without going through
     * `normalizeValue()` - see `SitemapService::_rawRobots()`.
     *
     * @param mixed $value
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public static function normalizeRobots(mixed $value): array {
        if (!\is_array($value)) return [];

        return array_values(array_intersect(SeoData::ROBOTS_DIRECTIVES, $value));
    }

    /**
     * Public (and static) so `SitemapService` can parse a posted/stored `excludeFromSitemap`
     * value straight off an element's raw, not-yet-normalized field data too, without going
     * through `normalizeValue()` - see `SitemapService::_rawExcludeFromSitemap()`. `null` (the
     * field was never rendered/posted at all, e.g. a value set programmatically or saved before
     * this setting existed) defaults to `false`, matching {@see SeoData::$excludeFromSitemap}'s
     * own default - unlike the lightswitch's own "on" posting the literal string `'1'`, which
     * this correctly reads as `true` via `_isTruthy()`.
     *
     * @param mixed $value
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.37.0
     */
    public static function normalizeExcludeFromSitemap(mixed $value): bool {
        return $value !== null && self::_isTruthy($value);
    }

    /**
     * Normalizes a posted element-select value into a single asset ID. The input posts a plain
     * scalar since it's configured as a single selection, but an array is tolerated too in case
     * that ever changes.
     *
     * @param mixed $value
     * @return int|null
     *
     * @since v1.0.0
     */
    private function _normalizeAssetId(mixed $value): ?int {
        if (\is_array($value)) $value = reset($value) ?: null;

        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * Tells the editor when the "Exclude from sitemap" switch has no effect either way right
     * now, because a setting above it - sitewide or for this element's own section - already
     * decides the outcome, rather than leaving them to notice only by checking the sitemap
     * itself, or to find out from static instructions that may no longer match the site's
     * actual current configuration. Links to the setting that's overriding this one, but only
     * for an admin - every settings page this could point to requires admin access
     * ({@see \digitalastronaut\craftcoreseogeo\controllers\ContentSeoController::beforeAction()}),
     * so a non-admin editor gets the same explanation with no dead link to a page they can't
     * open.
     *
     * @param ContentSeoService $contentSeo
     * @param string|null $sectionHandle the element's own section handle, or `null` when it has
     * none (not an `Entry`, or an `Entry` with none) - never excluded at the section level then
     * @param int $siteId
     * @return string|null
     *
     * @author      Digitalastronaut
     * @since       v1.37.0
     */
    private static function _sitemapWarning(ContentSeoService $contentSeo, ?string $sectionHandle, int $siteId): ?string {
        $isAdmin = Craft::$app->getUser()->getIsAdmin();

        if (!CoreSeoGeo::getInstance()->getSettings()->enableSitemap) {
            return $isAdmin
                ? Craft::t('core-seo-geo', 'Sitemap generation is currently [disabled globally]({url})', [
                    'url' => UrlHelper::cpUrl('core-seo-geo/settings/global-seo') . '#sitemap',
                ])
                : Craft::t('core-seo-geo', 'Sitemap generation is currently disabled globally');
        }

        if ($sectionHandle !== null && !$contentSeo->isSitemapEnabled($sectionHandle, $siteId)) {
            return $isAdmin
                ? Craft::t('core-seo-geo', 'Sitemap generation is currently [disabled for this section]({url})', [
                    'url' => UrlHelper::cpUrl("core-seo-geo/settings/content-seo/{$sectionHandle}", [
                        'site' => Craft::$app->getSites()->getSiteById($siteId)?->handle,
                    ]),
                ])
                : Craft::t('core-seo-geo', 'Sitemap generation is currently disabled for this section');
        }

        return null;
    }

    /**
     * Resolves a title/description format-rows config directly against an element, with no
     * posted/stored per-row override to consider. Used by `PluginTrait::_fallbackSeoData()` for
     * elements whose field layout has no `SeoField` of its own to resolve a per-row override from
     * in the first place.
     *
     * @param array<int, array{label: string, template: string}> $formatRows the format's rows
     * @param ElementInterface|null $element the element to resolve `{attr}`/`{{ twig }}` in each row's template against
     * @return string|null the joined result
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public static function resolveFormatRows(array $formatRows, ?ElementInterface $element): ?string {
        [, $joined] = self::_resolveFormat($formatRows, null, $element);

        return $joined;
    }

    /**
     * Resolves a title/description format against its settings rows: non-editable rows always
     * use the settings row's own template (a posted override is never trusted for those), while
     * editable rows use the posted override when present and non-blank, falling back to the
     * template's own computed value when the override was saved empty. The resolved rows are
     * then joined with a single space between each non-blank block, and keyed by each row's
     * `rowId` ({@see _matchPostedRows()}) rather than its position, so they keep resolving
     * against the right row even after the config is reordered or edited.
     *
     * @param array<int, array{rowId: string, label: string, template: string, editable: bool}> $formatRows the field's own settings rows
     * @param mixed $postedRows the posted/stored per-row override text, if any
     * @param ElementInterface|null $element the element being saved, used to resolve `{attr}`/`{{ twig }}` in each row
     * @return array{0: array<string, string>, 1: ?string} the resolved per-row text keyed by `rowId`, and the joined result
     *
     * @since v1.0.0
     */
    private static function _resolveFormat(array $formatRows, mixed $postedRows, ?ElementInterface $element): array {
        $matched = self::_matchPostedRows($formatRows, $postedRows);
        $rows = [];

        foreach ($formatRows as $i => $row) {
            $template = (string)($row['template'] ?? '');
            $editable = self::_isTruthy($row['editable'] ?? false);
            $posted = $matched[$i];
            $postedIsBlank = !\is_string($posted) || trim($posted) === '';

            $effective = ($editable && !$postedIsBlank) ? $posted : $template;
            $rows[$row['rowId']] = $element !== null ? Craft::$app->getView()->renderObjectTemplate($effective, $element) : $effective;
        }

        if ($rows === []) {
            return [[], null];
        }

        $nonBlankRows = array_filter($rows, static fn(string $row): bool => trim($row) !== '');
        $joined = trim(implode(' ', $nonBlankRows));

        return [$rows, $joined === '' ? null : $joined];
    }

    /**
     * Matches each of `$formatRows`' rows against its posted/stored override in `$postedRows`, by
     * `rowId` - except when `$postedRows` is itself still in the legacy, pre-`rowId` shape (a
     * plain list rather than a `rowId => text` map), in which case it's matched by position
     * instead, exactly as it would've been before `rowId`s existed. That's what a `SeoField`
     * saved before this existed still has stored, and what the config rows themselves still
     * resolve to until they're next saved through the settings page (every row gets a real
     * `rowId` the moment that page is saved - see `ContentSeoService::_ensureRowIds()`) - so
     * reading it back by position here keeps it resolving exactly as it already did, until then.
     *
     * @param array<int, array{rowId: string, label: string, template: string, editable: bool}> $formatRows
     * @param mixed $postedRows
     * @return array<int, string|null> the override text for each `$formatRows` row, keyed the same as `$formatRows`
     *
     * @author      Digitalastronaut
     * @since       v1.2.0
     */
    private static function _matchPostedRows(array $formatRows, mixed $postedRows): array {
        $postedRows = \is_array($postedRows) ? $postedRows : [];
        $legacy = array_is_list($postedRows);

        $matched = [];

        foreach ($formatRows as $i => $row) {
            $matched[$i] = $legacy ? ($postedRows[$i] ?? null) : ($postedRows[$row['rowId']] ?? null);
        }

        return $matched;
    }

    /**
     * Coerces a posted/stored lightswitch value to a boolean. PHP's `(bool)'0'` is `true`,
     * which would be wrong for the "0" a lightswitch posts when off.
     *
     * @param mixed $value
     * @return bool
     *
     * @since v1.0.0
     */
    private static function _isTruthy(mixed $value): bool {
        return $value === true || $value === '1' || $value === 1;
    }

    /**
     * Resolves the title/description block config an element's `SeoField` input (and its
     * resolved meta title/description) should be built from: the `coreseogeo_seodata` row for
     * the element's own section on its site first, falling back - independently per attribute,
     * since a section row can have one config filled in and not the other - to the site's global
     * bundle row. An element with no section (not an `Entry`, or an `Entry` with none) goes
     * straight to the global row. If neither has anything configured for an attribute, falls
     * back once more to a single free-text editable block ({@see _defaultFormatRow()}), so the
     * field still has somewhere to type a title/description into.
     *
     * @param ElementInterface|null $element
     * @return array{0: array<int, array{label: string, template: string, editable: bool}>, 1: array<int, array{label: string, template: string, editable: bool}>} title rows, then description rows
     *
     * @author      Digitalastronaut
     * @since       v1.2.0
     */
    private static function _resolveFormatRows(?ElementInterface $element): array {
        $contentSeo = CoreSeoGeo::getInstance()->getContentSeo();
        $siteId = (int)($element?->siteId ?? Craft::$app->getSites()->getCurrentSite()->id);
        $sectionHandle = $element instanceof Entry ? $element->getSection()?->handle : null;

        $sectionRow = $sectionHandle !== null ? $contentSeo->getRowForSection($sectionHandle, $siteId) : null;

        $titleFormatRows = self::_decodeFormatRows($sectionRow?->titleConfig);
        $descriptionFormatRows = self::_decodeFormatRows($sectionRow?->descriptionConfig);

        if ($titleFormatRows === [] || $descriptionFormatRows === []) {
            $globalRow = $contentSeo->getGlobalRow($siteId);

            if ($titleFormatRows === []) $titleFormatRows = self::_decodeFormatRows($globalRow?->titleConfig);
            if ($descriptionFormatRows === []) $descriptionFormatRows = self::_decodeFormatRows($globalRow?->descriptionConfig);
        }

        // Neither the section nor the global bundle has anything configured - rather than
        // leave the input with nothing to type a title/description into at all, fall back to a
        // single free-text editable block each, with no template of its own to fall back to.
        if ($titleFormatRows === []) $titleFormatRows = [self::_defaultFormatRow(Craft::t('core-seo-geo', 'Meta Title'), 'default-title')];
        if ($descriptionFormatRows === []) $descriptionFormatRows = [self::_defaultFormatRow(Craft::t('core-seo-geo', 'Meta Description'), 'default-description')];

        return [$titleFormatRows, $descriptionFormatRows];
    }

    /**
     * A single free-text editable block, used by {@see _resolveFormatRows()} as the last-resort
     * fallback when neither the section nor the global bundle row has any config of its own -
     * without this, an element in that situation would have no way to set a title/description at
     * all. `$rowId` is a fixed string rather than a generated one, since this row isn't backed by
     * any stored config to read a real one back from - it only needs to be stable across requests
     * (so a posted override still matches on the next one) and distinct between the title and
     * description fallback.
     *
     * @param string $label
     * @param string $rowId
     * @return array{rowId: string, label: string, template: string, editable: bool}
     *
     * @author      Digitalastronaut
     * @since       v1.2.0
     */
    private static function _defaultFormatRow(string $label, string $rowId): array {
        return ['rowId' => $rowId, 'label' => $label, 'template' => '', 'editable' => true];
    }

    /**
     * Decodes a stored `titleConfig`/`descriptionConfig` JSON blob back into its rows, and backs
     * every row that doesn't already have one - either it predates `rowId`s existing at all, or
     * it's a row freshly posted from "Add a block" that's never been saved before - with a
     * same-request-stable placeholder (`rowId` is a real, permanent uuid only once
     * `ContentSeoService::_ensureRowIds()` has saved it). It's keyed by position deliberately: as
     * long as nothing has a real `rowId` yet, this reduces right back to the exact numeric keys
     * `SeoData::$titleRows`/`$descriptionRows` already used before `rowId`s existed, so a config
     * that's never been saved through the new settings page still matches up with already-stored
     * per-entry overrides exactly as it did before - see `_matchPostedRows()`.
     *
     * @param string|null $json
     * @return array<int, array{rowId: string, label: string, template: string, editable: bool}>
     *
     * @author      Digitalastronaut
     * @since       v1.2.0
     */
    private static function _decodeFormatRows(?string $json): array {
        if ($json === null) return [];

        $decoded = Json::decode($json);

        if (!\is_array($decoded)) return [];

        foreach ($decoded as $i => &$row) {
            if (!\is_array($row)) continue;

            $row['rowId'] = ((string)($row['rowId'] ?? '')) !== '' ? (string)$row['rowId'] : (string)$i;
        }
        unset($row);

        return $decoded;
    }
}
