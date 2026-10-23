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
use craft\helpers\App;
use craft\helpers\UrlHelper;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\models\Settings;

/**
 * Class RobotsService
 *
 * Generates `robots.txt`. Computed on every request from `Settings` rather than written to
 * disk, same reasoning as `FaviconService` - there's nothing here expensive enough to cache.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.10.0
 */
class RobotsService extends Component {
    /**
     * `disallowRobots` (outside dev mode) always wins over every setting below it - the same
     * environment gate `PluginTrait::registerSiteUrlRules()` uses for the sitemap, but inverted: a sitemap hides itself
     * entirely when disallowed, while `robots.txt` has to actively say so instead, since a
     * missing file or a 404 is commonly read by crawlers as "no restrictions."
     *
     * @param int $siteId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.10.0
     */
    public function getRobotsTxt(int $siteId): string {
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        if ($generalConfig->disallowRobots && !App::devMode()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $settings = CoreSeoGeo::getInstance()->getSettings();
        $groups = [];

        if ($settings->robotsTxtDisallowCp) {
            $groups['*'][] = "Disallow: /{$generalConfig->cpTrigger}/";
        }

        foreach ($settings->robotsTxtRules as $rule) {
            $directive = ($rule['type'] ?? Settings::ROBOTS_RULE_DISALLOW) === Settings::ROBOTS_RULE_ALLOW
                ? 'Allow'
                : 'Disallow';
            $userAgent = ($rule['userAgent'] ?? '') !== '' ? $rule['userAgent'] : '*';

            $groups[$userAgent][] = "{$directive}: {$rule['path']}";
        }

        // The valid allow-all floor: never emit a group with nothing under it, and never emit
        // no groups at all.
        if ($groups === []) {
            $groups['*'] = ['Disallow:'];
        }

        $lines = [];

        foreach ($groups as $userAgent => $directives) {
            $lines[] = "User-agent: {$userAgent}";
            array_push($lines, ...$directives);
            $lines[] = '';
        }

        foreach ($settings->robotsTxtBlockedBots as $bot) {
            $lines[] = "User-agent: {$bot}";
            $lines[] = 'Disallow: /';
            $lines[] = '';
        }

        if ($settings->enableSitemap) {
            $lines[] = 'Sitemap: ' . UrlHelper::siteUrl('sitemap.xml', null, null, $siteId);
            $lines[] = '';
        }

        if (trim((string)$settings->robotsTxtExtra) !== '') {
            $lines[] = trim($settings->robotsTxtExtra);
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }
}
