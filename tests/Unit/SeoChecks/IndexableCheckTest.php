<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\IndexableCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

it('is not applicable when robots is set to noindex', function () {
    $seoData = new SeoData();
    $seoData->robots = [SeoData::ROBOTS_NOINDEX];

    $result = (new IndexableCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
    expect($result->applicable)->toBeFalse();
});

it('passes and is applicable when robots does not include noindex', function () {
    $seoData = new SeoData();
    $seoData->robots = [SeoData::ROBOTS_NOFOLLOW];

    $result = (new IndexableCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
    expect($result->applicable)->toBeTrue();
});

it('passes and is applicable when no robots directives are set', function () {
    $seoData = new SeoData();

    $result = (new IndexableCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
    expect($result->applicable)->toBeTrue();
});
