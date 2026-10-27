<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\seochecks;

/**
 * Enum SeoScoreCheckStatus
 *
 * The outcome tier of a {@see SeoScoreCheckResult}. `Warning` sits between `Passed` and `Failed`
 * for things that are technically fine but worth a second look (a title outside the recommended
 * length, an SEO image missing alt text, a canonical URL pointing elsewhere) -- cases that used
 * to report `valid: true` with an explanatory message nobody saw, since the CP only surfaced
 * messages for failures. `Warning` still earns its check's full weight in the score, same as
 * `Passed` -- only `Failed` doesn't -- so introducing it doesn't change anyone's existing score.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.29.0
 */
enum SeoScoreCheckStatus: string {
    case Passed = 'passed';
    case Warning = 'warning';
    case Failed = 'failed';
}
