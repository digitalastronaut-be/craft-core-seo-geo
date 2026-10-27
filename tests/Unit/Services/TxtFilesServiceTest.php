<?php

use digitalastronaut\craftcoreseogeo\services\TxtFilesService;

/**
 * getTypeLabel() is the only method here with no dependency on CoreSeoGeo::getInstance()
 * (every get*Txt() generator reads plugin Settings through it) or Craft::$app (config, sites,
 * cache, elements). Every actual txt-file generator -- robots/humans/ads/security/trust/llms/
 * llms-full -- is settings- and/or DB-driven end to end and belongs in the integration suite.
 */
it('returns the display label for a known file type slug', function () {
    expect((new TxtFilesService())->getTypeLabel('robots'))->toBe('robots.txt');
    expect((new TxtFilesService())->getTypeLabel('llms-full'))->toBe('llms-full.txt');
});

it('returns null for an unknown file type slug', function () {
    expect((new TxtFilesService())->getTypeLabel('not-a-real-type'))->toBeNull();
});
