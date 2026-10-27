<?php

/**
 * Pure-unit test bootstrap: the Craft app is never booted here, so \Craft and \Yii --
 * both global classes outside the PSR-4 autoload map -- have to be pulled in by hand.
 * Craft::$app stays null for the whole suite; production code that guards on
 * `Craft::$app instanceof ...` before touching a service correctly skips those paths,
 * and Craft::t()/Yii::t() fall back to plain strtr() interpolation without translation.
 */
require_once dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__) . '/vendor/craftcms/cms/src/Craft.php';

use craft\base\ElementInterface;
use digitalastronaut\craftcoreseogeo\models\SeoData;
use digitalastronaut\craftcoreseogeo\seochecks\SeoScoreCheckContext;

/**
 * A stubbed element satisfying ElementInterface without touching the database. Only
 * getUrl() is configured since that's the only element method the seo-score checks
 * call directly (duplicate-content/element-id lookups need a real DB and belong in the
 * integration suite instead).
 */
function stubElement(?string $url = null): ElementInterface {
    $element = test()->createStub(ElementInterface::class);
    $element->method('getUrl')->willReturn($url);

    return $element;
}

function seoScoreContext(SeoData $seoData, ?string $url = null, int $fieldId = 1): SeoScoreCheckContext {
    return new SeoScoreCheckContext(stubElement($url), $seoData, $fieldId);
}

/**
 * Parses an HTML fragment the same way the checks expect: local-name() xpath queries
 * work against this regardless of namespace, so a plain DOMDocument is equivalent to
 * the Masterminds\HTML5 parser SeoScoreCheckContext uses in production.
 */
function parseHtmlFragment(string $html): DOMDocument {
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_use_internal_errors(false);

    return $document;
}

/**
 * Injects an already-parsed document into a SeoScoreCheckContext via its private memo,
 * bypassing the real HTTP fetch in getDocument() entirely -- lets document-dependent
 * checks (heading structure, alt text, <main> presence, ...) be tested against known
 * markup without a live front end to fetch.
 */
function withDocument(SeoScoreCheckContext $context, ?DOMDocument $document): SeoScoreCheckContext {
    $ref = new ReflectionClass($context);

    $ref->getProperty('_documentFetched')->setValue($context, true);
    $ref->getProperty('_document')->setValue($context, $document);

    return $context;
}
