<?php

use digitalastronaut\craftcoreseogeo\services\ContentSeoService;

/**
 * Every public method either queries `coreseogeo_seodata` directly or goes through
 * `_tableExists()` (`Craft::$app->getDb()->tableExists()`) first, so none of them run
 * without a booted app/DB -- that's the integration suite's job. Covered here:
 * `_ensureRowIds()`, the one pure, DB-free piece of logic, reached via reflection the same
 * way MarkdownServiceTest reaches MarkdownService's private helpers.
 */
function ensureRowIds(array $rows): array {
    return (new ReflectionMethod(ContentSeoService::class, '_ensureRowIds'))->invoke(null, $rows);
}

it('assigns a new rowId to a row that has none yet', function () {
    $result = ensureRowIds([['label' => 'Title']]);

    expect($result[0]['rowId'])->toBeString()->not->toBe('');
});

it('assigns a new rowId to a row whose rowId is an empty string', function () {
    $result = ensureRowIds([['label' => 'Title', 'rowId' => '']]);

    expect($result[0]['rowId'])->not->toBe('');
});

it('keeps an existing rowId unchanged', function () {
    $result = ensureRowIds([['label' => 'Title', 'rowId' => 'existing-id']]);

    expect($result[0]['rowId'])->toBe('existing-id');
});

it('assigns a different rowId to each row missing one', function () {
    $result = ensureRowIds([['label' => 'A'], ['label' => 'B']]);

    expect($result[0]['rowId'])->not->toBe($result[1]['rowId']);
});

it('skips a non-array row entirely', function () {
    expect(ensureRowIds(['not an array']))->toBe(['not an array']);
});

it('returns an empty array unchanged', function () {
    expect(ensureRowIds([]))->toBe([]);
});
