<?php

use craft\elements\Asset;
use digitalastronaut\craftcoreseogeo\services\FaviconService;

/**
 * Every method here is a thin, pure wrapper around `Asset::getUrl()` with fixed transform
 * params, so a stubbed Asset that echoes its params back is enough to assert on them directly
 * -- no real transform/image driver involved. Not covered: getFaviconIcoUrl() (branches on
 * `Craft::$app->getImages()->getIsImagick()`) and getManifest() (reads the plugin's own
 * settings and `Craft::$app->getSites()->getCurrentSite()`), both of which need a booted app
 * and belong in the integration suite.
 */
function assetEchoingUrlParams(): Asset {
    $asset = test()->createStub(Asset::class);
    $asset->method('getUrl')->willReturnCallback(fn(array $params) => json_encode($params));

    return $asset;
}

it('requests a 32x32 png for the favicon', function () {
    $url = (new FaviconService())->getFaviconPngUrl(assetEchoingUrlParams());

    expect(json_decode($url, true))->toBe(['width' => 32, 'height' => 32, 'format' => 'png']);
});

it('requests a 180x180 png for the apple touch icon', function () {
    $url = (new FaviconService())->getAppleTouchIconUrl(assetEchoingUrlParams());

    expect(json_decode($url, true))->toBe(['width' => 180, 'height' => 180, 'format' => 'png']);
});

it('requests a 192x192 png for the 192 icon', function () {
    $url = (new FaviconService())->getIcon192Url(assetEchoingUrlParams());

    expect(json_decode($url, true))->toBe(['width' => 192, 'height' => 192, 'format' => 'png']);
});

it('requests a 512x512 png for the 512 icon', function () {
    $url = (new FaviconService())->getIcon512Url(assetEchoingUrlParams());

    expect(json_decode($url, true))->toBe(['width' => 512, 'height' => 512, 'format' => 'png']);
});

it('reuses the 512 icon as the maskable icon', function () {
    $service = new FaviconService();
    $asset = assetEchoingUrlParams();

    expect($service->getIconMaskUrl($asset))->toBe($service->getIcon512Url($asset));
});
