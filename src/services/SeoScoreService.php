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
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\jobs\CalculateSeoScore;
use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\models\SeoScore as SeoScoreModel;
use digitalastronaut\craftcoreseogeo\records\SeoScore as SeoScoreRecord;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckContext;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckInterface;

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

        $totalWeight = 0;
        $earnedWeight = 0;
        $checksData = [];

        foreach ($this->getRegisteredChecks() as $check) {
            $result = $check->validate($context);
            $weight = $check->getWeight();

            $checksData[] = [
                'key' => $check->getKey(),
                'label' => $check->getLabel(),
                'weight' => $weight,
                'valid' => $result->valid,
                'applicable' => $result->applicable,
                'message' => $result->message,
            ];

            if (!$result->applicable) continue;

            $totalWeight += $weight;

            if ($result->valid) $earnedWeight += $weight;
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
