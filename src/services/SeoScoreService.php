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
use craft\base\Element;
use craft\base\ElementInterface;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Html;
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\fields\SeoField;
use digitalastronaut\craftcoreseogeo\jobs\CalculateSeoScore;
use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\models\SeoScore as SeoScoreModel;
use digitalastronaut\craftcoreseogeo\records\SeoScore as SeoScoreRecord;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckContext;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckInterface;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

/**
 * Class SeoScoreService
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.4
 */
class SeoScoreService extends Component {
    /**
     * @event RegisterComponentTypesEvent registers the {@see SeoScoreCheckInterface} class
     * names that make up the SEO score checklist.
     *
     * @since v1.0.4
     */
    public const string EVENT_REGISTER_SEO_SCORE_CHECKS = 'registerSeoScoreChecks';

    /**
     * @var SeoScoreCheckInterface[]|null
     * @see getRegisteredChecks()
     *
     * @since v1.0.4
     */
    private ?array $_checks = null;

    /**
     * Returns every registered SEO score check, instantiated and memoized for the rest of the
     * request.
     *
     * @return SeoScoreCheckInterface[]
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getRegisteredChecks(): array {
        if ($this->_checks === null) {
            $event = new RegisterComponentTypesEvent();
            $this->trigger(self::EVENT_REGISTER_SEO_SCORE_CHECKS, $event);

            $this->_checks = array_map(
                static fn(string $type): SeoScoreCheckInterface => Craft::createObject($type),
                $event->types,
            );
        }

        return $this->_checks;
    }

    /**
     * Queues a `CalculateSeoScore` job for this element+field. The only thing this method does
     * -- the actual check-running and persistence lives in `process()`, which the job calls once
     * it's run. Callers that also want the CP to show "recalculating..." in the meantime should
     * call `markPending()` themselves beforehand; this method doesn't do that on their behalf.
     *
     * @param ElementInterface $element the element to score
     * @param int $fieldId the `SeoField`'s field ID
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function calculate(ElementInterface $element, int $fieldId): void {
        /** @var Element $element */
        Craft::$app->getQueue()->push(new CalculateSeoScore([
            'elementId' => $element->id,
            'siteId' => $element->getSite()->id,
            'fieldId' => $fieldId,
        ]));
    }

    /**
     * Runs every registered, applicable check against `$seoData` and persists the resulting
     * score. Clears `pending`. Called from `CalculateSeoScore::execute()`, not meant to be
     * called directly from request-time code -- use `calculate()` for that, which queues this
     * instead of running it inline.
     *
     * @param ElementInterface $element the element being scored
     * @param SeoData $seoData the element's resolved `SeoField` value
     * @param int $fieldId the `SeoField`'s field ID
     * @return SeoScoreModel
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function process(ElementInterface $element, SeoData $seoData, int $fieldId): SeoScoreModel {
        $context = new SeoScoreCheckContext($element, $seoData, $fieldId);
        $disabledChecks = CoreSeoGeo::getInstance()->getSettings()->disabledSeoScoreChecks;

        $totalWeight = 0;
        $earnedWeight = 0;
        $checksData = [];

        foreach ($this->getRegisteredChecks() as $check) {
            if (in_array($check->getKey(), $disabledChecks, true)) continue;

            $result = $check->validate($context);
            $weight = $check->getWeight();

            $checksData[] = [
                'key' => $check->getKey(),
                'label' => $check->getLabel(),
                'weight' => $weight,
                'status' => $result->status->value,
                'applicable' => $result->applicable,
                'message' => $result->message,
            ];

            if (!$result->applicable) continue;

            $totalWeight += $weight;

            if ($result->status !== SeoScoreCheckStatus::Failed) $earnedWeight += $weight;
        }

        $record = $this->_findOrCreateRecord($element, $fieldId);
        $record->score = $totalWeight > 0 ? (int)round($earnedWeight / $totalWeight * 100) : 100;
        $record->pending = false;
        // Denormalized for DuplicateContentCheck, which needs to look sideways at other
        // elements' rows without scanning the field's own JSON value.
        $record->metaTitle = $seoData->metaTitle;
        $record->metaDescription = $seoData->metaDescription;
        $record->checks = Json::encode($checksData);
        $record->save(false);

        return SeoScoreModel::fromRecord($record);
    }

    /**
     * @param ElementInterface $element
     * @param int $fieldId
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function markPending(ElementInterface $element, int $fieldId): void {
        $record = $this->_findOrCreateRecord($element, $fieldId);
        $record->pending = true;
        $record->save(false);
    }

    /**
     * @param ElementInterface $element
     * @param int $fieldId
     * @return SeoScoreModel|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    public function getScore(ElementInterface $element, int $fieldId): ?SeoScoreModel {
        /** @var Element $element */
        $record = SeoScoreRecord::findOne([
            'elementId' => $element->id,
            'siteId' => $element->getSite()->id,
            'fieldId' => $fieldId,
        ]);

        return $record !== null ? SeoScoreModel::fromRecord($record) : null;
    }

    /**
     * Renders `$score` as the colored "passed/applicable" chip used in the element index (via
     * `SeoField::getPreviewHtml()`). Hovering (or focusing) the chip opens a popup -- built on
     * the same `.core-seo-geo-info-icon-wrap`/`tooltips.js` popover pattern already used for the
     * structured-data and redirects forms -- listing every check via `renderChecklist()`.
     *
     * Unlike `renderChipPill()`, this one carries an `id` (see `chipId()`) and
     * `data-element-id`/`data-site-id`/`data-field-id` attributes -- an element index page can
     * show up to 100 of these at once, too many to poll individually
     * ({@see \digitalastronaut\craftcoreseogeo\controllers\SeoScoreController::actionBatchStatus()}),
     * so `admin/elementIndexScorePolling.js` instead scans the page for whichever chips are
     * still `--pending`, and needs these attributes to build its one shared batch request and to
     * find the right node to swap once that request resolves.
     *
     * @param SeoScoreModel|null $score
     * @param int $elementId
     * @param int $siteId
     * @param int $fieldId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.27.0
     */
    public function renderChip(?SeoScoreModel $score, int $elementId, int $siteId, int $fieldId): string {
        $attrs = $this->_chipAttrs($elementId, $siteId, $fieldId);
        $status = $this->_statusChip($score);

        if ($status !== null) {
            [$tier, $inner] = $status;

            return Html::tag('span', $inner, array_merge($attrs, [
                'class' => ['core-seo-geo-score-chip', "core-seo-geo-score-chip--{$tier}"],
            ]));
        }

        [$tier, $inner] = $this->_chipTierAndInner($score);

        return Html::tag('span', implode('', [
            $inner,
            Html::tag('span', $this->renderChecklist($score), [
                'class' => 'core-seo-geo-info-icon-popup core-seo-geo-score-chip-popup',
                'popover' => 'manual',
            ]),
        ]), array_merge($attrs, [
            'class' => ['core-seo-geo-score-chip', 'core-seo-geo-info-icon-wrap', "core-seo-geo-score-chip--{$tier}"],
            'tabindex' => '0',
        ]));
    }

    /**
     * Renders the same chip as `renderChip()`, minus the hover popover and the `id`/`data-*`
     * attributes -- used in the entry edit page's SEO/GEO sidebar pane
     * ({@see \digitalastronaut\craftcoreseogeo\PluginTrait::_seoScoreSidebarHtml()}), which shows
     * the checklist via its own "ellipsis" disclosure instead, and already polls its own single
     * field directly (`data-star-on-interval` on the pane itself) rather than needing to be found by
     * the element index's batch poll.
     *
     * @param SeoScoreModel|null $score
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    public function renderChipPill(?SeoScoreModel $score): string {
        $status = $this->_statusChip($score);

        if ($status !== null) {
            [$tier, $inner] = $status;

            return Html::tag('span', $inner, [
                'class' => ['core-seo-geo-score-chip', "core-seo-geo-score-chip--{$tier}"],
            ]);
        }

        [$tier, $inner] = $this->_chipTierAndInner($score);

        return Html::tag('span', $inner, [
            'class' => ['core-seo-geo-score-chip', "core-seo-geo-score-chip--{$tier}"],
        ]);
    }

    /**
     * The DOM `id` `renderChip()` gives an element index chip, and the same id
     * `SeoScoreController::actionBatchStatus()` keys its response by -- shared so the two always
     * agree on how to address one chip, without either hardcoding the other's format.
     *
     * @param int $elementId
     * @param int $siteId
     * @param int $fieldId
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.29.0
     */
    public static function chipId(int $elementId, int $siteId, int $fieldId): string {
        return "core-seo-geo-score-chip-{$elementId}-{$siteId}-{$fieldId}";
    }

    /**
     * Re-renders one element index chip by its `elementId`/`siteId`/`fieldId` -- the single-chip
     * building block `SeoScoreController::actionBatchStatus()` calls once per ref in its batch,
     * keying each result by `chipId()` so the polling JS can swap it in by `id`.
     *
     * @param int $elementId
     * @param int $siteId
     * @param int $fieldId
     * @return string|null null if there's no such element, or no such `SeoField` on it
     *
     * @author      Digitalastronaut
     * @since       v1.29.0
     */
    public function batchStatusChipHtml(int $elementId, int $siteId, int $fieldId): ?string {
        $field = Craft::$app->getFields()->getFieldById($fieldId);
        if (!$field instanceof SeoField) return null;

        $element = Craft::$app->getElements()->getElementById($elementId, null, $siteId, ['status' => null]);
        if ($element === null) return null;

        return $this->renderChip($this->getScore($element, $fieldId), $elementId, $siteId, $fieldId);
    }

    /**
     * @param int $elementId
     * @param int $siteId
     * @param int $fieldId
     * @return array{id: string, data: array{element-id: int, site-id: int, field-id: int}}
     *
     * @author      Digitalastronaut
     * @since       v1.29.0
     */
    private function _chipAttrs(int $elementId, int $siteId, int $fieldId): array {
        return [
            'id' => self::chipId($elementId, $siteId, $fieldId),
            'data' => [
                'element-id' => $elementId,
                'site-id' => $siteId,
                'field-id' => $fieldId,
            ],
        ];
    }

    /**
     * The full checklist markup shown in `renderChip()`'s hover popup (the element index) -- see
     * `renderChecklistFields()` for the same checklist, shaped for the sidebar pane's "ellipsis"
     * disclosure instead. Every check, failures first so the things that need attention don't
     * get buried below a wall of passes, non-applicable checks (excluded from the score itself)
     * sunk to the bottom. Empty string for a score that isn't calculated yet.
     *
     * @param SeoScoreModel|null $score
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    public function renderChecklist(?SeoScoreModel $score): string {
        $sortedChecks = $this->_sortedChecks($score);
        if ($sortedChecks === null) return '';

        return Html::tag('ul', implode('', array_map(
            function(array $check): string {
                return Html::tag('li', $this->_checkItemHtml($check), [
                    'class' => ['core-seo-geo-score-check', $this->_checkState($check)],
                ]);
            },
            $sortedChecks,
        )), ['class' => 'core-seo-geo-score-checklist']);
    }

    /**
     * The same checklist as `renderChecklist()`, one `<div>` per check instead of a `<ul>` --
     * used by the sidebar pane's "ellipsis" disclosure, which lives inside the same `.meta` as
     * the "Score" field rather than a popup. The rows are wrapped in a `grid-template-rows`
     * animator (toggled by `$showSignal`, a Datastar signal expression e.g.
     * `"$scoreChecklistExpanded5"`, via `data-star-class:is-expanded`) rather than shown/hidden
     * outright, so the disclosure grows/shrinks smoothly -- the same effect the redirect edit
     * page's own "Sites" disclosure gets from jQuery + Velocity height animation (see the
     * `{% js %}` block in `redirects/_edit.twig`), just done in CSS since this pane's rows are
     * re-rendered wholesale by Datastar patches rather than toggled by a persistent jQuery
     * listener.
     *
     * @param SeoScoreModel|null $score
     * @param string $showSignal
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    public function renderChecklistFields(?SeoScoreModel $score, string $showSignal): string {
        $sortedChecks = $this->_sortedChecks($score);
        if ($sortedChecks === null) return '';

        $rows = implode('', array_map(
            function(array $check): string {
                return Html::tag('div', $this->_checkItemHtml($check), [
                    'class' => ['core-seo-geo-score-check-row', $this->_checkState($check)],
                ]);
            },
            $sortedChecks,
        ));

        return Html::tag('div', Html::tag('div', $rows, ['class' => 'core-seo-geo-score-checklist-rows-inner']), [
            'class' => ['core-seo-geo-score-checklist-rows'],
            'data-star-class:is-expanded' => $showSignal,
        ]);
    }

    /**
     * The icon + label/message content shared by `renderChecklist()`'s `<li>` rows and
     * `renderChecklistFields()`'s `<div>` rows -- the two differ only in the element wrapping
     * this and the classes that go on it. The label and (for a warning or failure) message sit
     * together in their own `.core-seo-geo-score-check-text` block, so the icon sits beside both
     * lines as a unit rather than lining up with the label alone.
     *
     * @param array{key: string, label: string, weight: int, status: string, applicable: bool, message: string} $check
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    private function _checkItemHtml(array $check): string {
        $state = $this->_checkState($check);
        $showMessage = $check['applicable'] && $check['status'] !== SeoScoreCheckStatus::Passed->value;

        return implode('', [
            Html::tag('span', '', [
                'class' => ['core-seo-geo-score-check-icon', $state],
                'aria-hidden' => 'true',
            ]),
            Html::tag('span', implode('', [
                Html::tag('span', Html::encode($check['label']), ['class' => 'core-seo-geo-score-check-label']),
                $showMessage
                    ? Html::tag('p', Html::encode($check['message']), ['class' => 'core-seo-geo-score-check-message'])
                    : '',
            ]), ['class' => 'core-seo-geo-score-check-text']),
        ]);
    }

    /**
     * @param array{applicable: bool, status: string, ...} $check
     * @return string `is-valid`, `is-warning`, `is-invalid`, or `is-na`
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    private function _checkState(array $check): string {
        if (!$check['applicable']) return 'is-na';

        return match (SeoScoreCheckStatus::from($check['status'])) {
            SeoScoreCheckStatus::Passed => 'is-valid',
            SeoScoreCheckStatus::Warning => 'is-warning',
            SeoScoreCheckStatus::Failed => 'is-invalid',
        };
    }

    /**
     * `$score->checks` (`null` for a score that isn't calculated/finished yet), sorted failures
     * first, then warnings, so the things that need attention don't get buried below a wall of
     * passes -- non-applicable checks (excluded from the score itself) sink to the bottom.
     * Shared by `renderChecklist()` and `renderChecklistFields()`.
     *
     * @param SeoScoreModel|null $score
     * @return array<int, array{key: string, label: string, weight: int, status: string, applicable: bool, message: string}>|null
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    private function _sortedChecks(?SeoScoreModel $score): ?array {
        if ($score === null || $score->pending) return null;

        $rank = static function(array $check): int {
            if (!$check['applicable']) return 3;
            return match (SeoScoreCheckStatus::from($check['status'])) {
                SeoScoreCheckStatus::Failed => 0,
                SeoScoreCheckStatus::Warning => 1,
                SeoScoreCheckStatus::Passed => 2,
            };
        };
        $sortedChecks = $score->checks;
        usort($sortedChecks, static fn(array $a, array $b): int => $rank($a) <=> $rank($b));

        return $sortedChecks;
    }

    /**
     * The "Not calculated"/"Calculating..." chip's tier + inner content, shared by `renderChip()`
     * and `renderChipPill()` -- each wraps this in its own outer `<span>` (see those methods),
     * since only `renderChip()`'s needs the element index's `id`/`data-*` attributes. `null` when
     * `$score` is a completed result either of them needs to go on and render the full
     * percent/count chip for instead ({@see _chipTierAndInner()}). The "Calculating..." chip
     * echoes that completed chip's own percent/count layout rather than a flat label, just with
     * "Calculating..." standing in for the percent and the CP's own `.spinner` standing in for
     * the pass count -- applicability isn't known until the check run actually finishes, so
     * there's no meaningful `x/y` to show yet.
     *
     * @param SeoScoreModel|null $score
     * @return array{0: string, 1: string}|null tier, then inner content
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    private function _statusChip(?SeoScoreModel $score): ?array {
        if ($score === null) {
            return ['none', Craft::t('core-seo-geo', 'Not calculated')];
        }

        if ($score->pending) {
            // The CP's own `.spinner.small` (a fixed 12x12 circle) sits *inside* its own pill
            // span rather than carrying `core-seo-geo-score-chip-spinner` itself -- that class
            // sets an explicit height/width, which would've overridden the pill's stretch-to-
            // the-chip's-full-height behavior (`-count`'s own equivalent has no explicit size, so
            // it stretches) and left a 12px-tall nub instead of a full-height capsule.
            $spinner = Html::tag('span', '', ['class' => ['spinner', 'small']]);

            $inner = implode('', [
                Html::tag('span', Craft::t('core-seo-geo', 'Calculating...'), ['class' => 'core-seo-geo-score-chip-percent']),
                Html::tag('span', $spinner, ['class' => 'core-seo-geo-score-chip-spinner']),
            ]);

            return ['pending', $inner];
        }

        return null;
    }

    /**
     * The tier (`good`/`warning`/`bad`, driving the chip's color) and inner percent/count
     * markup shared by `renderChip()` and `renderChipPill()` -- everything the two need except
     * the outer tag, which differs (the hover-popup wrap vs. a plain span).
     *
     * @param SeoScoreModel $score a completed (non-null, non-pending) score
     * @return array{0: string, 1: string}
     *
     * @author      Digitalastronaut
     * @since       v1.28.0
     */
    private function _chipTierAndInner(SeoScoreModel $score): array {
        $applicable = array_values(array_filter($score->checks, static fn(array $check): bool => $check['applicable']));
        $passed = array_values(array_filter($applicable, static fn(array $check): bool => $check['status'] !== SeoScoreCheckStatus::Failed->value));
        $total = count($applicable);

        $tier = match (true) {
            $score->score >= 80 => 'good',
            $score->score >= 50 => 'warning',
            default => 'bad',
        };

        $inner = implode('', [
            Html::tag('span', "{$score->score}%", ['class' => 'core-seo-geo-score-chip-percent']),
            Html::tag('span', count($passed) . "/{$total}", ['class' => 'core-seo-geo-score-chip-count']),
        ]);

        return [$tier, $inner];
    }

    // Private Methods
    // =========================================================================

    /**
     * @param ElementInterface $element
     * @param int $fieldId
     * @return SeoScoreRecord
     *
     * @author      Digitalastronaut
     * @since       v1.0.4
     */
    private function _findOrCreateRecord(ElementInterface $element, int $fieldId): SeoScoreRecord {
        /** @var Element $element */
        $siteId = $element->getSite()->id;

        $record = SeoScoreRecord::findOne([
            'elementId' => $element->id,
            'siteId' => $siteId,
            'fieldId' => $fieldId,
        ]);

        if ($record !== null) {
            return $record;
        }

        $record = new SeoScoreRecord();
        $record->elementId = $element->id;
        $record->siteId = $siteId;
        $record->fieldId = $fieldId;

        return $record;
    }
}
