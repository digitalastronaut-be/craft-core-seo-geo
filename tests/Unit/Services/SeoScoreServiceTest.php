<?php

use digitalastronaut\craftcoreseogeo\models\SeoScore as SeoScoreModel;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;
use digitalastronaut\craftcoreseogeo\services\SeoScoreService;

/**
 * Only the pure rendering/sorting side of SeoScoreService is covered here --
 * process()/getScore()/markPending()/batchStatusChipHtml() all need a booted plugin
 * instance and a database (CoreSeoGeo::getInstance(), SeoScoreRecord, Craft::$app->getElements()/
 * getFields()) and belong in the integration suite instead.
 */
function checkRow(string $key, SeoScoreCheckStatus $status, bool $applicable = true, string $message = ''): array {
    return [
        'key' => $key,
        'label' => ucfirst(str_replace('-', ' ', $key)),
        'weight' => 10,
        'status' => $status->value,
        'applicable' => $applicable,
        'message' => $message,
    ];
}

function completedScore(array $checks, int $score): SeoScoreModel {
    return new SeoScoreModel(['score' => $score, 'pending' => false, 'checks' => $checks]);
}

// =========================================================================
// chipId()
// =========================================================================

it('builds a chip id from element, site, and field ids', function () {
    expect(SeoScoreService::chipId(12, 3, 7))->toBe('core-seo-geo-score-chip-12-3-7');
});

// =========================================================================
// renderChip() / renderChipPill() -- not-calculated and pending states
// =========================================================================

it('renders a "not calculated" chip when there is no score yet', function () {
    $html = (new SeoScoreService())->renderChip(null, 1, 2, 3);

    expect($html)
        ->toContain('core-seo-geo-score-chip--none')
        ->toContain('Not calculated')
        ->toContain('core-seo-geo-score-chip-1-2-3');
});

it('renders a pending chip while the score is still calculating', function () {
    $score = new SeoScoreModel(['pending' => true]);

    $html = (new SeoScoreService())->renderChip($score, 1, 2, 3);

    expect($html)
        ->toContain('core-seo-geo-score-chip--pending')
        ->toContain('Calculating...');
});

it('renders the pill variant without element/site/field data attributes', function () {
    $html = (new SeoScoreService())->renderChipPill(null);

    expect($html)
        ->toContain('Not calculated')
        ->not->toContain('data-element-id');
});

// =========================================================================
// renderChip() / renderChipPill() -- completed score tiers
// =========================================================================

it('tiers a completed score as good at 80% and above', function () {
    $score = completedScore([checkRow('valid-title', SeoScoreCheckStatus::Passed)], 80);

    expect((new SeoScoreService())->renderChipPill($score))->toContain('core-seo-geo-score-chip--good');
});

it('tiers a completed score as warning between 50% and 79%', function () {
    $score = completedScore([checkRow('valid-title', SeoScoreCheckStatus::Passed)], 50);

    expect((new SeoScoreService())->renderChipPill($score))->toContain('core-seo-geo-score-chip--warning');
});

it('tiers a completed score as bad below 50%', function () {
    $score = completedScore([checkRow('valid-title', SeoScoreCheckStatus::Passed)], 49);

    expect((new SeoScoreService())->renderChipPill($score))->toContain('core-seo-geo-score-chip--bad');
});

it('shows the passed-over-applicable count, excluding non-applicable checks', function () {
    $score = completedScore([
        checkRow('valid-title', SeoScoreCheckStatus::Passed),
        checkRow('valid-description', SeoScoreCheckStatus::Failed),
        checkRow('canonical-url', SeoScoreCheckStatus::Passed, applicable: false),
    ], 50);

    $html = (new SeoScoreService())->renderChipPill($score);

    expect($html)->toContain('50%')->toContain('1/2');
});

// =========================================================================
// renderChecklist() / renderChecklistFields()
// =========================================================================

it('renders no checklist for a null or pending score', function () {
    $service = new SeoScoreService();

    expect($service->renderChecklist(null))->toBe('');
    expect($service->renderChecklist(new SeoScoreModel(['pending' => true])))->toBe('');
    expect($service->renderChecklistFields(null, '$x'))->toBe('');
});

it('orders the checklist failures, then warnings, then passes, then non-applicable', function () {
    $score = completedScore([
        checkRow('valid-title', SeoScoreCheckStatus::Passed),
        checkRow('canonical-url', SeoScoreCheckStatus::Passed, applicable: false),
        checkRow('valid-description', SeoScoreCheckStatus::Failed),
        checkRow('seo-image', SeoScoreCheckStatus::Warning),
    ], 50);

    $html = (new SeoScoreService())->renderChecklist($score);

    $failedPos = strpos($html, 'Valid description');
    $warningPos = strpos($html, 'Seo image');
    $passedPos = strpos($html, 'Valid title');
    $naPos = strpos($html, 'Canonical url');

    expect($failedPos)->toBeLessThan($warningPos);
    expect($warningPos)->toBeLessThan($passedPos);
    expect($passedPos)->toBeLessThan($naPos);
});

it('marks a non-applicable check as is-na regardless of its status', function () {
    $score = completedScore([checkRow('canonical-url', SeoScoreCheckStatus::Passed, applicable: false)], 100);

    expect((new SeoScoreService())->renderChecklist($score))->toContain('is-na');
});

it('shows the message for a failed or warning check, but not for a passed one', function () {
    $score = completedScore([
        checkRow('valid-title', SeoScoreCheckStatus::Failed, message: 'No meta title is set.'),
        checkRow('valid-description', SeoScoreCheckStatus::Passed, message: 'should not appear'),
    ], 50);

    $html = (new SeoScoreService())->renderChecklist($score);

    expect($html)
        ->toContain('No meta title is set.')
        ->not->toContain('should not appear');
});

it('toggles the checklist-fields expanded state via the given signal', function () {
    $score = completedScore([checkRow('valid-title', SeoScoreCheckStatus::Passed)], 100);

    $html = (new SeoScoreService())->renderChecklistFields($score, '$scoreChecklistExpanded5');

    expect($html)->toContain('data-star-class:is-expanded="$scoreChecklistExpanded5"');
});
