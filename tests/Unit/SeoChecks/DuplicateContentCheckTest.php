<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\DuplicateContentCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

/**
 * Only the "nothing to compare yet" branch is unit-testable: every other branch queries
 * SeoScoreRecord::find() against the database. Cross-element duplicate detection belongs
 * in the integration suite once a DB-backed harness exists.
 */
it('is not applicable when neither a title nor a description is set', function () {
    $seoData = new SeoData();

    $result = (new DuplicateContentCheck())->validate(seoScoreContext($seoData));

    expect($result->applicable)->toBeFalse();
});
