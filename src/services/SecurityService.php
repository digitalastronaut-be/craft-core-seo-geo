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
use craft\helpers\UrlHelper;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;

/**
 * Class SecurityService
 *
 * Generates `security.txt` per RFC 9116 ({@link https://www.rfc-editor.org/rfc/rfc9116}) - a
 * `<Field>: <value>` line per declared field, in the field order real-world examples
 * conventionally use, CRLF-terminated as the RFC's syntax section requires (every other text
 * file this plugin generates uses a bare `\n`; this is the one exception).
 *
 * `Canonical` isn't a `Settings` property at all - unlike every other field here, it's not
 * admin-authored content, it's simply the URL(s) this plugin is actually serving the file from,
 * which it already knows. Auto-deriving it is the same reasoning as `robots.txt`'s `Sitemap:`
 * line: asking an admin to type their own site's URL back to it would be pure boilerplate.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.13.0
 */
class SecurityService extends Component {
    /**
     * `Contact` and `Expires` are both mandatory per RFC 9116 §2.5.3/§2.5.5 - a file missing
     * either would be non-compliant, which is worse than not serving one at all, so this
     * returns an empty string rather than a partial file when either is missing (regardless of
     * `Settings::$enableSecurityTxt`; `SecurityController` 404s on an empty result).
     *
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.13.0
     */
    public function getSecurityTxt(int $siteId): string {
        $settings = CoreSeoGeo::getInstance()->getSettings();

        if ($settings->securityTxtContacts === [] || $settings->securityTxtExpires === null) {
            return '';
        }

        $lines = [];

        foreach ($settings->securityTxtContacts as $contact) {
            $lines[] = "Contact: {$contact['value']}";
        }

        $lines[] = "Expires: {$settings->securityTxtExpires}";

        foreach ($settings->securityTxtEncryption as $row) {
            $lines[] = "Encryption: {$row['value']}";
        }

        foreach ($settings->securityTxtAcknowledgments as $row) {
            $lines[] = "Acknowledgments: {$row['value']}";
        }

        if (($settings->securityTxtPreferredLanguages ?? '') !== '') {
            $lines[] = "Preferred-Languages: {$settings->securityTxtPreferredLanguages}";
        }

        foreach ($this->getCanonicalUrls($siteId) as $url) {
            $lines[] = "Canonical: {$url}";
        }

        foreach ($settings->securityTxtPolicy as $row) {
            $lines[] = "Policy: {$row['value']}";
        }

        foreach ($settings->securityTxtHiring as $row) {
            $lines[] = "Hiring: {$row['value']}";
        }

        if (trim((string)$settings->securityTxtExtra) !== '') {
            foreach (preg_split('/\r\n|\r|\n/', trim($settings->securityTxtExtra)) as $extraLine) {
                $lines[] = $extraLine;
            }
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * The URL(s) this file is actually authoritative for - both the preferred
     * `.well-known/security.txt` location and the deprecated-but-still-checked root copy, per
     * whichever of those `PluginTrait::registerSiteUrlRules()` has registered.
     *
     * @param int $siteId
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.13.0
     */
    public function getCanonicalUrls(int $siteId): array {
        return [
            UrlHelper::siteUrl('.well-known/security.txt', null, null, $siteId),
            UrlHelper::siteUrl('security.txt', null, null, $siteId),
        ];
    }
}
