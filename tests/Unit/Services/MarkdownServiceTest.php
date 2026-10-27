<?php

use digitalastronaut\craftcoreseogeo\services\MarkdownService;
use digitalastronaut\craftcoreseogeo\twig\ExcludeLlmContentNode;
use digitalastronaut\craftcoreseogeo\twig\LlmContentNode;

/**
 * getResult()/getCachedResult()/refresh()/_generate()/_store() all need a booted app --
 * Craft::$app->getCache()/getElements(), plus a real HTTP fetch of the element's own URL --
 * and belong in the integration suite. Covered here: the two public pure methods
 * (htmlToMarkdown(), estimateTokens()), plus the two private pure helpers that do the actual
 * marker-extraction and markdown post-processing work, reached directly via reflection since
 * _generate() is the only (network-bound) caller of the former.
 */
function invokeMarkdownServiceMethod(MarkdownService $service, string $method, array $args): mixed {
    return (new ReflectionMethod($service, $method))->invoke($service, ...$args);
}

function extractLlmContent(string $html): string {
    return invokeMarkdownServiceMethod(new MarkdownService(), '_extractLlmContent', [$html]);
}

function cleanupMarkdown(string $markdown): string {
    return invokeMarkdownServiceMethod(new MarkdownService(), '_cleanup', [$markdown]);
}

function wrapLlmContent(string $content): string {
    return LlmContentNode::START_MARKER . $content . LlmContentNode::END_MARKER;
}

function wrapExcludedContent(string $content): string {
    return ExcludeLlmContentNode::START_MARKER . $content . ExcludeLlmContentNode::END_MARKER;
}

// =========================================================================
// estimateTokens()
// =========================================================================

it('estimates tokens as roughly one quarter of the character count', function () {
    expect(MarkdownService::estimateTokens('12345678'))->toBe(2);
});

it('rounds a partial token up', function () {
    expect(MarkdownService::estimateTokens('123456789'))->toBe(3);
});

it('estimates zero tokens for an empty string', function () {
    expect(MarkdownService::estimateTokens(''))->toBe(0);
});

it('counts multi-byte characters, not raw bytes', function () {
    expect(MarkdownService::estimateTokens('café'))->toBe((int)ceil(4 / 4));
});

// =========================================================================
// _extractLlmContent()
// =========================================================================

it('returns an empty string when there are no llmContent markers', function () {
    expect(extractLlmContent('<html><body><p>Hello</p></body></html>'))->toBe('');
});

it('extracts only the content inside a single llmContent block', function () {
    $html = '<p>Before</p>' . wrapLlmContent('<p>Hello</p>') . '<p>After</p>';

    expect(extractLlmContent($html))->toBe('<p>Hello</p>');
});

it('concatenates multiple llmContent blocks in document order', function () {
    $html = wrapLlmContent('<p>First</p>') . '<p>Ignored</p>' . wrapLlmContent('<p>Second</p>');

    expect(extractLlmContent($html))->toBe('<p>First</p><p>Second</p>');
});

it('removes a nested excludeLlmContent region from the extracted content', function () {
    $html = wrapLlmContent('<p>Keep</p>' . wrapExcludedContent('<p>Drop</p>') . '<p>Also keep</p>');

    expect(extractLlmContent($html))->toBe('<p>Keep</p><p>Also keep</p>');
});

// =========================================================================
// _cleanup()
// =========================================================================

it('strips leftover HTML tags from link text', function () {
    expect(cleanupMarkdown('[<b>Click</b> here](/path)'))->toBe('[Click here](/path)');
});

it('drops a link whose text is empty once tags are stripped', function () {
    expect(cleanupMarkdown('Before [<img src="x">](/path) after'))->toBe('Before  after');
});

it('removes an empty list item left behind after a removed node', function () {
    expect(cleanupMarkdown("- Item one\n-   \n- Item two"))->toBe("- Item one\n\n- Item two");
});

it('decodes HTML entities left over after stripping tags', function () {
    expect(cleanupMarkdown('[Fish &amp; Chips](/path)'))->toBe('[Fish & Chips](/path)');
});

it('collapses multiple consecutive blank lines into one', function () {
    expect(cleanupMarkdown("Para one\n\n\n\nPara two"))->toBe("Para one\n\nPara two");
});

// =========================================================================
// htmlToMarkdown() (the full conversion pipeline, incl. _cleanup())
// =========================================================================

it('converts headings and paragraphs to atx-style markdown', function () {
    $markdown = (new MarkdownService())->htmlToMarkdown('<h1>Title</h1><p>Some text.</p>');

    expect($markdown)->toContain('# Title')->toContain('Some text.');
});

it('strips configured nodes entirely instead of converting them', function () {
    $html = '<p>Before</p><img src="photo.jpg" alt="A photo"><script>alert(1)</script><p>After</p>';

    $markdown = (new MarkdownService())->htmlToMarkdown($html);

    expect($markdown)
        ->toContain('Before')
        ->toContain('After')
        ->not->toContain('photo.jpg')
        ->not->toContain('alert(1)');
});

it('converts a table using the registered table converter', function () {
    $html = '<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>';

    $markdown = (new MarkdownService())->htmlToMarkdown($html);

    expect($markdown)->toContain('A')->toContain('B')->toContain('1')->toContain('2');
});
