<?php

use digitalastronaut\craftcoreseogeo\elements\StructuredData as StructuredDataElement;
use digitalastronaut\craftcoreseogeo\services\GlobalStructuredDataService;

/**
 * Most of this service is a thin wrapper around element queries/Craft::$app (getElement(),
 * getModel(), save()) and belongs in the integration suite. Covered here: the two pure lookups,
 * getTypeForSlug() and getTypeLabel(). The postal address/geo logic that used to live here is
 * now plain model logic - see OrganizationDataTest/LocalBusinessDataTest.
 */

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
