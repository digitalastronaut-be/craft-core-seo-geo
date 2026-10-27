<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\ImageAltTextCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

it('is not applicable when the page could not be fetched', function () {
    $context = seoScoreContext(new SeoData(), null);

    $result = (new ImageAltTextCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('is not applicable when main has no images', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><main><p>No images</p></main></body></html>'));

    $result = (new ImageAltTextCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('fails when an image inside main has no alt attribute', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><main><img src="a.jpg"></main></body></html>'));

    $result = (new ImageAltTextCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('fails when an image inside main has an empty alt attribute', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><main><img src="a.jpg" alt="  "></main></body></html>'));

    $result = (new ImageAltTextCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('ignores images outside of main', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><header><img src="logo.jpg"></header><main><p>Text</p></main></body></html>'));

    $result = (new ImageAltTextCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('passes when every image inside main has alt text', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><main><img src="a.jpg" alt="A description"></main></body></html>'));

    $result = (new ImageAltTextCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
