<?php

use digitalastronaut\craftcoreseogeo\services\JsonLdGraphService;

/**
 * add()/has()/reference()/idFor() are pure graph-bookkeeping and covered here. render() --
 * and addElement(), which only ever feeds it -- always calls _addGlobalObjects() first, which
 * needs Craft::$app->getSites() and a booted plugin instance (CoreSeoGeo::getInstance()), so
 * they belong in the integration suite instead.
 */
function graphObjects(JsonLdGraphService $service): array {
    $ref = new ReflectionProperty($service, 'objects');

    return $ref->getValue($service);
}

it('stores an object under its id, prefixed with @id', function () {
    $service = new JsonLdGraphService();

    $id = $service->add(['@type' => 'Thing', 'name' => 'Foo'], 'thing1');

    expect($id)->toBe('thing1');
    expect(graphObjects($service))->toBe([
        'thing1' => ['@id' => 'thing1', '@type' => 'Thing', 'name' => 'Foo'],
    ]);
});

it('strips any @context/@id already on the object before storing it', function () {
    $service = new JsonLdGraphService();

    $service->add(['@context' => 'https://schema.org', '@id' => 'should-be-dropped', '@type' => 'Thing'], 'thing1');

    expect(graphObjects($service))->toBe([
        'thing1' => ['@id' => 'thing1', '@type' => 'Thing'],
    ]);
});

it('reports whether an id has already been added', function () {
    $service = new JsonLdGraphService();
    $service->add(['@type' => 'Thing'], 'thing1');

    expect($service->has('thing1'))->toBeTrue();
    expect($service->has('thing2'))->toBeFalse();
});

it('builds an @id reference pointer', function () {
    expect((new JsonLdGraphService())->reference('thing1'))->toBe(['@id' => 'thing1']);
});

it('builds a lowercase type-suffixed id from a url', function () {
    $service = new JsonLdGraphService();

    expect($service->idFor('WebPage', 'https://example.com/page'))->toBe('https://example.com/page#webpage');
});

it('trims a trailing slash from the url before building the id', function () {
    $service = new JsonLdGraphService();

    expect($service->idFor('WebPage', 'https://example.com/page/'))->toBe('https://example.com/page#webpage');
});

it('appends a disambiguator when given one', function () {
    $service = new JsonLdGraphService();

    expect($service->idFor('Review', 'https://example.com/page', 'abc123'))
        ->toBe('https://example.com/page#review-abc123');
});
