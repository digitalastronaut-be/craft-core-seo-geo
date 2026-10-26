<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\elements;

use Craft;
use craft\base\Element;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\fieldlayoutelements\TitleField;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\db\Table;
use digitalastronaut\craftcoreseogeo\elements\conditions\StructuredDataCondition;
use digitalastronaut\craftcoreseogeo\elements\db\StructuredDataQuery;
use digitalastronaut\craftcoreseogeo\fieldlayoutelements\FieldMappingUiElement;
use digitalastronaut\craftcoreseogeo\fieldlayoutelements\JsonLdPreviewUiElement;
use digitalastronaut\craftcoreseogeo\records\StructuredDataMapping as StructuredDataMappingRecord;

/**
 * Class StructuredData
 *
 * A reusable, site-wide structured data object (an `Organization`, a `WebSite`, ...), as
 * opposed to the page-specific JSON-LD `StructuredDataField` computes per entry.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredData extends Element {
    public const string TYPE_ORGANIZATION = 'Organization';
    public const string TYPE_WEBSITE = 'WebSite';
    public const string TYPE_LOCAL_BUSINESS = 'LocalBusiness';
    public const string TYPE_PERSON = 'Person';

    /**
     * @var string[] The standalone, site-wide types this element owns. A mapping-backed row (see
     * `$mappingId`, `$ownerId`) isn't restricted to this list - `StructuredDataMapping::$type` is
     * a free-form schema.org type the mapping's own settings picked, so `defineRules()` validates
     * those against the full schema.org vocabulary instead.
     *
     * @since v1.0.0
     */
    public const array TYPES = [
        self::TYPE_ORGANIZATION,
        self::TYPE_WEBSITE,
        self::TYPE_LOCAL_BUSINESS,
        self::TYPE_PERSON,
    ];

    /**
     * @param string $type a `TYPES` constant
     * @return bool whether a standalone row of this type gets a postal address - see
     * `GlobalStructuredDataService::getAddress()`. Not a real field on this element at all: the
     * `Address` is a separately-owned element (`primaryOwnerId` pointed at this row, no
     * `fieldId`) that `GlobalStructuredDataService` manages directly, rather than something
     * added to this element's own field layout.
     *
     * @since v1.5.0
     */
    public static function typeHasAddress(string $type): bool {
        return in_array($type, [self::TYPE_ORGANIZATION, self::TYPE_LOCAL_BUSINESS], true);
    }

    /**
     * @var string Which of `TYPES` this object represents.
     *
     * @since v1.0.0
     */
    public string $type = self::TYPE_ORGANIZATION;

    /**
     * @var array<string, mixed> This object's computed JSON-LD property values, keyed by
     * property name. For a standalone object this is the final, ready-to-emit data; for a
     * mapping-backed row (`$mappingId`/`$ownerId` both set) it's exactly what
     * `StructuredDataService::renderProperties()` produced for that entry against the mapping's
     * `properties` templates; no Twig templates are stored here, those live on the mapping row.
     *
     * @since v1.0.0
     */
    public array $properties = [];

    /**
     * @var array<string, mixed> The raw form input a built-in standalone type's settings page
     * (Organization, WebSite, LocalBusiness, Person) was saved with - e.g. a logo's asset ID or
     * an address's individual parts - kept separately from `$properties` (the already-rendered
     * schema.org shape) so re-opening that settings page can repopulate its fields.
     * {@see \digitalastronaut\craftcoreseogeo\services\GlobalStructuredDataService} is what
     * reads and writes this. Always empty for a mapping-backed row.
     *
     * @since v1.5.0
     */
    public array $sourceData = [];

    /**
     * @var int|null The `StructuredDataMapping` this row was computed for, when it's not a
     * standalone object. Null and `$ownerId` null together mean standalone.
     *
     * @since v1.4.0 (renamed from `$fieldId`)
     */
    public ?int $mappingId = null;

    /**
     * @var int|null The entry (or other element) `$mappingId` computed this row for.
     *
     * @since v1.0.0
     */
    public ?int $ownerId = null;

    /**
     * Returns the full JSON-LD object: `@context`, `@type`, and every computed property. The
     * same shape `models\StructuredData::toJsonLd()` produces for a field value.
     *
     * @return array<string, mixed>
     *
     * @since v1.0.0
     */
    public function toJsonLd(): array {
        return CoreSeoGeo::getInstance()->getStructuredData()->toJsonLd($this->type, $this->properties);
    }

    public static function displayName(): string { return Craft::t('core-seo-geo', 'Structured Data'); }
    public static function pluralDisplayName(): string { return Craft::t('core-seo-geo', 'Structured Data'); }
    public static function refHandle(): ?string { return 'structureddata'; }

    public static function trackChanges(): bool { return true; }
    public static function hasTitles(): bool { return true; }
    public static function hasStatuses(): bool { return true; }

    /**
     * One row per `(element, site)`, not per element - lets a field-backed row's `properties`
     * (and a standalone object's) vary by site, matching how `elements_sites` scopes title and
     * field content. Craft's own edit screen only shows the site switcher once this is `true`
     * and more than one site exists.
     *
     * @return bool
     *
     * @since v1.0.0
     */
    public static function isLocalized(): bool { return true; }

    public function canDuplicate(User $user): bool {
        if (parent::canDuplicate($user)) return true;
        return $this->canSave($user);
    }

    public function canCreateDrafts(User $user): bool {
        return $this->canSave($user);
    }

    /**
     * @param User $user
     * @return bool
     */
    public function canView(User $user): bool {
        if (parent::canView($user)) return true;
        return $user->can('viewStructuredData');
    }

    /**
     * A mapping-backed row is computed and saved by `StructuredDataMappingService::syncEntry()`,
     * not edited directly - letting a human save over it would just get overwritten on the
     * owner's next save anyway.
     *
     * @param User $user
     * @return bool
     */
    public function canSave(User $user): bool {
        if ($this->ownerId !== null) return false;
        if (parent::canSave($user)) return true;
        return $user->can('saveStructuredData');
    }

    /**
     * @param User $user
     * @return bool
     */
    public function canDelete(User $user): bool {
        return $user->can('deleteStructuredData');
    }

    public function getPostEditUrl(): ?string {
        return UrlHelper::cpUrl('core-seo-geo/structured-data');
    }

    /**
     * @return StructuredDataQuery
     */
    public static function find(): ElementQueryInterface {
        return Craft::createObject(StructuredDataQuery::class, [static::class]);
    }

    /**
     * @return StructuredDataCondition
     */
    public static function createCondition(): ElementConditionInterface {
        return Craft::createObject(StructuredDataCondition::class, [static::class]);
    }

    /**
     * @return array[]
     */
    protected static function defineSources(string $context): array {
        $sources = [
            ['key' => '*', 'label' => Craft::t('core-seo-geo', 'All structured data')],
            ['heading' => Craft::t('core-seo-geo', 'Data types')],
        ];

        // `self::TYPES` only covers the built-in standalone types - a mapping's content
        // settings can pick any schema.org type (e.g. `Person`, `WebPage`), so those need to be
        // pulled in too for a source to exist for them.
        $mappedTypes = array_diff(CoreSeoGeo::getInstance()->getStructuredDataMappings()->getMappedTypes(), self::TYPES);
        sort($mappedTypes);

        foreach (array_merge(self::TYPES, $mappedTypes) as $type) {
            $sources[] = [
                'key' => "type:$type",
                'label' => Craft::t('core-seo-geo', $type),
                'criteria' => ['type' => $type],
            ];
        }

        return $sources;
    }

    protected static function includeSetStatusAction(): bool { return true; }

    /**
     * @return array
     */
    protected static function defineSortOptions(): array {
        return [
            'title' => Craft::t('app', 'Title'),
            ['label' => Craft::t('app', 'Date Created'), 'orderBy' => 'elements.dateCreated', 'attribute' => 'dateCreated', 'defaultDir' => 'desc'],
            ['label' => Craft::t('app', 'Date Updated'), 'orderBy' => 'elements.dateUpdated', 'attribute' => 'dateUpdated', 'defaultDir' => 'desc'],
        ];
    }

    /**
     * @return array
     */
    protected static function defineTableAttributes(): array {
        return [
            'type' => ['label' => Craft::t('core-seo-geo', 'Type')],
            'owner' => ['label' => Craft::t('core-seo-geo', 'Owner')],
            'mapping' => ['label' => Craft::t('core-seo-geo', 'Mapping')],
            'id' => ['label' => Craft::t('app', 'ID')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    /**
     * @return string[]
     */
    protected static function defineDefaultTableAttributes(string $source): array {
        return ['type', 'owner', 'dateUpdated'];
    }

    /**
     * @return string
     */
    protected function cpEditUrl(): ?string {
        return sprintf('core-seo-geo/structured-data/%s', $this->getCanonicalId());
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout {
        return static::defineFieldLayouts(null)[0] ?? null;
    }

    /**
     * Code-only layout, same reasoning as `StructuredDataField`'s own read-only input: there's
     * nothing here for an editor to configure, just the field mapping (when this row is
     * field-backed) and the computed JSON-LD, both read-only - so no designer route is
     * registered for it. A standalone `Organization`/`LocalBusiness` row's postal address isn't
     * here either - see `typeHasAddress()`.
     *
     * @return FieldLayout[]
     *
     * @since v1.0.0
     */
    protected static function defineFieldLayouts(?string $source): array {
        $layout = new FieldLayout(['type' => static::class]);
        $tab = new FieldLayoutTab(['name' => Craft::t('core-seo-geo', 'Structured Data'), 'layout' => $layout]);

        $tab->setElements([
            new TitleField(),
            new FieldMappingUiElement(),
            new JsonLdPreviewUiElement(),
        ]);

        $layout->setTabs([$tab]);

        return [$layout];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string {
        return match ($attribute) {
            'owner' => $this->_ownerHtml(),
            'mapping' => Html::encode($this->_mappingLabel()),
            default => parent::attributeHtml($attribute),
        };
    }

    /**
     * @return array
     */
    protected function defineRules(): array {
        return array_merge(parent::defineRules(), [
            [['type'], 'in', 'range' => self::TYPES, 'when' => fn(): bool => $this->mappingId === null],
            [['type'], function(string $attribute): void {
                if (CoreSeoGeo::getInstance()->getSchemaOrg()->getType($this->$attribute) === null) {
                    $this->addError($attribute, Craft::t('core-seo-geo', '{value} isn\'t a known schema.org type.', [
                        'value' => $this->$attribute,
                    ]));
                }
            }, 'when' => fn(): bool => $this->mappingId !== null],
            [['properties', 'sourceData', 'mappingId', 'ownerId'], 'safe'],
        ]);
    }

    /**
     * @inheritdoc
     *
     * No `$this->propagating` guard: Craft's own propagation to other sites never re-invokes
     * `afterSave()` (it fires exactly once, for whatever site was actually being saved), so
     * there's nothing to skip - a row only ever gets written for the site this save touched.
     * Other sites simply have no row yet until someone edits this element while viewing them,
     * the same lazy, per-site-on-demand behavior Craft's own field content has.
     */
    public function afterSave(bool $isNew): void {
        Db::upsert(Table::STRUCTUREDDATA, [
            'id' => $this->id,
            'siteId' => $this->siteId,
            'mappingId' => $this->mappingId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'properties' => $this->properties,
            'sourceData' => $this->sourceData,
        ], [
            'mappingId' => $this->mappingId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'properties' => $this->properties,
            'sourceData' => $this->sourceData,
        ]);

        parent::afterSave($isNew);
    }

    /**
     * @return string
     */
    private function _mappingLabel(): string {
        if ($this->mappingId === null) return '';

        $mapping = StructuredDataMappingRecord::findOne($this->mappingId);

        if ($mapping === null) return '';

        return $mapping->label ?? ($mapping->sectionHandle !== ''
            ? Craft::t('core-seo-geo', '{section} ({type})', ['section' => $mapping->sectionHandle, 'type' => $mapping->type])
            : $mapping->type);
    }

    /**
     * @return string
     */
    private function _ownerHtml(): string {
        if ($this->ownerId === null) return Html::tag('span', Craft::t('core-seo-geo', 'Global'), ['class' => ['light']]);

        $owner = Craft::$app->getElements()->getElementById($this->ownerId);

        if ($owner === null) return '';

        return Cp::elementChipHtml($owner, ['showActionMenu' => false]);
    }
}
