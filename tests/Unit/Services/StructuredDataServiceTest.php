<?php

use digitalastronaut\craftcoreseogeo\services\StructuredDataService;

/**
 * renderProperties() needs a booted app (`Craft::$app->getView()->renderObjectTemplate()`)
 * and belongs in the integration suite. Covered here: the pure public toJsonLd(), plus the
 * two private pure decoding helpers it and renderProperties() both rely on, reached via
 * reflection the same way MarkdownServiceTest reaches MarkdownService's private helpers.
 */
function invokeStructuredDataServiceMethod(string $method, array $args): mixed {
    return (new ReflectionMethod(StructuredDataService::class, $method))->invoke(new StructuredDataService(), ...$args);
}

function decodeRenderedProperty(string $rendered): mixed {
    return invokeStructuredDataServiceMethod('_decodeRenderedProperty', [$rendered]);
}

function unwrapJsonLdScript(string $rendered): string {
    return invokeStructuredDataServiceMethod('_unwrapJsonLdScript', [$rendered]);
}

// =========================================================================
// toJsonLd()
// =========================================================================

it('assembles the context and type ahead of the rendered property data', function () {
    $result = (new StructuredDataService())->toJsonLd('WebPage', ['name' => 'Home']);

    expect($result)->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => 'Home',
    ]);
});

it('builds a bare context/type object when there is no property data', function () {
    expect((new StructuredDataService())->toJsonLd('WebPage', []))->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
    ]);
});

it('lets rendered data override @context or @type if present', function () {
    $result = (new StructuredDataService())->toJsonLd('WebPage', ['@type' => 'Article']);

    expect($result['@type'])->toBe('Article');
});

// =========================================================================
// _unwrapJsonLdScript()
// =========================================================================

it('unwraps the inner json from a full ld+json script tag', function () {
    $rendered = '<script type="application/ld+json">{"name":"Home"}</script>';

    expect(unwrapJsonLdScript($rendered))->toBe('{"name":"Home"}');
});

it('leaves plain text unchanged when it is not a script tag', function () {
    expect(unwrapJsonLdScript('Just a plain string'))->toBe('Just a plain string');
});

it('leaves a non-ld+json script-like fragment unchanged if it is malformed', function () {
    $rendered = '<script type="application/ld+json">{"name":"Home"}';

    expect(unwrapJsonLdScript($rendered))->toBe($rendered);
});

// =========================================================================
// _decodeRenderedProperty()
// =========================================================================

it('keeps a plain string result as-is', function () {
    expect(decodeRenderedProperty('Just a name'))->toBe('Just a name');
});

it('decodes a hand-written json object literal', function () {
    expect(decodeRenderedProperty('{"name":"Home","url":"https://example.com"}'))
        ->toBe(['name' => 'Home', 'url' => 'https://example.com']);
});

it('decodes a ld+json script tag and strips its own @context', function () {
    $rendered = '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Person","name":"Jane"}</script>';

    expect(decodeRenderedProperty($rendered))->toBe(['@type' => 'Person', 'name' => 'Jane']);
});

it('keeps a json array literal as a plain array', function () {
    expect(decodeRenderedProperty('["a","b"]'))->toBe(['a', 'b']);
});
