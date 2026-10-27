<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;
use digitalastronaut\craftcoreseogeo\seochecks\ValidTitleCheck;

it('fails when no meta title is set', function () {
    $seoData = new SeoData();
    $seoData->metaTitle = null;

    $result = (new ValidTitleCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
    expect($result->applicable)->toBeTrue();
});

it('fails when the meta title is only whitespace', function () {
    $seoData = new SeoData();
    $seoData->metaTitle = '   ';

    $result = (new ValidTitleCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('warns when the meta title is too short', function () {
    $seoData = new SeoData();
    $seoData->metaTitle = 'Short';

    $result = (new ValidTitleCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Warning);
});

it('warns when the meta title is too long', function () {
    $seoData = new SeoData();
    $seoData->metaTitle = str_repeat('a', 61);

    $result = (new ValidTitleCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Warning);
});

it('passes for a title within the recommended length', function () {
    $seoData = new SeoData();
    $seoData->metaTitle = 'A perfectly reasonable page title';

    $result = (new ValidTitleCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
