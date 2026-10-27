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

use DateTime;

use craft\base\Model;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\records\SeoScore as SeoScoreRecord;

/**
 * Class SeoScore
 *
 * The read-side view of one `coreseogeo_seoscores` row, returned by
 * `SeoScoreService::getScore()`. Kept separate from `SeoScoreRecord` (the Active Record) so
 * columns can be added to the table later without changing what callers of `getScore()` already
 * depend on.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
class SeoScore extends Model {
    /**
     * @since v1.0.4
     */
    public ?int $id = null;
    public ?int $elementId = null;
    public ?int $siteId = null;
    public ?int $fieldId = null;
    public ?int $score = null;
    public bool $pending = false;

    /**
     * @var array<int, array{key: string, label: string, weight: int, status: string, applicable: bool, message: string}>
     * the per-check breakdown from the last completed run, oldest-registered-check first. Empty
     * if the row predates this column or hasn't been calculated yet. `status` is one of
     * {@see \digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus}'s backing values.
     *
     * @since v1.0.6
     */
    public array $checks = [];

    /**
     * @since v1.0.4
     */
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    /**
     * Builds a model from its backing Active Record.
     *
     * @param SeoScoreRecord $record
     * @return self
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public static function fromRecord(SeoScoreRecord $record): self {
        return new self([
            'id' => $record->id,
            'elementId' => $record->elementId,
            'siteId' => $record->siteId,
            'fieldId' => $record->fieldId,
            'score' => $record->score,
            'pending' => (bool)$record->pending,
            'checks' => $record->checks !== null ? Json::decode($record->checks) : [],
            'dateCreated' => DateTimeHelper::toDateTime($record->dateCreated) ?: null,
            'dateUpdated' => DateTimeHelper::toDateTime($record->dateUpdated) ?: null,
            'uid' => $record->uid,
        ]);
    }
}
