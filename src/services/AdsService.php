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
 * Class AdsService
 *
 * Generates `ads.txt` per the IAB Tech Lab ads.txt v1.0.3 spec
 * ({@link https://iabtechlab.com/ads-txt/}) - the data records from section 2.1 (one
 * `<domain>, <account ID>, <relationship>[, <certification authority ID>]` line per
 * authorized seller), followed by the optional file-level variables from section 2.2
 * (`OWNERDOMAIN`, `MANAGERDOMAIN`, `CONTACT`, `SUBDOMAIN`). Like `humans.txt`, there's no safe
 * default content, so an unconfigured install just produces an empty file.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.12.0
 */
class AdsService extends Component {
    /**
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.12.0
     */
    public function getAdsTxt(): string {
        $settings = CoreSeoGeo::getInstance()->getSettings();
        $blocks = [];

        if ($settings->adsTxtRecords !== []) {
            $lines = [];

            foreach ($settings->adsTxtRecords as $record) {
                $parts = [
                    $record['domain'] ?? '',
                    $record['accountId'] ?? '',
                    $record['relationship'] ?? '',
                ];

                if (($record['certAuthorityId'] ?? '') !== '') {
                    $parts[] = $record['certAuthorityId'];
                }

                $lines[] = implode(', ', $parts);
            }

            $blocks[] = implode("\n", $lines);
        }

        $variableLines = [];

        if (($settings->adsTxtOwnerDomain ?? '') !== '') {
            $variableLines[] = "OWNERDOMAIN={$settings->adsTxtOwnerDomain}";
        }

        foreach ($settings->adsTxtManagerDomains as $manager) {
            $value = ($manager['country'] ?? '') !== ''
                ? "{$manager['domain']}, {$manager['country']}"
                : $manager['domain'];

            $variableLines[] = "MANAGERDOMAIN={$value}";
        }

        foreach ($settings->adsTxtContacts as $contact) {
            $variableLines[] = "CONTACT={$contact['value']}";
        }

        foreach ($settings->adsTxtSubdomains as $subdomain) {
            $variableLines[] = "SUBDOMAIN={$subdomain['value']}";
        }

        if ($variableLines !== []) {
            $blocks[] = implode("\n", $variableLines);
        }

        if (trim((string)$settings->adsTxtExtra) !== '') {
            $blocks[] = trim($settings->adsTxtExtra);
        }

        if ($blocks === []) return '';

        return implode("\n\n", $blocks) . "\n";
    }
}
