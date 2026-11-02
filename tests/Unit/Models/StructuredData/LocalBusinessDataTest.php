<?php

use digitalastronaut\craftcoreseogeo\models\structureddata\LocalBusinessData;

// =========================================================================
// toProperties() - geo
// =========================================================================

it('adds geo coordinates when both latitude and longitude are set', function () {
    $model = new LocalBusinessData(['latitude' => '39.78', 'longitude' => '-89.65']);

    expect($model->toProperties()['geo'])->toBe([
        '@type' => 'GeoCoordinates',
        'latitude' => 39.78,
        'longitude' => -89.65,
    ]);
});

it('omits geo coordinates when longitude is missing', function () {
    $model = new LocalBusinessData(['latitude' => '39.78']);

    expect($model->toProperties())->not->toHaveKey('geo');
});

it('omits geo coordinates when latitude is missing', function () {
    $model = new LocalBusinessData(['longitude' => '-89.65']);

    expect($model->toProperties())->not->toHaveKey('geo');
});
