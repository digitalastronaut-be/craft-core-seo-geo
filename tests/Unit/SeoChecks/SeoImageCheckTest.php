<?php

use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\SeoImageCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

/**
 * SeoData's own image is mocked via getImage() so these never touch the database --
 * `??` short-circuits before SeoImageCheck ever reaches ContentSeoService's fallback
 * lookup (which needs a booted plugin instance). The "no image at all, and no section
 * fallback either" Failed branch therefore belongs in the integration suite.
 */
function seoDataWithImage(?Asset $asset): SeoData {
    $query = test()->createStub(AssetQuery::class);
    $query->method('one')->willReturn($asset);

    $seoData = test()->getMockBuilder(SeoData::class)->onlyMethods(['getImage'])->getMock();
    $seoData->method('getImage')->willReturn($query);

    return $seoData;
}

it('warns when the seo image has no alt text', function () {
    $asset = test()->createStub(Asset::class);
    $asset->method('getWidth')->willReturn(1600);
    $asset->method('getHeight')->willReturn(900);
    $asset->alt = null;

    $result = (new SeoImageCheck())->validate(seoScoreContext(seoDataWithImage($asset)));

    expect($result->status)->toBe(SeoScoreCheckStatus::Warning);
});

it('warns when the seo image is smaller than recommended', function () {
    $asset = test()->createStub(Asset::class);
    $asset->method('getWidth')->willReturn(600);
    $asset->method('getHeight')->willReturn(315);
    $asset->alt = 'A description';

    $result = (new SeoImageCheck())->validate(seoScoreContext(seoDataWithImage($asset)));

    expect($result->status)->toBe(SeoScoreCheckStatus::Warning);
});

it('passes when the seo image has alt text and a good size', function () {
    $asset = test()->createStub(Asset::class);
    $asset->method('getWidth')->willReturn(1600);
    $asset->method('getHeight')->willReturn(900);
    $asset->alt = 'A description';

    $result = (new SeoImageCheck())->validate(seoScoreContext(seoDataWithImage($asset)));

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
