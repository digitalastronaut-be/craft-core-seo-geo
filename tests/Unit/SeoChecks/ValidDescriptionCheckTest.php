<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;
use digitalastronaut\craftcoreseogeo\seochecks\ValidDescriptionCheck;

it('fails when no meta description is set', function () {
    $seoData = new SeoData();
    $seoData->metaDescription = null;

    $result = (new ValidDescriptionCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('fails when the meta description is only whitespace', function () {
    $seoData = new SeoData();
    $seoData->metaDescription = '   ';

    $result = (new ValidDescriptionCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('warns when the meta description is too short', function () {
    $seoData = new SeoData();
    $seoData->metaDescription = 'Too short';

    $result = (new ValidDescriptionCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Warning);
});

it('warns when the meta description is too long', function () {
    $seoData = new SeoData();
    $seoData->metaDescription = str_repeat('a', 161);

    $result = (new ValidDescriptionCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Warning);
});

it('passes for a description within the recommended length', function () {
    $seoData = new SeoData();
    $seoData->metaDescription = str_repeat('a', 100);

    $result = (new ValidDescriptionCheck())->validate(seoScoreContext($seoData));

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
