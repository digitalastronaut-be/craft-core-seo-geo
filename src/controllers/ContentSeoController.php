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
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\fields\SeoField;
use digitalastronaut\craftcoreseogeo\fields\StructuredDataField;
use digitalastronaut\craftcoreseogeo\models\Settings;
use digitalastronaut\craftcoreseogeo\records\ContentSeo;
use digitalastronaut\craftcoreseogeo\records\StructuredDataMapping;
use digitalastronaut\craftcoreseogeo\services\GlobalStructuredDataService;
use digitalastronaut\craftcoreseogeo\services\TxtFilesService;

use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class ContentSeoController
 *
 * The "Content SEO" settings pages: an index listing every section's (plus the global bundle's)
 * `coreseogeo_seodata` row for the currently-selected site, a per-section page for editing one
 * row's title/description block config and robots directives, and the Global SEO/GEO settings
 * page's own title/description config, which is the global bundle row's (`sectionHandle === ''`)
 * counterpart to a section's. `ContentSeoService` is what actually keeps rows in sync as sections
 * themselves are added/saved/deleted - this controller only reads them back for display and, via
 * `actionSaveSection()`/`actionSaveGlobal()`, writes back the editable config on top.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.1.0
 */
class ContentSeoController extends Controller {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function beforeAction($action): bool {
        $this->requireAdmin(false);

        return parent::beforeAction($action);
    }

    /**
     * @return Response
     * @throws ForbiddenHttpException if the current user can't edit any site - shouldn't happen
     * for an admin, but `Cp::requestedSite()` returns `null` rather than guaranteeing one.
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function actionIndex(): Response {
        $site = Cp::requestedSite();

        if ($site === null) {
            throw new ForbiddenHttpException(Craft::t('core-seo-geo', "You don't have permission to edit any sites."));
        }

        return $this->renderTemplate('core-seo-geo/settings/content-seo.twig', [
            'site' => $site,
        ]);
    }

    /**
     * JSON data for the index's `Craft.VueAdminTable` - `content-seo.twig`'s
     * `tableDataEndpoint`, scoped to whichever site the page's `?site=` query param (baked into
     * that endpoint string) names. `page`/`per_page`/`search`/`sort.0.direction` are the
     * component's own query params; `sectionLabel` is the only sortable column, so unlike
     * `RedirectsController::actionTableData()` there's no `sort.0.field` to branch on.
     *
     * @return Response
     * @throws ForbiddenHttpException if the current user can't edit any site
     *
     * @author      Digitalastronaut
     * @since       v1.25.0
     */
    public function actionTableData(): Response {
        $this->requireAcceptsJson();

        $site = Cp::requestedSite();

        if ($site === null) {
            throw new ForbiddenHttpException(Craft::t('core-seo-geo', "You don't have permission to edit any sites."));
        }

        $request = Craft::$app->getRequest();
        $page = (int)$request->getParam('page', 1);
        $limit = (int)$request->getParam('per_page', 100);
        $searchTerm = $request->getParam('search');
        $sortDir = $request->getParam('sort.0.direction') === 'desc' ? SORT_DESC : SORT_ASC;

        [$pagination, $rows] = CoreSeoGeo::getInstance()->getContentSeo()->getTableDataForSite($site->id, $page, $limit, $searchTerm, $sortDir);

        return $this->asSuccess(data: [
            'pagination' => $pagination,
            'data' => $this->_contentSeoTableRows($rows),
        ]);
    }

    /**
     * This plugin's own settings page for a single section - not Craft's native section-edit
     * screen (`Section::getCpEditUrl()`), which only covers the section's structure (entry
     * types, URL format, propagation). Renders the title/description config tables and robots
     * field against the section's current `coreseogeo_seodata` row; posts to
     * `actionSaveSection()`.
     *
     * @param string $sectionHandle
     * @return Response
     * @throws ForbiddenHttpException if the current user can't edit any site
     * @throws NotFoundHttpException if no section has this handle
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function actionSection(string $sectionHandle): Response {
        $site = Cp::requestedSite();

        if ($site === null) {
            throw new ForbiddenHttpException(Craft::t('core-seo-geo', "You don't have permission to edit any sites."));
        }

        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);

        if ($section === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No section exists with the handle "{handle}".', [
                'handle' => $sectionHandle,
            ]));
        }

        $row = CoreSeoGeo::getInstance()->getContentSeo()->getRowForSection($sectionHandle, $site->id);

        return $this->renderTemplate('core-seo-geo/settings/content-seo-section.twig', [
            'site' => $site,
            'section' => $section,
            'titleConfig' => $this->_decodeConfig($row?->titleConfig),
            'descriptionConfig' => $this->_decodeConfig($row?->descriptionConfig),
            'robots' => $this->_decodeConfig($row?->robots),
            'robotsOptions' => SeoField::getRobotsOptions(),
            'sitemapEnabled' => $row?->sitemapEnabled ?? true,
            'image' => $row?->getImageAsset(),
            'mappingRows' => $this->_mappingTableRows(
                CoreSeoGeo::getInstance()->getStructuredDataMappings()->getRowsForSection($sectionHandle),
            ),
            'newMappingUrl' => UrlHelper::cpUrl("core-seo-geo/settings/content-seo/{$sectionHandle}/mappings/new", ['site' => $site->handle]),
        ]);
    }

    /**
     * The "Create mapping"/"Edit mapping" page for one structured data mapping on this section -
     * a new, unsaved `StructuredDataMapping` row when `$mappingId` is `null` (the `.../mappings/
     * new` route), the existing one otherwise. Renders the same type/properties mapping editor
     * as `StructuredDataField`'s own settings ({@see \digitalastronaut\craftcoreseogeo\fields\
     * StructuredDataField::settingsNamespace()}); posts to `actionSaveMapping()`.
     *
     * @param string $sectionHandle
     * @param int|null $mappingId
     * @return Response
     * @throws ForbiddenHttpException if the current user can't edit any site
     * @throws NotFoundHttpException if no section has this handle, or no mapping has this ID
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function actionEditMapping(string $sectionHandle, ?int $mappingId = null): Response {
        $site = Cp::requestedSite();

        if ($site === null) {
            throw new ForbiddenHttpException(Craft::t('core-seo-geo', "You don't have permission to edit any sites."));
        }

        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);

        if ($section === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No section exists with the handle "{handle}".', [
                'handle' => $sectionHandle,
            ]));
        }

        $mapping = $mappingId !== null
            ? CoreSeoGeo::getInstance()->getStructuredDataMappings()->getMappingById($mappingId, $sectionHandle)
            : null;

        if ($mappingId !== null && $mapping === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', "No structured data mapping exists with that ID."));
        }

        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();
        $type = $mapping->type ?? '';
        $properties = $this->_decodeConfig($mapping?->properties);
        $propertyNames = $schemaOrg->getType($type)['properties'] ?? [];
        $previewEntry = $mapping?->getPreviewEntry();

        return $this->renderTemplate('core-seo-geo/settings/content-seo-mapping.twig', [
            'site' => $site,
            'section' => $section,
            'mapping' => $mapping,
            'mappingProperties' => $properties,
            'mappingNamespace' => StructuredDataField::settingsNamespace(),
            'typeTree' => $schemaOrg->getTypeTree(),
            'expandedTypes' => $type !== '' ? $schemaOrg->getPrimaryAncestorPath($type) : [],
            'typeLabel' => $type !== '' ? ($schemaOrg->getType($type)['label'] ?? $type) : '',
            'propertyGroups' => $schemaOrg->getGroupedTypeProperties($type),
            // Covers every property of the type, not just the ones already added - the
            // picker's dropdown needs a tooltip for anything a user might still pick, not only
            // what's already in the mapping table below.
            'propertyTooltips' => $schemaOrg->getPropertyTooltips($propertyNames),
            'previewEntry' => $previewEntry,
            'calculatedValueHtml' => StructuredDataField::calculatedValueHtml($properties, $previewEntry),
            'jsonLdPreview' => StructuredDataField::jsonLdPreview($type, $properties, $previewEntry),
        ]);
    }

    /**
     * Persists the type/properties mapping posted from `content-seo-mapping.twig`, creating a
     * new `coreseogeo_structureddatamappings` row when no `id` was posted (a brand new mapping)
     * or updating the posted one otherwise. The posted `type`/`properties`/`previewElementId`
     * fields themselves are namespaced under `StructuredDataField::settingsNamespace()` - the
     * same mapping editor UI `StructuredDataField`'s own settings use - so they're read back out
     * from under that key rather than as plain top-level body params.
     *
     * @return Response|null
     * @throws BadRequestHttpException if `sectionHandle` wasn't posted
     * @throws NotFoundHttpException if no section has this handle, or the posted `id` doesn't
     * match an existing mapping for it
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    public function actionSaveMapping(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $sectionHandle = $request->getRequiredBodyParam('sectionHandle');
        $id = $request->getBodyParam('id');

        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);

        if ($section === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No section exists with the handle "{handle}".', [
                'handle' => $sectionHandle,
            ]));
        }

        $mappingsService = CoreSeoGeo::getInstance()->getStructuredDataMappings();
        $mapping = $id ? $mappingsService->getMappingById((int)$id, $sectionHandle) : null;

        if ($id && $mapping === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', "No structured data mapping exists with that ID."));
        }

        $scoped = $request->getBodyParam('types', [])[StructuredDataField::settingsNamespaceKey()] ?? [];

        $saved = $mappingsService->saveMapping(
            $mapping,
            $sectionHandle,
            (string)($scoped['type'] ?? ''),
            is_array($scoped['properties'] ?? null) ? $scoped['properties'] : [],
            StructuredDataField::normalizePreviewElementId($scoped['previewElementId'] ?? null),
        );

        if (!$saved) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the structured data mapping."));

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'Structured data mapping saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes one structured data mapping, posted from the delete button in
     * `content-seo-section.twig`'s mappings table. Ajax-only (the button posts via
     * `Craft.sendActionRequest()`), so this relies on `asSuccess()`/`asFailure()` for their JSON
     * responses rather than the flash+redirect pattern the rest of this controller uses.
     *
     * @return Response|null
     * @throws BadRequestHttpException if `sectionHandle` or `id` wasn't posted
     * @throws NotFoundHttpException if no section has this handle, or the posted `id` doesn't
     * match an existing mapping for it
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function actionDeleteMapping(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $sectionHandle = $request->getRequiredBodyParam('sectionHandle');
        $id = (int)$request->getRequiredBodyParam('id');

        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);

        if ($section === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No section exists with the handle "{handle}".', [
                'handle' => $sectionHandle,
            ]));
        }

        $mappingsService = CoreSeoGeo::getInstance()->getStructuredDataMappings();
        $mapping = $mappingsService->getMappingById($id, $sectionHandle);

        if ($mapping === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', "No structured data mapping exists with that ID."));
        }

        if (!$mappingsService->deleteMapping($mapping)) {
            return $this->asFailure(Craft::t('core-seo-geo', "Couldn't delete the structured data mapping."));
        }

        return $this->asSuccess(Craft::t('core-seo-geo', 'Structured data mapping deleted.'));
    }

    /**
     * Persists the title/description block config, robots directives, and sitemap toggle posted
     * from `content-seo-section.twig`. Posted alongside the form's own `titleConfig`/
     * `descriptionConfig`/`robots`/`sitemapEnabled` fields by hidden `sectionHandle`/`siteId`
     * inputs, since neither is otherwise part of the row's own editable config.
     *
     * @return Response|null
     * @throws BadRequestHttpException if `sectionHandle` or `siteId` wasn't posted
     * @throws NotFoundHttpException if no section has this handle
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function actionSaveSection(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $sectionHandle = $request->getRequiredBodyParam('sectionHandle');
        $siteId = (int)$request->getRequiredBodyParam('siteId');

        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);

        if ($section === null) {
            throw new NotFoundHttpException(Craft::t('core-seo-geo', 'No section exists with the handle "{handle}".', [
                'handle' => $sectionHandle,
            ]));
        }

        $saved = CoreSeoGeo::getInstance()->getContentSeo()->saveSectionConfig(
            $section,
            $siteId,
            $this->_arrayBodyParam('titleConfig'),
            $this->_arrayBodyParam('descriptionConfig'),
            $this->_arrayBodyParam('robots'),
            (bool)$request->getBodyParam('sitemapEnabled', true),
            $this->_assetIdBodyParam('image'),
        );

        if (!$saved) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the SEO/GEO settings."));

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'SEO/GEO settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * The Global SEO/GEO settings page. Renders the title/description config tables, robots
     * directives, and fallback image against the selected site's global bundle row. The plugin's
     * own site-wide settings (hreflang, favicon, sitemap, etc.) live on the separate "Settings"
     * nav page instead. Posts to `actionSaveGlobal()`.
     *
     * @return Response
     * @throws ForbiddenHttpException if the current user can't edit any site
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function actionGlobal(): Response {
        $site = Cp::requestedSite();

        if ($site === null) {
            throw new ForbiddenHttpException(Craft::t('core-seo-geo', "You don't have permission to edit any sites."));
        }

        $row = CoreSeoGeo::getInstance()->getContentSeo()->getGlobalRow($site->id);

        return $this->renderTemplate('core-seo-geo/settings/global-seo.twig', [
            'site' => $site,
            'titleConfig' => $this->_decodeConfig($row?->titleConfig),
            'descriptionConfig' => $this->_decodeConfig($row?->descriptionConfig),
            'robots' => $this->_decodeConfig($row?->robots),
            'robotsOptions' => SeoField::getRobotsOptions(),
            'image' => $row?->getImageAsset(),
            'structuredDataTypeRows' => $this->_globalStructuredDataTypeRows($site->id),
            'txtFileRows' => $this->_txtFileRows($site->id),
            'settings' => CoreSeoGeo::getInstance()->getSettings(),
            'referrerPolicyOptions' => Settings::getReferrerPolicyOptions(),
            'allowAdminChanges' => Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ]);
    }

    /**
     * Builds `global-seo.twig`'s "Business & Website Info" table: one row per built-in
     * standalone type ({@see \digitalastronaut\craftcoreseogeo\services\
     * GlobalStructuredDataService::TYPE_SLUGS}), with its settings page URL and the same
     * "Configured"/"Not configured" status label `content-seo.twig`'s own table uses
     * ({@see _configStatusHtml()}), for whether it's currently configured (and enabled) for the
     * given site.
     *
     * @param int $siteId
     * @return array<int, array{slug: string, label: string, url: string, statusHtml: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.5.0
     */
    private function _globalStructuredDataTypeRows(int $siteId): array {
        $service = CoreSeoGeo::getInstance()->getGlobalStructuredData();
        $rows = [];

        foreach (GlobalStructuredDataService::TYPE_SLUGS as $slug => $type) {
            $element = $service->getElement($type, $siteId);

            $rows[] = [
                'slug' => $slug,
                'label' => $service->getTypeLabel($type),
                'url' => UrlHelper::cpUrl("core-seo-geo/settings/global/{$slug}", ['site' => Craft::$app->getSites()->getSiteById($siteId)?->handle]),
                'statusHtml' => $this->_configStatusHtml($element !== null && $element->enabled),
            ];
        }

        return $rows;
    }

    /**
     * Builds `global-seo.twig`'s "Text files" table: one row per generated text file
     * ({@see TxtFilesService::TYPE_SLUGS}), with its settings page URL - every one of them now
     * reflects its real `enable*` setting.
     *
     * @param int $siteId
     * @return array<int, array{slug: string, label: string, url: string, statusHtml: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    private function _txtFileRows(int $siteId): array {
        $settings = CoreSeoGeo::getInstance()->getSettings();
        $rows = [];

        foreach (TxtFilesService::TYPE_SLUGS as $slug => $label) {
            $statusHtml = match ($slug) {
                'robots' => $this->_onOffStatusHtml(
                    $settings->enableRobotsTxt,
                    Craft::t('core-seo-geo', 'Enabled'),
                    Craft::t('core-seo-geo', 'Disabled'),
                ),
                'humans' => $this->_onOffStatusHtml(
                    $settings->enableHumansTxt,
                    Craft::t('core-seo-geo', 'Enabled'),
                    Craft::t('core-seo-geo', 'Disabled'),
                ),
                'ads' => $this->_onOffStatusHtml(
                    $settings->enableAdsTxt,
                    Craft::t('core-seo-geo', 'Enabled'),
                    Craft::t('core-seo-geo', 'Disabled'),
                ),
                'security' => $this->_onOffStatusHtml(
                    $settings->enableSecurityTxt,
                    Craft::t('core-seo-geo', 'Enabled'),
                    Craft::t('core-seo-geo', 'Disabled'),
                ),
                'trust' => $this->_onOffStatusHtml(
                    $settings->enableTrustTxt,
                    Craft::t('core-seo-geo', 'Enabled'),
                    Craft::t('core-seo-geo', 'Disabled'),
                ),
                'llms' => $this->_onOffStatusHtml(
                    $settings->enableLlmsTxt,
                    Craft::t('core-seo-geo', 'Enabled'),
                    Craft::t('core-seo-geo', 'Disabled'),
                ),
                'llms-full' => $this->_onOffStatusHtml(
                    $settings->enableLlmsFullTxt,
                    Craft::t('core-seo-geo', 'Enabled'),
                    Craft::t('core-seo-geo', 'Disabled'),
                ),
                default => $this->_configStatusHtml(false),
            };

            $rows[] = [
                'slug' => $slug,
                'label' => $label,
                'url' => UrlHelper::cpUrl("core-seo-geo/settings/txt/{$slug}", ['site' => Craft::$app->getSites()->getSiteById($siteId)?->handle]),
                'statusHtml' => $statusHtml,
            ];
        }

        return $rows;
    }

    /**
     * Persists the title/description block config, robots directives, and fallback image posted
     * from `global-seo.twig` into the posted site's global bundle row, plus the plugin-wide
     * settings (hreflang, favicon, sitemap generation, custom sitemap URLs) posted alongside them
     * under the `settings` namespace - the same fields the plugin's native "Settings" screen used
     * to own on its own page, before they were moved to the bottom of this one. Saved through
     * `CoreSeoGeo::savePartialSettings()` rather than posting to the generic
     * `plugins/save-plugin-settings` action separately (a page can only have one `<form>`/one
     * submit action) - and rather than `craft\services\Plugins::savePluginSettings()` directly,
     * which would silently erase every *other* settings page's fields - see that method's
     * docblock for why.
     *
     * @return Response|null
     * @throws BadRequestHttpException if `siteId` wasn't posted
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function actionSaveGlobal(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $siteId = (int)$request->getRequiredBodyParam('siteId');

        $contentSeoSaved = CoreSeoGeo::getInstance()->getContentSeo()->saveGlobalConfig(
            $siteId,
            $this->_arrayBodyParam('titleConfig'),
            $this->_arrayBodyParam('descriptionConfig'),
            $this->_arrayBodyParam('robots'),
            $this->_assetIdBodyParam('image'),
        );

        $pluginSettingsSaved = CoreSeoGeo::getInstance()->savePartialSettings($this->_arrayBodyParam('settings'));

        if (!$contentSeoSaved || !$pluginSettingsSaved) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the SEO/GEO settings."));

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'SEO/GEO settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Persists the per-site llms.txt title/summary posted from `txt-llms.twig`, plus the
     * plugin-wide `enableLlmsTxt`/`enableLlmsFullTxt` toggles posted alongside them under the
     * `settings` namespace - same dual-save shape as `actionSaveGlobal()`, kept as its own
     * action rather than folded into that one since `txt-llms.twig` is its own page with its
     * own form.
     *
     * @return Response|null
     * @throws BadRequestHttpException if `siteId` wasn't posted
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function actionSaveLlms(): ?Response {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $siteId = (int)$request->getRequiredBodyParam('siteId');

        $contentSaved = CoreSeoGeo::getInstance()->getContentSeo()->saveLlmsTxtConfig(
            $siteId,
            $request->getBodyParam('llmsTxtTitle'),
            $request->getBodyParam('llmsTxtSummary'),
        );

        $settingsSaved = CoreSeoGeo::getInstance()->savePartialSettings($this->_arrayBodyParam('settings'));

        if (!$contentSaved || !$settingsSaved) {
            $this->setFailFlash(Craft::t('core-seo-geo', "Couldn't save the llms.txt settings."));

            return null;
        }

        $this->setSuccessFlash(Craft::t('core-seo-geo', 'llms.txt settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * @param string|null $json
     * @return array
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _decodeConfig(?string $json): array {
        return $json !== null ? Json::decode($json) : [];
    }

    /**
     * Builds the display-ready rows for `content-seo-section.twig`'s structured data mappings
     * table: the schema.org type's own label (falling back to its raw handle when it isn't a
     * known type) and a comma-joined list of the mapping's configured property names.
     *
     * @param StructuredDataMapping[] $mappings
     * @return array<int, array{id: int, typeLabel: string, propertyNames: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.3.0
     */
    private function _mappingTableRows(array $mappings): array {
        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();

        return array_map(function(StructuredDataMapping $mapping) use ($schemaOrg): array {
            return [
                'id' => $mapping->id,
                'typeLabel' => $schemaOrg->getType($mapping->type)['label'] ?? $mapping->type,
                'propertyNames' => implode(', ', array_keys($this->_decodeConfig($mapping->properties))),
            ];
        }, $mappings);
    }

    /**
     * Builds one `Craft.VueAdminTable` row per section for `actionTableData()`'s JSON
     * response. `title`/`url` feed the `__slot:title` column; `titleStatus`/`description`/
     * `sitemap` are pre-rendered status-label HTML ({@see _configStatusHtml()}/{@see
     * _onOffStatusHtml()}) since the admin table renders every non-slot cell's value as raw
     * HTML, not escaped text. `robots` and `mappings` are comma-joined, HTML-encoded text,
     * falling back to `&ndash;` when there's nothing configured.
     *
     * @param ContentSeo[] $rows
     * @return array<int, array<string, mixed>>
     *
     * @author      Digitalastronaut
     * @since       v1.25.0
     */
    private function _contentSeoTableRows(array $rows): array {
        $mappingsService = CoreSeoGeo::getInstance()->getStructuredDataMappings();
        $tableRows = [];

        foreach ($rows as $row) {
            $robotsLabels = $this->_robotsLabels($this->_decodeConfig($row->robots));
            $mappings = $this->_mappingSummaryRows($mappingsService->getRowsForSection($row->sectionHandle));

            $tableRows[] = [
                'id' => $row->id,
                'title' => $row->sectionLabel,
                'url' => UrlHelper::url('core-seo-geo/settings/content-seo/' . $row->sectionHandle),
                'titleStatus' => $this->_configStatusHtml($this->_decodeConfig($row->titleConfig) !== []),
                'description' => $this->_configStatusHtml($this->_decodeConfig($row->descriptionConfig) !== []),
                'sitemap' => $this->_onOffStatusHtml(
                    $row->sitemapEnabled,
                    Craft::t('core-seo-geo', 'On'),
                    Craft::t('core-seo-geo', 'Off'),
                ),
                'robots' => $robotsLabels !== [] ? Html::encode(implode(', ', $robotsLabels)) : '&ndash;',
                'mappings' => $mappings !== [] ? Html::encode(implode(', ', array_map(
                    static fn(array $mapping): string => "{$mapping['typeLabel']} ({$mapping['propertyCount']})",
                    $mappings,
                ))) : '&ndash;',
            ];
        }

        return $tableRows;
    }

    /**
     * The same status label shape `Lightswitch::getPreviewHtml()` uses for its own on/off
     * states - teal+check when `$on`, gray+xmark otherwise - with whatever labels the caller
     * wants for each state.
     *
     * @param bool $on
     * @param string $onLabel
     * @param string $offLabel
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.8.0
     */
    private function _onOffStatusHtml(bool $on, string $onLabel, string $offLabel): string {
        return Cp::statusLabelHtml([
            'color' => $on ? Color::Teal : Color::Gray,
            'label' => $on ? $onLabel : $offLabel,
            'icon' => $on ? 'check' : 'xmark',
        ]);
    }

    /**
     * @param bool $configured
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.8.0
     */
    private function _configStatusHtml(bool $configured): string {
        return $this->_onOffStatusHtml(
            $configured,
            Craft::t('core-seo-geo', 'Configured'),
            Craft::t('core-seo-geo', 'Not configured'),
        );
    }

    /**
     * @param string[] $values raw `robots` directive values, e.g. `SeoData::ROBOTS_NOINDEX`
     * @return string[] their labels, falling back to the raw value for anything
     * `SeoField::getRobotsOptions()` doesn't recognize
     *
     * @author      Digitalastronaut
     * @since       v1.8.0
     */
    private function _robotsLabels(array $values): array {
        $labels = [];

        foreach (SeoField::getRobotsOptions() as $option) {
            if (isset($option['value'], $option['label'])) $labels[$option['value']] = $option['label'];
        }

        return array_map(static fn(string $value): string => $labels[$value] ?? $value, $values);
    }

    /**
     * @param StructuredDataMapping[] $mappings
     * @return array<int, array{typeLabel: string, propertyCount: int}>
     *
     * @author      Digitalastronaut
     * @since       v1.8.0
     */
    private function _mappingSummaryRows(array $mappings): array {
        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();

        return array_map(function(StructuredDataMapping $mapping) use ($schemaOrg): array {
            return [
                'typeLabel' => $schemaOrg->getType($mapping->type)['label'] ?? $mapping->type,
                'propertyCount' => count($this->_decodeConfig($mapping->properties)),
            ];
        }, $mappings);
    }

    /**
     * Craft's `multiselect`/`editableTable` inputs pair their real field with a same-named
     * hidden fallback input, so the field still posts something when nothing's selected/no rows
     * exist - as an empty string rather than an empty array. Normalizes that back to `[]`.
     *
     * @param string $name
     * @return array
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _arrayBodyParam(string $name): array {
        $value = Craft::$app->getRequest()->getBodyParam($name, []);

        return is_array($value) ? $value : [];
    }

    /**
     * Normalizes a posted `elementSelectField` value into a single asset ID. The input posts a
     * plain scalar since it's configured as a single selection, but an array is tolerated too in
     * case that ever changes - the same shape `SeoField::_normalizeAssetId()` normalizes.
     *
     * @param string $name
     * @return int|null
     *
     * @author      Digitalastronaut
     * @since       v1.9.0
     */
    private function _assetIdBodyParam(string $name): ?int {
        $value = Craft::$app->getRequest()->getBodyParam($name);

        if (\is_array($value)) $value = reset($value) ?: null;

        return is_numeric($value) ? (int)$value : null;
    }
}
