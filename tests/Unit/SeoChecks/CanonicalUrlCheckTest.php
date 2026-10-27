<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\CanonicalUrlCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

it('is not applicable when no canonical URL override is set', function () {
    $seoData = new SeoData();
    $seoData->canonicalUrl = null;

    $result = (new CanonicalUrlCheck())->validate(seoScoreContext($seoData));

    expect($result->applicable)->toBeFalse();
});

it('fails when the canonical URL is not a valid URL', function () {
    $seoData = new SeoData();
    $seoData->canonicalUrl = 'not a url';

    $result = (new CanonicalUrlCheck())->validate(seoScoreContext($seoData, 'https://example.com/page'));

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('warns when the canonical URL points elsewhere', function () {
    $seoData = new SeoData();
    $seoData->canonicalUrl = 'https://example.com/other-page';

    $result = (new CanonicalUrlCheck())->validate(seoScoreContext($seoData, 'https://example.com/page'));

    expect($result->status)->toBe(SeoScoreCheckStatus::Warning);
});

it('passes when the canonical URL matches the element URL', function () {
    $seoData = new SeoData();
    $seoData->canonicalUrl = 'https://example.com/page';

    $result = (new CanonicalUrlCheck())->validate(seoScoreContext($seoData, 'https://example.com/page'));

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});

it('ignores a trailing slash difference when comparing URLs', function () {
    $seoData = new SeoData();
    $seoData->canonicalUrl = 'https://example.com/page/';

    $result = (new CanonicalUrlCheck())->validate(seoScoreContext($seoData, 'https://example.com/page'));

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});

it('passes when the element has no URL to compare against', function () {
    $seoData = new SeoData();
    $seoData->canonicalUrl = 'https://example.com/page';

    $result = (new CanonicalUrlCheck())->validate(seoScoreContext($seoData, null));

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
