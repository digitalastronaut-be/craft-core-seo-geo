<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\MainHasLinkCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

it('is not applicable when the page could not be fetched', function () {
    $context = seoScoreContext(new SeoData(), null);

    $result = (new MainHasLinkCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('is not applicable when there is no main element', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><p>No main here</p></body></html>'));

    $result = (new MainHasLinkCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('fails when main has no links', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><main><p>Dead end</p></main></body></html>'));

    $result = (new MainHasLinkCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('passes when main has at least one link', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><main><a href="/next">Next</a></main></body></html>'));

    $result = (new MainHasLinkCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
