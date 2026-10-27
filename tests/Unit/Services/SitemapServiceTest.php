<?php

use digitalastronaut\craftcoreseogeo\services\SitemapService;

/**
 * Most of this service resolves sections/categories/routes via Craft::$app, or runs element
 * queries, and belongs in the integration suite. Covered here: the pure XML-building helpers
 * (_wrapXml(), _urlXml()), the pure route-pattern check (_isDynamicRoutePattern()), all reached
 * via reflection, and resolveGroupKey() -- pure given its groups list, but reached only through
 * getGroups(), which needs a booted app. A partial mock stubs just that one method so
 * resolveGroupKey()'s own (DB-free) parsing logic can be exercised directly.
 */
function invokeSitemapServiceMethod(string $method, array $args): mixed {
    return (new ReflectionMethod(SitemapService::class, $method))->invoke(new SitemapService(), ...$args);
}

function wrapXml(string $rootTag, string $innerXml): string {
    return invokeSitemapServiceMethod('_wrapXml', [$rootTag, $innerXml]);
}

function urlXml(array $url): string {
    return invokeSitemapServiceMethod('_urlXml', [$url]);
}

function resolveGroupKeyWithGroups(array $groups, string $groupKey): array {
    $service = test()->getMockBuilder(SitemapService::class)->onlyMethods(['getGroups'])->getMock();
    $service->method('getGroups')->willReturn($groups);

    return (new ReflectionMethod(SitemapService::class, 'resolveGroupKey'))->invoke($service, 1, $groupKey);
}

// =========================================================================
// _wrapXml()
// =========================================================================

it('wraps a sitemapindex without the image/xhtml namespaces', function () {
    $xml = wrapXml('sitemapindex', '<sitemap></sitemap>');

    expect($xml)
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="https://www.sitemaps.org/schemas/sitemap/0.9">')
        ->toContain('<sitemap></sitemap>')
        ->toEndWith('</sitemapindex>')
        ->not->toContain('xmlns:image')
        ->not->toContain('xmlns:xhtml');
});

it('wraps a urlset with the image and xhtml namespaces', function () {
    $xml = wrapXml('urlset', '<url></url>');

    expect($xml)
        ->toContain('xmlns:image="https://www.google.com/schemas/sitemap-image/1.1"')
        ->toContain('xmlns:xhtml="https://www.w3.org/1999/xhtml"')
        ->toContain('<url></url>');
});

// =========================================================================
// _urlXml()
// =========================================================================

it('renders a bare url entry with no lastmod, images, or alternates', function () {
    $xml = urlXml(['loc' => 'https://example.com/', 'lastmod' => null]);

    expect($xml)->toBe('<url><loc>https://example.com/</loc></url>');
});

it('encodes special characters in the loc', function () {
    $xml = urlXml(['loc' => 'https://example.com/?a=1&b=2', 'lastmod' => null]);

    expect($xml)->toContain('https://example.com/?a=1&amp;b=2');
});

it('renders lastmod in ATOM format when present', function () {
    $date = new DateTime('2026-01-15T10:00:00+00:00');

    $xml = urlXml(['loc' => 'https://example.com/', 'lastmod' => $date]);

    expect($xml)->toContain('<lastmod>' . $date->format(DateTime::ATOM) . '</lastmod>');
});

it('renders one xhtml alternate link per entry', function () {
    $xml = urlXml([
        'loc' => 'https://example.com/',
        'lastmod' => null,
        'alternates' => [['language' => 'fr', 'url' => 'https://example.com/fr/']],
    ]);

    expect($xml)->toContain('<xhtml:link rel="alternate" hreflang="fr" href="https://example.com/fr/"/>');
});

it('renders one image entry per image url', function () {
    $xml = urlXml([
        'loc' => 'https://example.com/',
        'lastmod' => null,
        'images' => ['https://example.com/a.jpg', 'https://example.com/b.jpg'],
    ]);

    expect($xml)
        ->toContain('<image:image><image:loc>https://example.com/a.jpg</image:loc></image:image>')
        ->toContain('<image:image><image:loc>https://example.com/b.jpg</image:loc></image:image>');
});

// =========================================================================
// _isDynamicRoutePattern()
// =========================================================================

it('treats a pattern with a route param as dynamic', function () {
    expect(invokeSitemapServiceMethod('_isDynamicRoutePattern', ['blog/<slug>']))->toBeTrue();
});

it('treats a plain static pattern as not dynamic', function () {
    expect(invokeSitemapServiceMethod('_isDynamicRoutePattern', ['about-us']))->toBeFalse();
});

// =========================================================================
// resolveGroupKey()
// =========================================================================

it('resolves an exact group handle to page 1', function () {
    expect(resolveGroupKeyWithGroups(['blog' => 'Blog'], 'blog'))->toBe(['blog', 1]);
});

it('resolves a paginated handle to its base handle and page number', function () {
    expect(resolveGroupKeyWithGroups(['blog' => 'Blog'], 'blog-3'))->toBe(['blog', 3]);
});

it('returns null for a handle that does not match any group', function () {
    expect(resolveGroupKeyWithGroups(['blog' => 'Blog'], 'not-a-group'))->toBe([null, 1]);
});

it('returns null for a paginated handle whose base is not a known group', function () {
    expect(resolveGroupKeyWithGroups(['blog' => 'Blog'], 'not-a-group-2'))->toBe([null, 1]);
});

it('prefers the exact handle match when a group handle itself ends in digits', function () {
    expect(resolveGroupKeyWithGroups(['news-2' => 'News 2'], 'news-2'))->toBe(['news-2', 1]);
});
