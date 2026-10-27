<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\MainWordCountCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

it('is not applicable when the page could not be fetched', function () {
    $context = seoScoreContext(new SeoData(), null);

    $result = (new MainWordCountCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('is not applicable when there is no main element', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><p>No main here</p></body></html>'));

    $result = (new MainWordCountCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('fails when main has fewer than 300 words', function () {
    $html = '<html><body><main>' . str_repeat('word ', 50) . '</main></body></html>';
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment($html));

    $result = (new MainWordCountCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('passes when main has at least 300 words', function () {
    $html = '<html><body><main>' . str_repeat('word ', 300) . '</main></body></html>';
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment($html));

    $result = (new MainWordCountCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
