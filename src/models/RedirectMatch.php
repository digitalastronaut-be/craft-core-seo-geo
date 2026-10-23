<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\models;

/**
 * Class RedirectMatch
 *
 * The outcome of `RedirectsService::findMatch()`: which `Redirect` row matched, and the
 * final destination to send the visitor to - already resolved for a regex match's capture
 * groups, so nothing downstream needs to know whether the match was exact or regex.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.16.0
 */
final class RedirectMatch {
    /**
     * @param int $redirectId the matched `Redirect` row's `id`, for `RedirectsService::incrementHitCount()`
     * @param string|null $destinationUri `null` only when `statusCode` is `Redirect::STATUS_CODE_GONE`
     * @param int $statusCode one of `Redirect::STATUS_CODE_*`
     *
     * @author      Digitalastronaut
     * @since       v1.16.0
     */
    public function __construct(
        public readonly int $redirectId,
        public readonly ?string $destinationUri,
        public readonly int $statusCode,
    ) {
    }
}
