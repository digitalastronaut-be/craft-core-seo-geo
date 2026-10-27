<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\HeadingStructureCheck;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;

it('is not applicable when the page could not be fetched', function () {
    $context = seoScoreContext(new SeoData(), null);

    $result = (new HeadingStructureCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('is not applicable when there are no headings at all', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><p>No headings here</p></body></html>'));

    $result = (new HeadingStructureCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('fails when there is more than one h1', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><h1>One</h1><h1>Two</h1></body></html>'));

    $result = (new HeadingStructureCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('fails when a heading level is skipped', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><h1>Title</h1><h2>Section</h2><h4>Skipped to h4</h4></body></html>'));

    $result = (new HeadingStructureCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('passes when heading levels decrease without skipping', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><h1>Title</h1><h2>Section</h2><h3>Sub</h3><h2>Next section</h2></body></html>'));

    $result = (new HeadingStructureCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});

it('passes for a single, well-structured heading hierarchy', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment('<html><body><h1>Title</h1><h2>Section</h2><h3>Sub</h3></body></html>'));

    $result = (new HeadingStructureCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
