<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\HasMainCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

it('is not applicable when the page could not be fetched', function () {
    $context = seoScoreContext(new SeoData(), null);

    $result = (new HasMainCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('fails when the rendered page has no main element', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><div>Hello</div></body></html>'));

    $result = (new HasMainCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('warns when the rendered page has more than one main element', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><main>A</main><main>B</main></body></html>'));

    $result = (new HasMainCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Warning);
});

it('passes when the rendered page has exactly one main element', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><main>Content</main></body></html>'));

    $result = (new HasMainCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
