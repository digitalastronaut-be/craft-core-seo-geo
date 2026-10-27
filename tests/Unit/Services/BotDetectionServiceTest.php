<?php

use digitalastronaut\craftcoreseogeo\services\BotDetectionService;

/**
 * Exercises BotDetectionService against the real, shipped `static/aibots/bots.json` --
 * it's a static reference list, not content, so there's no need to fake it. "GPTBot" and
 * "ClaudeBot" are verified entries in the installed list before writing these.
 */

it('loads the known bots list from the bundled json file', function () {
    $bots = (new BotDetectionService())->getKnownBots();

    expect($bots)->toBeArray()->not->toBeEmpty();
    expect($bots)->toContain('GPTBot')->toContain('ClaudeBot');
});

it('detects a bot whose user agent exactly matches a known entry', function () {
    expect((new BotDetectionService())->isAiBot('GPTBot'))->toBeTrue();
});

it('detects a bot whose user agent contains a known entry as a substring', function () {
    expect((new BotDetectionService())->isAiBot('Mozilla/5.0 (compatible; GPTBot/1.0)'))->toBeTrue();
});

it('matches case-insensitively', function () {
    expect((new BotDetectionService())->isAiBot('mozilla/5.0 (compatible; gptbot/1.0)'))->toBeTrue();
});

it('returns false for a regular browser user agent', function () {
    expect((new BotDetectionService())->isAiBot('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'))->toBeFalse();
});

it('returns false for an empty user agent', function () {
    expect((new BotDetectionService())->isAiBot(''))->toBeFalse();
});

it('returns the matched bot name', function () {
    expect((new BotDetectionService())->getDetectedBotName('ClaudeBot/1.0'))->toBe('ClaudeBot');
});

it('returns null when no bot matches', function () {
    expect((new BotDetectionService())->getDetectedBotName('Mozilla/5.0'))->toBeNull();
});

it('returns null for an empty user agent without scanning the list', function () {
    expect((new BotDetectionService())->getDetectedBotName(''))->toBeNull();
});

it('memoizes the loaded bots list across calls', function () {
    $service = new BotDetectionService();

    expect($service->getKnownBots())->toBe($service->getKnownBots());
});
