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
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use craft\helpers\UrlHelper;

use digitalastronaut\craftcoreseogeo\db\Table;
use digitalastronaut\craftcoreseogeo\elements\conditions\StructuredDataCondition;
use digitalastronaut\craftcoreseogeo\elements\db\StructuredDataQuery;

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
     * @var string[] Every global structured data type this element can represent.
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
     * @var string Which of `TYPES` this object represents.
     *
     * @since v1.0.0
     */
    public string $type = self::TYPE_ORGANIZATION;

    /**
     * @var array<string, array{template: string}> Raw Twig template text per property, the
     * same shape (and the same rendering path) as `StructuredDataField::$properties`.
     *
     * @since v1.0.0
     */
    public array $properties = [];

    public static function displayName(): string { return Craft::t('core-seo-geo', 'Structured Data'); }
    public static function pluralDisplayName(): string { return Craft::t('core-seo-geo', 'Structured Data'); }
    public static function refHandle(): ?string { return 'structureddata'; }

    public static function trackChanges(): bool { return true; }
    public static function hasTitles(): bool { return true; }
    public static function hasStatuses(): bool { return true; }

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
     * @param User $user
     * @return bool
     */
    public function canSave(User $user): bool {
        if (parent::canSave($user)) return true;
        return $user->can('saveStructuredData');
    }

    /**
     * @param User $user
     * @return bool
     */
    public function canDelete(User $user): bool {
        if (parent::canDelete($user)) return true;
        return $user->can('deleteStructuredData');
    }

    public function getPostEditUrl(): ?string {
        return UrlHelper::cpUrl('structured-data');
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
        return [
            ['key' => '*', 'label' => Craft::t('core-seo-geo', 'All structured data')],
        ];
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
        return ['type', 'dateCreated', 'dateUpdated'];
    }

    /**
     * @return string
     */
    protected function cpEditUrl(): ?string {
        return sprintf('structured-data/%s', $this->getCanonicalId());
    }

    /**
     * @return array
     */
    protected function defineRules(): array {
        return array_merge(parent::defineRules(), [
            [['type'], 'in', 'range' => self::TYPES],
            [['properties'], 'safe'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void {
        if (!$this->propagating) {
            Db::upsert(Table::STRUCTUREDDATA, [
                'id' => $this->id,
                'type' => $this->type,
                'properties' => $this->properties,
            ], [
                'type' => $this->type,
                'properties' => $this->properties,
            ]);
        }

        parent::afterSave($isNew);
    }
}
