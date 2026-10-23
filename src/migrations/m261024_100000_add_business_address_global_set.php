<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Addresses;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

/**
 * Class m261024_100000_add_business_address_global_set
 *
 * Creates the "Business Addresses" global set behind the Organization/LocalBusiness settings
 * pages' postal addresses - two real, native `craft\fields\Addresses` fields
 * (`organizationAddress`, `localBusinessAddress`), each on the same global set rather than on
 * `StructuredData` itself, so:
 *   - the address is a real, native Craft field again (live country-switching JS, standard
 *     `setFieldValuesFromRequest()`/`saveElement()` lifecycle - no bespoke save code);
 *   - it isn't owned by (and so doesn't clutter) any `coreseogeo_structureddata` row;
 *   - it never shows up on a `StructuredData` element's own edit screen, since it was never
 *     added to that element's field layout.
 *
 * Also re-homes any address a pre-release install already saved directly against a standalone
 * `Organization` row (back when this plugin managed the `Address` itself) onto this global set,
 * so that data survives the move.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.5.0
 */
class m261024_100000_add_business_address_global_set extends Migration {
    public const string GLOBAL_SET_HANDLE = 'coreSeoGeoBusinessAddresses';
    public const string ORGANIZATION_ADDRESS_FIELD_HANDLE = 'organizationAddress';
    public const string LOCAL_BUSINESS_ADDRESS_FIELD_HANDLE = 'localBusinessAddress';

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.5.0
     */
    public function safeUp(): bool {
        $fieldsService = Craft::$app->getFields();
        $globalsService = Craft::$app->getGlobals();

        $organizationField = $fieldsService->getFieldByHandle(self::ORGANIZATION_ADDRESS_FIELD_HANDLE);

        if ($organizationField === null) {
            $organizationField = Craft::createObject([
                'class' => Addresses::class,
                'name' => 'Organization Address',
                'handle' => self::ORGANIZATION_ADDRESS_FIELD_HANDLE,
                'minAddresses' => 0,
                'maxAddresses' => 1,
                'viewMode' => Addresses::VIEW_MODE_CARDS,
            ]);

            if (!$fieldsService->saveField($organizationField)) return false;
        }

        $localBusinessField = $fieldsService->getFieldByHandle(self::LOCAL_BUSINESS_ADDRESS_FIELD_HANDLE);

        if ($localBusinessField === null) {
            $localBusinessField = Craft::createObject([
                'class' => Addresses::class,
                'name' => 'Local Business Address',
                'handle' => self::LOCAL_BUSINESS_ADDRESS_FIELD_HANDLE,
                'minAddresses' => 0,
                'maxAddresses' => 1,
                'viewMode' => Addresses::VIEW_MODE_CARDS,
            ]);

            if (!$fieldsService->saveField($localBusinessField)) return false;
        }

        $globalSet = $globalsService->getSetByHandle(self::GLOBAL_SET_HANDLE);

        if ($globalSet === null) {
            $globalSet = new GlobalSet([
                'name' => 'Business Addresses',
                'handle' => self::GLOBAL_SET_HANDLE,
            ]);

            $layout = new FieldLayout(['type' => GlobalSet::class]);
            $tab = new FieldLayoutTab(['layout' => $layout, 'name' => 'Content']);
            $tab->setElements([
                new CustomField($organizationField),
                new CustomField($localBusinessField),
            ]);
            $layout->setTabs([$tab]);
            $globalSet->setFieldLayout($layout);

            if (!$globalsService->saveSet($globalSet)) return false;
        }

        $this->_rehomeExistingOrganizationAddress($globalSet, $organizationField);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.5.0
     */
    public function safeDown(): bool {
        $globalsService = Craft::$app->getGlobals();
        $fieldsService = Craft::$app->getFields();

        $globalSet = $globalsService->getSetByHandle(self::GLOBAL_SET_HANDLE);
        if ($globalSet !== null) $globalsService->deleteSet($globalSet);

        foreach ([self::ORGANIZATION_ADDRESS_FIELD_HANDLE, self::LOCAL_BUSINESS_ADDRESS_FIELD_HANDLE] as $handle) {
            $field = $fieldsService->getFieldByHandle($handle);
            if ($field !== null) $fieldsService->deleteField($field);
        }

        return true;
    }

    /**
     * A pre-release install of this plugin saved an `Organization` row's address directly onto
     * that row (`addresses.primaryOwnerId` pointing at the `coreseogeo_structureddata` element,
     * no `fieldId`) rather than onto this global set. Finds any such address still around and
     * re-points it at `$globalSet`/`$organizationField` instead, so it isn't lost by this move -
     * a direct `UPDATE` rather than going through `saveElement()`, since nothing about the
     * address's own data is changing, just which element owns it.
     *
     * @param GlobalSet $globalSet
     * @param \craft\base\FieldInterface $organizationField
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.5.0
     */
    private function _rehomeExistingOrganizationAddress(GlobalSet $globalSet, \craft\base\FieldInterface $organizationField): void {
        // `coreseogeo_structureddata` has one row per `(id, siteId)` - a plain join on `sd.id =
        // a.primaryOwnerId` would match once per site sharing that `id`, duplicating every
        // address. `GROUP BY a.id` collapses that back down to one row per address; which site
        // comes back for it doesn't matter, since the global set it resolves to has the same
        // element ID on every site regardless.
        $rows = (new Query())
            ->select(['a.id', 'a.primaryOwnerId AS oldOwnerId', 'MIN(es.siteId) AS siteId'])
            ->from(['a' => '{{%addresses}}'])
            ->innerJoin(['sd' => '{{%coreseogeo_structureddata}}'], '[[sd.id]] = [[a.primaryOwnerId]]')
            ->innerJoin(['es' => '{{%elements_sites}}'], '[[es.elementId]] = [[a.id]]')
            ->where([
                'sd.type' => 'Organization',
                'sd.mappingId' => null,
                'sd.ownerId' => null,
                'a.fieldId' => null,
            ])
            ->groupBy(['a.id', 'a.primaryOwnerId'])
            ->all($this->db);

        foreach ($rows as $row) {
            $siteGlobalSet = Craft::$app->getGlobals()->getSetByHandle(self::GLOBAL_SET_HANDLE, (int)$row['siteId']);

            if ($siteGlobalSet === null) continue;

            $this->update('{{%addresses}}', [
                'primaryOwnerId' => $siteGlobalSet->id,
                'fieldId' => $organizationField->id,
            ], ['id' => $row['id']]);

            $this->update('{{%elements_owners}}', [
                'ownerId' => $siteGlobalSet->id,
            ], ['elementId' => $row['id'], 'ownerId' => $row['oldOwnerId']]);
        }
    }
}
