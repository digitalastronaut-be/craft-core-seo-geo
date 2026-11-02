<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\records;

use Craft;
use craft\db\ActiveRecord;

use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class TxtFileContent
 *
 * Active record for the `coreseogeo_txt_file_content` table: a single global row holding the
 * content behind `robots.txt`, `humans.txt`, `ads.txt`, `security.txt` and `trust.txt` - crawler
 * rules, team credits, ad network authorizations, security contacts/expiry, trust signals. This
 * used to live on the plugin's own `Settings` model, persisted into project config - but this
 * install doesn't distinguish dev/staging/production for these files at all, and that content is
 * routinely updated by non-developer staff (a yearly `security.txt` renewal, a new ad network
 * partnership) who need to edit it directly in production, where `allowAdminChanges` is commonly
 * off and project config writes are blocked. `TxtFilesService::getContent()`/`saveContent()` own
 * the single-row get-or-create lookup and the normalization logic; this is a thin data-access
 * wrapper, same as `ContentSeo`.
 *
 * @property int $id
 * @property bool $enableRobotsTxt
 * @property bool $robotsTxtDisallowCp
 * @property string|null $robotsTxtBlockedBots JSON-encoded string[], decode with `craft\helpers\Json::decode()`
 * @property string|null $robotsTxtRules JSON-encoded array<int, array{path: string, type: string, userAgent: string}>
 * @property string|null $robotsTxtExtra
 * @property string|null $humansTxtTeam JSON-encoded array<int, array{role: string, name: string, contact: string, from: string}>, decode with `craft\helpers\Json::decode()`
 * @property string|null $humansTxtThanks JSON-encoded, same shape as `$humansTxtTeam`
 * @property string|null $humansTxtStandards
 * @property string|null $humansTxtLastUpdated `Y-m-d`
 * @property string|null $humansTxtExtra
 * @property string|null $adsTxtRecords JSON-encoded array<int, array{domain: string, accountId: string, relationship: string, certAuthorityId: string}>
 * @property string|null $adsTxtOwnerDomain
 * @property string|null $adsTxtManagerDomains JSON-encoded array<int, array{domain: string, country: string}>
 * @property string|null $adsTxtContacts JSON-encoded array<int, array{value: string}>
 * @property string|null $adsTxtSubdomains JSON-encoded array<int, array{value: string}>
 * @property string|null $adsTxtExtra
 * @property string|null $securityTxtContacts JSON-encoded array<int, array{value: string}>
 * @property string|null $securityTxtExpires `DateTime::ATOM` once read through `TxtFilesService::getContent()`
 * @property string|null $securityTxtEncryption JSON-encoded array<int, array{value: string}>
 * @property string|null $securityTxtAcknowledgments JSON-encoded array<int, array{value: string}>
 * @property string|null $securityTxtPreferredLanguages
 * @property string|null $securityTxtPolicy JSON-encoded array<int, array{value: string}>
 * @property string|null $securityTxtHiring JSON-encoded array<int, array{value: string}>
 * @property string|null $securityTxtExtra
 * @property string|null $trustTxtBelongTo JSON-encoded array<int, array{value: string}>
 * @property string|null $trustTxtControl JSON-encoded array<int, array{value: string}>
 * @property string|null $trustTxtMember JSON-encoded array<int, array{value: string}>
 * @property string|null $trustTxtSocial JSON-encoded array<int, array{value: string}>
 * @property string|null $trustTxtContact JSON-encoded array<int, array{value: string}>
 * @property string|null $trustTxtDisclosure JSON-encoded array<int, array{value: string}>
 * @property string|null $trustTxtCredential JSON-encoded array<int, array{value: string}>
 * @property string|null $trustTxtCopyright JSON-encoded array<int, array{value: string}>
 * @property string|null $trustTxtDataTrainingAllowed `yes`, `no`, or `null` (not asserted)
 * @property string|null $trustTxtExtra
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.32.0
 */
class TxtFileContent extends ActiveRecord {
    /**
     * @since v1.33.0
     */
    public const string ROBOTS_RULE_ALLOW = 'allow';
    public const string ROBOTS_RULE_DISALLOW = 'disallow';

    /**
     * ads.txt v1.0.3's two account-relationship values ({@link https://iabtechlab.com/ads-txt/}
     * section 2.1) - whether the publisher directly controls the account on the named system
     * (`DIRECT`), or has authorized another entity to resell their ad space through it
     * (`RESELLER`).
     *
     * @since v1.32.0
     */
    public const string ADS_RELATIONSHIP_DIRECT = 'DIRECT';
    public const string ADS_RELATIONSHIP_RESELLER = 'RESELLER';

    /**
     * @inheritdoc
     *
     * Seeds `enableRobotsTxt`/`robotsTxtDisallowCp` with their column defaults for a brand new
     * record - without this, a freshly-`new`'d instance (e.g. `TxtFilesService::getContent()`
     * before the table/row exists yet) leaves both `null` until saved, which would show the
     * `robots.txt` settings page's lightswitches both off rather than on.
     *
     * @author      Digitalastronaut
     * @since       v1.33.0
     */
    public function init(): void {
        parent::init();

        if ($this->getIsNewRecord()) {
            $this->enableRobotsTxt = true;
            $this->robotsTxtDisallowCp = true;
        }
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.32.0
     */
    public static function tableName(): string {
        return Table::TXTFILECONTENT;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     *
     * @author      Digitalastronaut
     * @since       v1.33.0
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
     * @since       v1.32.0
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
     * @since       v1.32.0
     */
    public static function getTrustTxtDataTrainingAllowedOptions(): array {
        return [
            ['label' => Craft::t('core-seo-geo', 'Not asserted'), 'value' => ''],
            ['label' => Craft::t('core-seo-geo', 'Yes'), 'value' => 'yes'],
            ['label' => Craft::t('core-seo-geo', 'No'), 'value' => 'no'],
        ];
    }
}
