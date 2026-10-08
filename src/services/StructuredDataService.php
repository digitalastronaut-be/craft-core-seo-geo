<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\services;

use Craft;

use craft\base\Component;
use craft\base\ElementInterface;
use craft\helpers\Json;

/**
 * Class StructuredDataService
 *
 * Builds the structured data `StructuredDataField` (and the standalone `StructuredData`
 * element) expose: each configured property is a Twig template string, stored per schema.org
 * property name; `renderProperties()` renders every one against the owning element and
 * `toJsonLd()` assembles the final, emittable object from the result.
 *
 * {@see \digitalastronaut\craftcoreseogeo\variables\CoreSeoGeoVariable}).
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredDataService extends Component {
    /**
     * Renders every configured property's Twig template against `$element` and decodes each
     * result, producing the data half of a structured data object (everything but
     * `@context`/`@type`; see `toJsonLd()`).
     *
     * A template rendering to an empty string is omitted entirely (an unset property), rather
     * than being kept as `""`. A render that throws (e.g. a typo'd filter) is logged and
     * skipped the same way, rather than failing the whole object over one bad property. Each
     * surviving result is decoded by `_decodeRenderedProperty()`, which lets a template return
     * a plain string (the common case, e.g. a `name`) or a nested object (e.g.
     * `craft.coreSeoGeo.schema(...)`) without the template needing to say which; a result
     * that isn't valid JSON is kept as the plain string it already is.
     *
     * @param ElementInterface $element the element the property templates render against
     * @param array<string, array{template?: string|null}> $properties keyed by schema.org
     * property name
     * @return array<string, mixed>
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function renderProperties(ElementInterface $element, array $properties): array {
        $data = [];
        $view = Craft::$app->getView();

        foreach ($properties as $property => $propertyData) {
            $template = $propertyData['template'] ?? null;

            if ($template === null || trim($template) === '') continue;

            try {
                $rendered = trim($view->renderObjectTemplate($template, $element, ['entry' => $element]));
            } catch (\Throwable $e) {
                Craft::warning("Couldn't render the \"{$property}\" structured data property: {$e->getMessage()}", __METHOD__);
                continue;
            }

            if ($rendered === '') continue;

            $data[$property] = $this->_decodeRenderedProperty($rendered);
        }

        return $data;
    }

    /**
     * Assembles the final, emittable JSON-LD object from a schema.org type and its
     * already-rendered property data (see `renderProperties()`).
     *
     * @param string $type a schema.org type name, e.g. `WebPage`
     * @param array<string, mixed> $data the rendered property data, keyed by property name
     * @return array{'@context': string, '@type': string}
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function toJsonLd(string $type, array $data): array {
        return array_merge([
            '@context' => 'https://schema.org',
            '@type' => $type,
        ], $data);
    }

    // Private Methods
    // =========================================================================

    /**
     * Decodes one rendered property template's output. Handles three shapes a template can
     * produce: a plain string (the common case, kept as-is), hand-written JSON (an object or
     * array literal typed directly into the template), and a `craft.coreSeoGeo.schema(...)`
     * builder chain, whose nested object needs two corrections before it fits as a property
     * value rather than a document of its own.
     *
     * @param string $rendered
     * @return mixed
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    private function _decodeRenderedProperty(string $rendered): mixed {
        $decoded = Json::decodeIfJson($this->_unwrapJsonLdScript($rendered));

        if (\is_array($decoded)) unset($decoded['@context']);

        return $decoded;
    }

    /**
     * A bare `{{ craft.coreSeoGeo.schema(...) }}` doesn't render to JSON: Twig stringifies the
     * `spatie/schema-org` builder object via PHP's own `__toString()`, which that library
     * defines as `toScript()` - a full `<script type="application/ld+json">...</script>` tag,
     * meant for dropping straight into page HTML, not for nesting inside another object's
     * property. Unwrapping it here, in the one place responsible for interpreting a rendered
     * property template, keeps the `schema()` builder itself
     * ({@see SchemaOrgService::build()}) a thin, unmodified pass-through to the library.
     *
     * @param string $rendered
     * @return string the script tag's inner JSON, or `$rendered` unchanged if it isn't one
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    private function _unwrapJsonLdScript(string $rendered): string {
        if (preg_match('/^<script\b[^>]*>(.*)<\/script>$/is', $rendered, $matches) === 1) {
            return trim($matches[1]);
        }

        return $rendered;
    }
}
