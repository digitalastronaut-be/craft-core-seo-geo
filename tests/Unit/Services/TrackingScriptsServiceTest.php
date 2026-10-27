<?php

use digitalastronaut\craftcoreseogeo\records\TrackingScript;
use digitalastronaut\craftcoreseogeo\services\TrackingScriptsService;

/**
 * Everything public here (getAllTrackingScripts(), getTrackingScriptById(),
 * saveTrackingScript(), deleteTrackingScript()) is a thin ActiveRecord wrapper, and
 * injectIntoHtml() unconditionally calls _getSnippetsByLocation() -> _getEnabledTrackingScripts(),
 * which queries the database -- none of that runs without a booted app/DB, and belongs in the
 * integration suite. Covered here: _findInsertions(), the one pure, DB-free piece of the
 * algorithm (resolving snippets-by-location into offsets against a given HTML string), reached
 * via reflection the same way MarkdownServiceTest reaches MarkdownService's private helpers.
 */
function emptySnippetsByLocation(): array {
    return array_fill_keys(TrackingScript::LOCATIONS, '');
}

function findInsertions(string $html, array $snippetsByLocation): array {
    return (new ReflectionMethod(TrackingScriptsService::class, '_findInsertions'))
        ->invoke(new TrackingScriptsService(), $html, $snippetsByLocation);
}

it('inserts a head-start snippet right after the opening head tag', function () {
    $snippets = [...emptySnippetsByLocation(), TrackingScript::LOCATION_HEAD_START => '<script>a</script>'];
    $html = '<html><head><title>T</title></head><body></body></html>';

    $insertions = findInsertions($html, $snippets);

    expect($insertions)->toBe([[strpos($html, '<head>') + strlen('<head>'), '<script>a</script>']]);
});

it('tolerates attributes on the opening head tag', function () {
    $snippets = [...emptySnippetsByLocation(), TrackingScript::LOCATION_HEAD_START => '<script>a</script>'];
    $html = '<html><head data-x="1"><title>T</title></head><body></body></html>';

    $insertions = findInsertions($html, $snippets);
    $expectedOffset = strpos($html, '<head data-x="1">') + strlen('<head data-x="1">');

    expect($insertions)->toBe([[$expectedOffset, '<script>a</script>']]);
});

it('inserts a head-end snippet right before the closing head tag', function () {
    $snippets = [...emptySnippetsByLocation(), TrackingScript::LOCATION_HEAD_END => '<script>b</script>'];
    $html = '<html><head><title>T</title></head><body></body></html>';

    $insertions = findInsertions($html, $snippets);

    expect($insertions)->toBe([[strpos($html, '</head>'), '<script>b</script>']]);
});

it('inserts a body-start snippet right after the opening body tag', function () {
    $snippets = [...emptySnippetsByLocation(), TrackingScript::LOCATION_BODY_START => '<script>c</script>'];
    $html = '<html><head></head><body class="x"><p>Hi</p></body></html>';

    $insertions = findInsertions($html, $snippets);
    $expectedOffset = strpos($html, '<body class="x">') + strlen('<body class="x">');

    expect($insertions)->toBe([[$expectedOffset, '<script>c</script>']]);
});

it('inserts a body-end snippet right before the closing body tag', function () {
    $snippets = [...emptySnippetsByLocation(), TrackingScript::LOCATION_BODY_END => '<script>d</script>'];
    $html = '<html><head></head><body><p>Hi</p></body></html>';

    $insertions = findInsertions($html, $snippets);

    expect($insertions)->toBe([[strpos($html, '</body>'), '<script>d</script>']]);
});

it('matches head and body tags case-insensitively', function () {
    $snippets = [
        ...emptySnippetsByLocation(),
        TrackingScript::LOCATION_HEAD_START => '<script>a</script>',
        TrackingScript::LOCATION_BODY_START => '<script>c</script>',
    ];
    $html = '<HTML><HEAD></HEAD><BODY></BODY></HTML>';

    expect(findInsertions($html, $snippets))->toHaveCount(2);
});

it('returns one insertion per non-empty location, all at once', function () {
    $snippets = [
        TrackingScript::LOCATION_HEAD_START => '<script>a</script>',
        TrackingScript::LOCATION_HEAD_END => '<script>b</script>',
        TrackingScript::LOCATION_BODY_START => '<script>c</script>',
        TrackingScript::LOCATION_BODY_END => '<script>d</script>',
    ];
    $html = '<html><head></head><body></body></html>';

    expect(findInsertions($html, $snippets))->toHaveCount(4);
});

it('returns nothing when every location snippet is empty', function () {
    $html = '<html><head></head><body></body></html>';

    expect(findInsertions($html, emptySnippetsByLocation()))->toBe([]);
});

it('skips a location whose snippet is set but the page has no matching tag', function () {
    $snippets = [...emptySnippetsByLocation(), TrackingScript::LOCATION_BODY_START => '<script>c</script>'];
    $html = '<html><head></head></html>';

    expect(findInsertions($html, $snippets))->toBe([]);
});
