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
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\Section;
use craft\models\Section_SiteSettings;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\db\Table;
use digitalastronaut\craftcoreseogeo\fields\SeoField;
use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\records\ContentSeo as ContentSeoRecord;

/**
 * Class ContentSeoService
 *
 * Keeps `coreseogeo_seodata` in sync with the site's actual sections and sites: one row per
 * section per site it has URLs enabled for, plus one global fallback row (`sectionHandle === ''`)
 * per site. A section with no URL on a given site (e.g. a `single` used to hold global
 * header/footer content rather than an actual page) gets no row there - there's no page for its
 * meta tags to apply to. Nothing else in the plugin writes to this table directly - route every
 * change through here so the table never drifts out of sync with
 * `Entries::getAllSections()`/`getAllSites()`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.1.0
 */
class ContentSeoService extends Component {
    /**
     * @var bool|null memoized {@see _tableExists()} result
     *
     * @since v1.1.0
     */
    private ?bool $_tableExists = null;

    /**
     * Full reconciliation: every section gets a row for every site it's enabled in
     * ({@see ensureRowsForSection()}), rows for sections that no longer exist are removed, and
     * every site gets its global fallback row ({@see ensureGlobalRows()}). Idempotent and safe
     * to call repeatedly - called from `PluginTrait` on every CP/console request as a safety net
     * against drift, on top of the targeted `ensureRowsForSection()`/`deleteRowsForSection()`
     * calls already wired to section save/delete.
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function syncSections(): void {
        if (!$this->_tableExists()) return;

        $sections = Craft::$app->getEntries()->getAllSections();

        foreach ($sections as $section) {
            $this->ensureRowsForSection($section);
        }

        $this->_pruneOrphanedSectionRows($sections);
        $this->ensureGlobalRows();
    }

    /**
     * Ensures the given section has exactly one row per site it has URLs enabled for: missing
     * rows are created, existing rows have their denormalized `sectionLabel` kept in sync with
     * the section's current name, and rows for a site the section is no longer enabled in (or no
     * longer has URLs for) are deleted. A section without URLs on a given site - e.g. a `single`
     * used to hold global header/footer content rather than an actual page - has nothing for the
     * meta tags this table configures to apply to, so it gets no row there. Called from
     * `EVENT_AFTER_SAVE_SECTION`.
     *
     * @param Section $section
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function ensureRowsForSection(Section $section): void {
        if (!$this->_tableExists()) return;

        $enabledSiteIds = array_keys(array_filter(
            $section->getSiteSettings(),
            static fn(Section_SiteSettings $siteSettings): bool => $siteSettings->hasUrls,
        ));

        /** @var ContentSeoRecord[] $recordsBySiteId */
        $recordsBySiteId = ContentSeoRecord::find()
            ->where(['sectionHandle' => $section->handle])
            ->indexBy('siteId')
            ->all();

        foreach ($enabledSiteIds as $siteId) {
            $record = $recordsBySiteId[$siteId] ?? new ContentSeoRecord([
                'siteId' => $siteId,
                'sectionHandle' => $section->handle,
            ]);

            $record->sectionLabel = $section->name;
            $record->save(false);

            unset($recordsBySiteId[$siteId]);
        }

        // Whatever's left in $recordsBySiteId is a site the section is no longer enabled in.
        foreach ($recordsBySiteId as $record) {
            $record->delete();
        }
    }

    /**
     * Deletes every row for the given section handle. Called from `EVENT_AFTER_DELETE_SECTION` -
     * there's no foreign key to cascade this one, since `sectionHandle` is a plain string rather
     * than a reference to the (by-then-gone) section's ID.
     *
     * @param string $sectionHandle
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function deleteRowsForSection(string $sectionHandle): void {
        if (!$this->_tableExists()) return;

        ContentSeoRecord::deleteAll(['sectionHandle' => $sectionHandle]);
    }

    /**
     * Ensures every site has its global fallback row (`sectionHandle === ''`).
     *
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function ensureGlobalRows(): void {
        if (!$this->_tableExists()) return;

        $siteIds = Craft::$app->getSites()->getAllSiteIds();

        $existingSiteIds = ContentSeoRecord::find()
            ->select(['siteId'])
            ->where(['sectionHandle' => ''])
            ->column();

        foreach (array_diff($siteIds, $existingSiteIds) as $siteId) {
            $record = new ContentSeoRecord(['siteId' => $siteId, 'sectionHandle' => '']);
            $record->save(false);
        }
    }

    /**
     * Persists the title/description block config and robots directives posted from
     * `content-seo-section.twig` into the section's row for the given site. The row should
     * already exist (`ensureRowsForSection()` creates it as soon as the section has URLs for
     * this site), but a record is created here too in case this runs before that's had a chance
     * to. Called from `ContentSeoController::actionSaveSection()`.
     *
     * @param Section $section
     * @param int $siteId
     * @param array $titleConfig
     * @param array $descriptionConfig
     * @param array $robots
     * @param bool $sitemapEnabled whether this section should keep appearing in the sitemap -
     * see {@see isSitemapEnabled()}
     * @param int|null $imageId the fallback social image a `SeoField`'s own per-entry image falls
     * back to when it's left empty - see {@see getFallbackImage()}
     * @return bool whether the save succeeded
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function saveSectionConfig(Section $section, int $siteId, array $titleConfig, array $descriptionConfig, array $robots, bool $sitemapEnabled = true, ?int $imageId = null): bool {
        if (!$this->_tableExists()) return false;

        $record = ContentSeoRecord::findOne(['sectionHandle' => $section->handle, 'siteId' => $siteId])
            ?? new ContentSeoRecord(['sectionHandle' => $section->handle, 'siteId' => $siteId]);

        $record->sectionLabel = $section->name;
        $record->titleConfig = Json::encode(self::_ensureRowIds($titleConfig));
        $record->descriptionConfig = Json::encode(self::_ensureRowIds($descriptionConfig));
        $record->robots = Json::encode($robots);
        $record->sitemapEnabled = $sitemapEnabled;
        $record->imageId = $imageId;

        return $record->save();
    }

    /**
     * Persists the title/description block config, robots directives, and fallback image posted
     * from `global-seo.twig` into the given site's global bundle row (`sectionHandle === ''`).
     * The row should already exist (`ensureGlobalRows()` creates it for every site), but a record
     * is created here too in case this runs before that's had a chance to. Leaves
     * `canonicalConfig` untouched - that page doesn't expose it. Called from
     * `ContentSeoController::actionSaveGlobal()`.
     *
     * @param int $siteId
     * @param array $titleConfig
     * @param array $descriptionConfig
     * @param array $robots
     * @param int|null $imageId the site-wide fallback social image a `SeoField`'s own per-entry
     * image falls back to when it's left empty and no section-level fallback is configured either
     * - see {@see getFallbackImage()}
     * @return bool whether the save succeeded
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function saveGlobalConfig(int $siteId, array $titleConfig, array $descriptionConfig, array $robots = [], ?int $imageId = null): bool {
        if (!$this->_tableExists()) return false;

        $record = ContentSeoRecord::findOne(['sectionHandle' => '', 'siteId' => $siteId])
            ?? new ContentSeoRecord(['siteId' => $siteId, 'sectionHandle' => '']);

        $record->titleConfig = Json::encode(self::_ensureRowIds($titleConfig));
        $record->descriptionConfig = Json::encode(self::_ensureRowIds($descriptionConfig));
        $record->robots = Json::encode($robots);
        $record->imageId = $imageId;

        return $record->save();
    }

    /**
     * Persists the `llms.txt` title/summary posted from `txt-llms.twig` into the given site's
     * global bundle row (`sectionHandle === ''`) - the same row `saveGlobalConfig()` writes the
     * rest of the global bundle to, but kept as its own method/action
     * (`ContentSeoController::actionSaveLlms()`) since `txt-llms.twig` is its own page with its
     * own form, not a section of `global-seo.twig`'s.
     *
     * @param int $siteId
     * @param string|null $title
     * @param string|null $summary
     * @return bool whether the save succeeded
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function saveLlmsTxtConfig(int $siteId, ?string $title, ?string $summary): bool {
        if (!$this->_tableExists()) return false;

        $record = ContentSeoRecord::findOne(['sectionHandle' => '', 'siteId' => $siteId])
            ?? new ContentSeoRecord(['siteId' => $siteId, 'sectionHandle' => '']);

        $title = trim((string)$title);
        $summary = trim((string)$summary);

        $record->llmsTxtTitle = $title !== '' ? $title : null;
        $record->llmsTxtSummary = $summary !== '' ? $summary : null;

        $saved = $record->save();

        if ($saved) {
            CoreSeoGeo::getInstance()->getLlms()->clearCache();
            CoreSeoGeo::getInstance()->getLlmsFull()->clearCache();
        }

        return $saved;
    }

    /**
     * Returns the given site's global bundle row (`sectionHandle === ''`), or `null` if there
     * isn't one yet - the table doesn't exist. Used by `ContentSeoController::actionGlobal()` to
     * populate the Global SEO/GEO settings page.
     *
     * @param int $siteId
     * @return ContentSeoRecord|null
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getGlobalRow(int $siteId): ?ContentSeoRecord {
        return $this->getRowForSection('', $siteId);
    }

    /**
     * Returns every row for the given site: the global bundle first, then one per section. Used
     * by `ContentSeoController::actionIndex()` to populate the Content SEO settings table.
     *
     * @param int $siteId
     * @return ContentSeoRecord[]
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getRowsForSite(int $siteId): array {
        if (!$this->_tableExists()) return [];

        return ContentSeoRecord::find()
            ->where(['siteId' => $siteId])
            // '' sorts before any real handle, which puts the global bundle row first.
            ->orderBy(['sectionHandle' => SORT_ASC])
            ->all();
    }

    /**
     * Returns the row for one section on one site, or `null` if there isn't one - either the
     * table doesn't exist yet, or the section isn't enabled for this site. Used by
     * `ContentSeoController::actionSection()` to populate that section's own Content SEO
     * settings page.
     *
     * @param string $sectionHandle
     * @param int $siteId
     * @return ContentSeoRecord|null
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getRowForSection(string $sectionHandle, int $siteId): ?ContentSeoRecord {
        if (!$this->_tableExists()) return null;

        return ContentSeoRecord::findOne(['sectionHandle' => $sectionHandle, 'siteId' => $siteId]);
    }

    /**
     * Whether the given section should still be included in the sitemap (both its index entry
     * and its own sub-sitemap) - the toggle set from that section's own Content SEO settings page
     * (`content-seo-section.twig`). Defaults to `true` when there's no row to check - the table
     * doesn't exist yet, or the section has no row for this site - matching the toggle's own
     * on-by-default behaviour. Used by `SitemapService::getGroups()`.
     *
     * @param string $sectionHandle
     * @param int $siteId
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.2.1
     */
    public function isSitemapEnabled(string $sectionHandle, int $siteId): bool {
        return $this->getRowForSection($sectionHandle, $siteId)?->sitemapEnabled ?? true;
    }

    /**
     * Resolves the fallback social image a `SeoField`'s own per-entry image should fall back to
     * when it's left empty: the element's own section row's image first, falling back to the
     * site's global bundle row's image, the same two-step chain
     * {@see \digitalastronaut\craftcoreseogeo\fields\SeoField::_resolveFormatRows()} uses for the
     * title/description config. An element with no section (not an `Entry`, or an `Entry` with
     * none) goes straight to the global row.
     *
     * @param ElementInterface|null $element
     * @return Asset|null
     *
     * @author      Digitalastronaut
     * @since       v1.9.0
     */
    public function getFallbackImage(?ElementInterface $element): ?Asset {
        if (!$this->_tableExists()) return null;

        $siteId = (int)($element?->siteId ?? Craft::$app->getSites()->getCurrentSite()->id);
        $sectionHandle = $element instanceof Entry ? $element->getSection()?->handle : null;

        $sectionRow = $sectionHandle !== null ? $this->getRowForSection($sectionHandle, $siteId) : null;

        return $sectionRow?->getImageAsset() ?? $this->getGlobalRow($siteId)?->getImageAsset();
    }

    /**
     * Resolves the `SeoData` that actually applies to `$element` right now: its own `SeoField`
     * value when it has one, or the sitewide title/description-block fallback
     * ({@see getFallbackSeoData()}) when it doesn't. `null` when the element has a `SeoField`
     * but its value isn't actually a `SeoData` instance (a corrupt/legacy value) - treated as
     * "nothing to resolve" rather than guessing, matching what the pre-extraction inline version
     * of this in `PluginTrait::_renderSeoTags()` already did. Used by both that meta-tag
     * injection and `LlmsService` for llms.txt's title/description/robots-exclusion per entry.
     *
     * @param ElementInterface $element
     * @return SeoData|null
     *
     * @author      Digitalastronaut
     * @since       v1.15.0
     */
    public function resolveSeoData(ElementInterface $element): ?SeoData {
        $field = SeoField::getFieldsOn($element)[0] ?? null;

        if ($field === null) {
            return $this->getFallbackSeoData($element);
        }

        $seo = $element->getFieldValue($field->handle);

        return $seo instanceof SeoData ? $seo : null;
    }

    /**
     * Builds the meta title/description an element falls back to when its field layout has no
     * `SeoField` of its own, by resolving the element's site's global bundle row
     * (`getGlobalRow()`) title/description config against it. Every other `SeoData` attribute
     * (canonical URL, robots, image) is left at its default, since there's no per-element field
     * value to source those from. Moved here from `PluginTrait` (and made public) so
     * `resolveSeoData()` - and anything else that needs the same resolution - can call it
     * without reaching into that trait's private methods.
     *
     * @param ElementInterface $element
     * @return SeoData
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    public function getFallbackSeoData(ElementInterface $element): SeoData {
        $row = $this->getGlobalRow((int)$element->siteId);

        return new SeoData([
            'metaTitle' => SeoField::resolveFormatRows($row?->titleConfig !== null ? Json::decode($row->titleConfig) : [], $element),
            'metaDescription' => SeoField::resolveFormatRows($row?->descriptionConfig !== null ? Json::decode($row->descriptionConfig) : [], $element),
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Whether `coreseogeo_seodata` actually exists yet. Every other public method on this
     * service bails out (as a no-op) when this is `false`, rather than letting the query fail -
     * this service's `syncSections()` runs unconditionally during this plugin's `init()` (see
     * `PluginTrait::registerContentSeoSync()`), which for a console request includes
     * `migrate/up` itself: the very command that creates this table in the first place. Without
     * this guard, a fresh install (or a deploy that applies project config before this plugin's
     * own migrations) would crash on every console/CP request before the migration ever gets a
     * chance to run.
     *
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _tableExists(): bool {
        return $this->_tableExists ??= Craft::$app->getDb()->tableExists(Table::SEODATA);
    }

    /**
     * Deletes rows whose `sectionHandle` doesn't match any of the given, currently-existing
     * sections (and isn't the global bundle's `''`) - the cleanup half of `syncSections()` for
     * sections that were deleted without going through `deleteRowsForSection()` (e.g. drift from
     * before this plugin was installed, or a project config change applied out of band).
     *
     * @param Section[] $sections every currently-existing section
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.1.0
     */
    private function _pruneOrphanedSectionRows(array $sections): void {
        $handles = array_map(static fn(Section $section): string => $section->handle, $sections);
        $handles[] = '';

        ContentSeoRecord::deleteAll(['not in', 'sectionHandle', $handles]);
    }

    /**
     * Assigns a stable `rowId` to every posted title/description config row that doesn't already
     * have one - a brand new row, added via the "Add a block" button, has nothing posted for it
     * at all. An existing row keeps the `rowId` it was loaded with (round-tripped through the
     * settings page's own hidden `rowId` input - see `includeRowId` on the `editableTableField()`
     * calls in `global-seo.twig`/`content-seo-section.twig`), rather than getting a new one on
     * every save: `SeoField` matches a per-entry override against the row it was entered for by
     * this id, so reassigning it on every save would orphan every existing override the moment
     * the config is next touched at all, even for an edit to an unrelated row.
     *
     * @param array $rows
     * @return array
     *
     * @author      Digitalastronaut
     * @since       v1.2.0
     */
    private static function _ensureRowIds(array $rows): array {
        foreach ($rows as &$row) {
            if (!\is_array($row)) continue;

            if (($row['rowId'] ?? '') === '') {
                $row['rowId'] = StringHelper::UUID();
            }
        }
        unset($row);

        return $rows;
    }
}
