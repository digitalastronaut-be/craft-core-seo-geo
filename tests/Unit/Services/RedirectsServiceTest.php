<?php

use Carbon\Carbon;
use digitalastronaut\craftcoreseogeo\services\RedirectsService;

/**
 * Nearly everything here reads/writes `coreseogeo_redirects`/`coreseogeo_404s`/
 * `coreseogeo_metrics`, or goes through `Craft::$app->getCache()`/`getDb()` directly
 * (findMatch() included, via the private, cache-backed _getCachedLookup() -- a private
 * method can't be partial-mocked the way a public one can elsewhere in this suite, since
 * PHP resolves a private method call by its defining class, not polymorphically), so all of
 * that belongs in the integration suite. Covered here: the three pure, DB-free helpers --
 * normalizeUri() (public), _isNoiseUri() and _bucketStart() (private, reached via
 * reflection the same way MarkdownServiceTest reaches MarkdownService's private helpers).
 */
function isNoiseUri(string $uri): bool {
    return (new ReflectionMethod(RedirectsService::class, '_isNoiseUri'))->invoke(new RedirectsService(), $uri);
}

function bucketStart(Carbon $time): Carbon {
    return (new ReflectionMethod(RedirectsService::class, '_bucketStart'))->invoke(new RedirectsService(), $time);
}

// =========================================================================
// normalizeUri()
// =========================================================================

it('strips a leading and trailing slash', function () {
    expect(RedirectsService::normalizeUri('/blog/my-post/'))->toBe('blog/my-post');
});

it('strips the query string', function () {
    expect(RedirectsService::normalizeUri('blog/my-post?utm_source=x'))->toBe('blog/my-post');
});

it('decodes percent-encoding', function () {
    expect(RedirectsService::normalizeUri('blog/my%20post'))->toBe('blog/my post');
});

it('leaves an already-normalized uri unchanged', function () {
    expect(RedirectsService::normalizeUri('blog/my-post'))->toBe('blog/my-post');
});

it('normalizes the homepage uri to an empty string', function () {
    expect(RedirectsService::normalizeUri('/'))->toBe('');
});

it('normalizes an already-empty uri to an empty string', function () {
    expect(RedirectsService::normalizeUri(''))->toBe('');
});

// =========================================================================
// _isNoiseUri()
// =========================================================================

it('treats wp-admin paths as noise', function () {
    expect(isNoiseUri('wp-admin/'))->toBeTrue();
    expect(isNoiseUri('wp-admin'))->toBeTrue();
});

it('treats wp-login.php as noise', function () {
    expect(isNoiseUri('wp-login.php'))->toBeTrue();
});

it('treats a dotfile like .env or .git as noise', function () {
    expect(isNoiseUri('.env'))->toBeTrue();
    expect(isNoiseUri('config/.env'))->toBeTrue();
    expect(isNoiseUri('.git/config'))->toBeTrue();
});

it('treats a server-side script extension as noise', function () {
    expect(isNoiseUri('shell.php'))->toBeTrue();
    expect(isNoiseUri('shell.php7'))->toBeTrue();
    expect(isNoiseUri('upload.asp'))->toBeTrue();
    expect(isNoiseUri('upload.aspx'))->toBeTrue();
    expect(isNoiseUri('upload.jsp'))->toBeTrue();
});

it('does not treat a normal content path as noise', function () {
    expect(isNoiseUri('blog/my-post'))->toBeFalse();
});

it('does not treat wp-admin as noise when it is not a path segment', function () {
    expect(isNoiseUri('not-wp-admin'))->toBeFalse();
});

// =========================================================================
// _bucketStart()
// =========================================================================

it('rounds a time down to the start of its 5-minute bucket', function () {
    $time = Carbon::create(2026, 1, 15, 10, 37, 42);

    expect(bucketStart($time)->format('Y-m-d H:i:s'))->toBe('2026-01-15 10:35:00');
});

it('leaves a time already at a bucket boundary unchanged but for seconds', function () {
    $time = Carbon::create(2026, 1, 15, 10, 35, 59);

    expect(bucketStart($time)->format('Y-m-d H:i:s'))->toBe('2026-01-15 10:35:00');
});

it('rounds the very start of an hour down to itself', function () {
    $time = Carbon::create(2026, 1, 15, 10, 0, 30);

    expect(bucketStart($time)->format('Y-m-d H:i:s'))->toBe('2026-01-15 10:00:00');
});

it('rounds the last bucket of an hour down correctly', function () {
    $time = Carbon::create(2026, 1, 15, 10, 59, 59);

    expect(bucketStart($time)->format('Y-m-d H:i:s'))->toBe('2026-01-15 10:55:00');
});

it('does not mutate the original time instance', function () {
    $time = Carbon::create(2026, 1, 15, 10, 37, 42);
    $original = $time->copy();

    bucketStart($time);

    expect($time->eq($original))->toBeTrue();
});
