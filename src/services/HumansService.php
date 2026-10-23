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
 * Class HumansService
 *
 * Generates `humans.txt` (humanstxt.org) - unlike `robots.txt`, nothing here is machine-parsed,
 * so there's no safe default content and no environment gate: an unconfigured install simply
 * produces an empty file, and that's fine, nothing reads it but people.
 *
 * Deliberately does not credit Craft CMS or list installed plugins - that's a stack-fingerprint
 * handed to anyone looking for a known CVE to target, for no real benefit.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.11.0
 */
class HumansService extends Component {
    /**
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.11.0
     */
    public function getHumansTxt(): string {
        $settings = CoreSeoGeo::getInstance()->getSettings();
        $sections = [];

        if ($settings->humansTxtTeam !== []) {
            $sections[] = "/* TEAM */\n\n" . $this->_renderPeople($settings->humansTxtTeam);
        }

        if ($settings->humansTxtThanks !== []) {
            $sections[] = "/* THANKS */\n\n" . $this->_renderPeople($settings->humansTxtThanks);
        }

        $siteLines = [];

        if ($settings->humansTxtLastUpdated !== null) {
            $siteLines[] = "Last update: {$settings->humansTxtLastUpdated}";
        }

        if (trim((string)$settings->humansTxtStandards) !== '') {
            $siteLines[] = 'Standards: ' . trim($settings->humansTxtStandards);
        }

        if ($siteLines !== []) {
            $sections[] = "/* SITE */\n\n    " . implode("\n    ", $siteLines);
        }

        if (trim((string)$settings->humansTxtExtra) !== '') {
            $sections[] = trim($settings->humansTxtExtra);
        }

        if ($sections === []) return '';

        return implode("\n\n", $sections) . "\n";
    }

    /**
     * Renders a list of credited people - `Settings::$humansTxtTeam`/`$humansTxtThanks`, same
     * row shape either way - as the indented `<role>: <name>` blocks shared by both the TEAM
     * and THANKS sections, one blank line between people.
     *
     * @param array<int, array{role: string, name: string, contact: string, from: string}> $people
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.11.0
     */
    private function _renderPeople(array $people): string {
        $lines = [];

        foreach ($people as $person) {
            $role = $person['role'] ?? '';
            $name = $person['name'] ?? '';

            $lines[] = '    ' . ($role !== '' ? "{$role}: {$name}" : $name);

            foreach (['contact' => 'Contact', 'from' => 'From'] as $key => $label) {
                if (($person[$key] ?? '') !== '') {
                    $lines[] = "    {$label}: {$person[$key]}";
                }
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }
}
