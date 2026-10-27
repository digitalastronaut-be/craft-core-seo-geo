<?php

use craft\elements\Address;
use digitalastronaut\craftcoreseogeo\elements\StructuredData as StructuredDataElement;
use digitalastronaut\craftcoreseogeo\services\GlobalStructuredDataService;

/**
 * Most of this service is a thin wrapper around element queries/Craft::$app (getElement(),
 * getModel(), getAddressGlobalSet(), getSavedAddress(), getAddressFieldHtml(), save()) and
 * belongs in the integration suite. Covered here: the two pure lookups (getTypeForSlug(),
 * getTypeLabel()), and _addressProperties() -- pure given an Address, but reached only through
 * getSavedAddress(), which itself needs a booted app. A partial mock stubs just that one method
 * so _addressProperties()'s own (DB-free) transform logic can be exercised directly.
 */
function addressWith(array $attributes): Address {
    $address = test()->createStub(Address::class);
    $address->countryCode = $attributes['countryCode'] ?? 'US';
    $address->addressLine1 = $attributes['addressLine1'] ?? null;
    $address->addressLine2 = $attributes['addressLine2'] ?? null;
    $address->locality = $attributes['locality'] ?? null;
    $address->administrativeArea = $attributes['administrativeArea'] ?? null;
    $address->postalCode = $attributes['postalCode'] ?? null;
    $address->latitude = $attributes['latitude'] ?? null;
    $address->longitude = $attributes['longitude'] ?? null;

    return $address;
}

function addressProperties(string $type, ?Address $address): array {
    $service = test()->getMockBuilder(GlobalStructuredDataService::class)
        ->onlyMethods(['getSavedAddress'])
        ->getMock();
    $service->method('getSavedAddress')->willReturn($address);

    return (new ReflectionMethod(GlobalStructuredDataService::class, '_addressProperties'))
        ->invoke($service, $type, 1);
}

// =========================================================================
// getTypeForSlug()
// =========================================================================

it('maps a known url slug to its StructuredData type constant', function () {
    expect((new GlobalStructuredDataService())->getTypeForSlug('local-business'))
        ->toBe(StructuredDataElement::TYPE_LOCAL_BUSINESS);
});

it('returns null for an unknown slug', function () {
    expect((new GlobalStructuredDataService())->getTypeForSlug('not-a-real-slug'))->toBeNull();
});

// =========================================================================
// getTypeLabel()
// =========================================================================

it('returns the display label for a known type', function () {
    expect((new GlobalStructuredDataService())->getTypeLabel(StructuredDataElement::TYPE_LOCAL_BUSINESS))
        ->toBe('Local Business');
});

it('falls back to the type constant itself for an unknown type', function () {
    expect((new GlobalStructuredDataService())->getTypeLabel('NotARealType'))->toBe('NotARealType');
});

// =========================================================================
// _addressProperties()
// =========================================================================

it('returns no properties when there is no saved address', function () {
    expect(addressProperties(StructuredDataElement::TYPE_ORGANIZATION, null))->toBe([]);
});

it('builds a postal address from the saved address fields', function () {
    $address = addressWith([
        'addressLine1' => '123 Main St',
        'locality' => 'Springfield',
        'administrativeArea' => 'IL',
        'postalCode' => '62704',
        'countryCode' => 'US',
    ]);

    expect(addressProperties(StructuredDataElement::TYPE_ORGANIZATION, $address))->toBe([
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '123 Main St',
            'addressLocality' => 'Springfield',
            'addressRegion' => 'IL',
            'postalCode' => '62704',
            'addressCountry' => 'US',
        ],
    ]);
});

it('joins both address lines into one street address', function () {
    $address = addressWith(['addressLine1' => '123 Main St', 'addressLine2' => 'Suite 4']);

    expect(addressProperties(StructuredDataElement::TYPE_ORGANIZATION, $address)['address']['streetAddress'])
        ->toBe('123 Main St Suite 4');
});

it('omits the address entirely when every field is empty', function () {
    $address = addressWith(['countryCode' => '']);

    expect(addressProperties(StructuredDataElement::TYPE_ORGANIZATION, $address))->toBe([]);
});

it('adds geo coordinates for a local business with lat/long', function () {
    $address = addressWith(['locality' => 'Springfield', 'latitude' => '39.78', 'longitude' => '-89.65']);

    expect(addressProperties(StructuredDataElement::TYPE_LOCAL_BUSINESS, $address)['geo'])->toBe([
        '@type' => 'GeoCoordinates',
        'latitude' => '39.78',
        'longitude' => '-89.65',
    ]);
});

it('omits geo coordinates for an organization even with lat/long set', function () {
    $address = addressWith(['locality' => 'Springfield', 'latitude' => '39.78', 'longitude' => '-89.65']);

    expect(addressProperties(StructuredDataElement::TYPE_ORGANIZATION, $address))->not->toHaveKey('geo');
});

it('omits geo coordinates when latitude or longitude is missing', function () {
    $address = addressWith(['locality' => 'Springfield', 'latitude' => '39.78', 'longitude' => null]);

    expect(addressProperties(StructuredDataElement::TYPE_LOCAL_BUSINESS, $address))->not->toHaveKey('geo');
});
