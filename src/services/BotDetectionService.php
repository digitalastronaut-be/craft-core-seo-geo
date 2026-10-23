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
use craft\helpers\Json;

/**
 * Class BotDetectionService
 *
 * Matches a request's `User-Agent` against a bundled list of known AI crawler/agent
 * user-agent substrings (`static/aibots/bots.json`), the same way `SchemaOrgService`
 * ships its own static reference data instead of a database table for data that's
 * closer to a config file than content.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.6
 */
class BotDetectionService extends Component {
    private const DATA_FILE = __DIR__ . '/../static/aibots/bots.json';

    /**
     * @var string[]|null
     */
    private ?array $_bots = null;

    /**
     * @param string $userAgent
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function isAiBot(string $userAgent): bool {
        return $this->getDetectedBotName($userAgent) !== null;
    }

    /**
     * @param string $userAgent
     * @return string|null The matched bot name, or `null` if `$userAgent` doesn't match
     * any known bot.
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function getDetectedBotName(string $userAgent): ?string {
        if ($userAgent === '') {
            return null;
        }

        foreach ($this->getKnownBots() as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return $bot;
            }
        }

        return null;
    }

    /**
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.0.6
     */
    public function getKnownBots(): array {
        return $this->_bots ??= $this->_loadBots();
    }

    /**
     * @return string[]
     */
    private function _loadBots(): array {
        if (!is_file(self::DATA_FILE)) {
            return [];
        }

        $raw = file_get_contents(self::DATA_FILE);
        if ($raw === false) {
            return [];
        }

        $decoded = Json::decodeIfJson($raw);

        return is_array($decoded) ? $decoded : [];
    }
}
