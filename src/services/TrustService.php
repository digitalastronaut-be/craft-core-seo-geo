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

use craft\base\Component;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class TrustService
 *
 * Generates `trust.txt` per the JournalList trust.txt v0.1 spec
 * ({@link https://journallist.net/trust-txt/}) - `field=value` lines, one per declared value,
 * `#` comments (none generated). Like `humans.txt`/`ads.txt`, there's no safe default content
 * and no field is actually mandatory, so an unconfigured install just produces an empty file.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.14.0
 */
class TrustService extends Component {
    /**
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.14.0
     */
    public function getTrustTxt(): string {
        $settings = CoreSeoGeo::getInstance()->getSettings();
        $lines = [];

        foreach ($settings->trustTxtBelongTo as $row) {
            $lines[] = "belongto={$row['value']}";
        }

        foreach ($settings->trustTxtControl as $row) {
            $lines[] = "control={$row['value']}";
        }

        foreach ($settings->trustTxtMember as $row) {
            $lines[] = "member={$row['value']}";
        }

        foreach ($settings->trustTxtSocial as $row) {
            $lines[] = "social={$row['value']}";
        }

        foreach ($settings->trustTxtContact as $row) {
            $lines[] = "contact={$row['value']}";
        }

        foreach ($settings->trustTxtDisclosure as $row) {
            $lines[] = "disclosure={$row['value']}";
        }

        foreach ($settings->trustTxtCredential as $row) {
            $lines[] = "credential={$row['value']}";
        }

        foreach ($settings->trustTxtCopyright as $row) {
            $lines[] = "copyright={$row['value']}";
        }

        if ($settings->trustTxtDataTrainingAllowed !== null) {
            $lines[] = "datatrainingallowed={$settings->trustTxtDataTrainingAllowed}";
        }

        if (trim((string)$settings->trustTxtExtra) !== '') {
            $lines[] = trim($settings->trustTxtExtra);
        }

        if ($lines === []) return '';

        return implode("\n", $lines) . "\n";
    }
}
