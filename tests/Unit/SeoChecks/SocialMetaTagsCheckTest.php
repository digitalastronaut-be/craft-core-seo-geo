<?php

use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckStatus;
use digitalastronaut\craftcoreseogeo\seochecks\SocialMetaTagsCheck;

const COMPLETE_SOCIAL_HEAD = <<<'HTML'
<html><head>
    <meta property="og:title" content="A title">
    <meta property="og:description" content="A description">
    <meta property="og:image" content="https://example.com/image.jpg">
    <meta name="twitter:card" content="summary_large_image">
</head><body></body></html>
HTML;

it('is not applicable when the page could not be fetched', function () {
    $context = seoScoreContext(new SeoData(), null);

    $result = (new SocialMetaTagsCheck())->validate($context);

    expect($result->applicable)->toBeFalse();
});

it('fails when a required tag is missing entirely', function () {
    $html = '<html><head><meta property="og:title" content="A title"></head><body></body></html>';
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment($html));

    $result = (new SocialMetaTagsCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('fails when a required tag resolves to an empty value', function () {
    $html = '<html><head>
        <meta property="og:title" content="">
        <meta property="og:description" content="A description">
        <meta property="og:image" content="https://example.com/image.jpg">
        <meta name="twitter:card" content="summary_large_image">
    </head><body></body></html>';
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment($html));

    $result = (new SocialMetaTagsCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Failed);
});

it('passes when all required tags resolve to a value', function () {
    $context = withDocument(seoScoreContext(new SeoData(), 'https://example.com'), parseHtmlFragment(COMPLETE_SOCIAL_HEAD));

    $result = (new SocialMetaTagsCheck())->validate($context);

    expect($result->status)->toBe(SeoScoreCheckStatus::Passed);
});
