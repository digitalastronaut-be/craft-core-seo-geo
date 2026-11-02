<?php

use digitalastronaut\craftcoreseogeo\models\structureddata\OrganizationData;

// =========================================================================
// toProperties() - address
// =========================================================================

it('builds a postal address from the address fields', function () {
    $model = new OrganizationData([
        'addressLine1' => '123 Main St',
        'locality' => 'Springfield',
        'administrativeArea' => 'IL',
        'postalCode' => '62704',
        'countryCode' => 'US',
    ]);

    expect($model->toProperties()['address'])->toBe([
        '@type' => 'PostalAddress',
        'streetAddress' => '123 Main St',
        'addressLocality' => 'Springfield',
        'addressRegion' => 'IL',
        'postalCode' => '62704',
        'addressCountry' => 'US',
    ]);
});

it('joins both address lines into one street address', function () {
    $model = new OrganizationData(['addressLine1' => '123 Main St', 'addressLine2' => 'Suite 4']);

    expect($model->toProperties()['address']['streetAddress'])->toBe('123 Main St Suite 4');
});

it('omits the address entirely when every address field is empty', function () {
    $model = new OrganizationData(['name' => 'Acme']);

    expect($model->toProperties())->not->toHaveKey('address');
});
