<?php

use craft\base\Element;
use craft\base\ElementInterface;
use digitalastronaut\craftcoreseogeo\services\HreflangService;

/**
 * getAlternates() does real work (resolving localized copies via `$element::find()`,
 * reading `Craft::$app->getSites()`) only once it's past its two early-return guards --
 * everything past those needs a booted app/DB and belongs in the integration suite.
 * Covered here: the guards themselves.
 *
 * A real (but un-booted) craft\base\Element subclass is used instead of a plain
 * ElementInterface stub so that `id` is a real declared property (ElementTrait) rather
 * than a dynamic one -- `init()` is overridden to a no-op since the real one touches
 * Craft::$app and attaches the customFields behavior, neither of which exist here.
 */
function elementWithId(int|string|null $id, array $supportedSites = []): ElementInterface {
    $element = new class extends Element {
        public array $supportedSitesStub = [];
        public function init(): void {}
        public function getSupportedSites(): array { return $this->supportedSitesStub; }
    };
    $element->id = $id;
    $element->supportedSitesStub = $supportedSites;

    return $element;
}

it('returns no alternates for an element with no id', function () {
    expect((new HreflangService())->getAlternates(elementWithId(null)))->toBe([]);
});

it('returns no alternates for an unsaved element with a falsy id', function () {
    expect((new HreflangService())->getAlternates(elementWithId(0)))->toBe([]);
});

it('returns no alternates when the element supports fewer than two sites', function () {
    expect((new HreflangService())->getAlternates(elementWithId(1, [1])))->toBe([]);
});

it('returns no alternates when the element supports zero sites', function () {
    expect((new HreflangService())->getAlternates(elementWithId(1, [])))->toBe([]);
});
