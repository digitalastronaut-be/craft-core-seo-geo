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
use craft\helpers\Html;
use craft\helpers\Json;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\elements\StructuredData as StructuredDataElement;

/**
 * Class JsonLdGraphService

 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.6.0
 */
class JsonLdGraphService extends Component {
    /**
     * @var array<string, array<string, mixed>> 
     * @since v1.6.0
     */
    private array $objects = [];

    /**
     * @param array<string, mixed> $object
     * @param string $id
     * @return string `$id`, unchanged - returned so a caller can chain straight into
     * `reference()` without repeating itself
     *
     * @author      Digitalastronaut
     * @since       v1.6.0
     */
    public function add(array $object, string $id): string {
        unset($object['@context'], $object['@id']);

        $this->objects[$id] = ['@id' => $id] + $object;

        return $id;
    }

    /**
     * @author      Digitalastronaut
     * @since       v1.6.0
     */
    public function addElement(ElementInterface $element): array {
        $url = $element->getUrl() ?? Craft::$app->getRequest()->getAbsoluteUrl();

        $rows = StructuredDataElement::find()
            ->ownerId($element->id)
            ->siteId($element->siteId)
            ->status(null)
            ->all();

        return array_map(
            fn(StructuredDataElement $row): string => $this->_addStructuredDataElement($row, $url),
            $rows,
        );
    }

    /**
     * @param string $id
     * @return bool whether something's already been added under `$id`
     *
     * @author      Digitalastronaut
     * @since       v1.6.0
     */
    public function has(string $id): bool {
        return isset($this->objects[$id]);
    }

    /**
     * @param string $id
     * @return array{'@id': string} the pointer a property value uses to refer to another node
     * already in the graph, instead of nesting a full copy of it
     *
     * @author      Digitalastronaut
     * @since       v1.6.0
     */
    public function reference(string $id): array {
        return ['@id' => $id];
    }

    /**
     * @param string $type a schema.org type name, e.g. `WebPage`
     * @param string $url
     * @param string|null $disambiguator appended when more than one object of this `$type` can
     * end up under the same `$url` (e.g. several owner-backed rows that all fall back to the
     * current request's URL because their owner isn't individually routable) - without it, those
     * objects would collide on the same `@id` and silently overwrite each other in `add()`
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.6.0
     */
    public function idFor(string $type, string $url, ?string $disambiguator = null): string {
        $id = rtrim($url, '/') . '#' . strtolower($type);

        return $disambiguator === null ? $id : $id . '-' . $disambiguator;
    }

    /**
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.6.0
     */
    public function render(): string {
        $this->_addGlobalObjects();

        if ($this->objects === []) return '';

        $graph = [
            '@context' => 'https://schema.org',
            '@graph' => array_values($this->objects),
        ];

        return Html::tag('script', Json::encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), [
            'type' => 'application/ld+json',
        ]);
    }

    /**
     * @return void
     *
     * @author      Digitalastronaut
     * @since       v1.6.0
     */
    private function _addGlobalObjects(): void {
        $site = Craft::$app->getSites()->getCurrentSite();
        $globalStructuredData = CoreSeoGeo::getInstance()->getGlobalStructuredData();

        foreach (StructuredDataElement::TYPES as $type) {
            $element = $globalStructuredData->getElement($type, $site->id);

            if ($element !== null) $this->_addStructuredDataElement($element, $site->getBaseUrl());
        }
    }

    /**
     * @param StructuredDataElement $element
     * @param string $url
     * @return string the `@id` it was added under
     *
     * @author      Digitalastronaut
     * @since       v1.6.0
     */
    private function _addStructuredDataElement(StructuredDataElement $element, string $url): string {
        // Owner-backed rows (unlike the global, one-per-type objects) can share both `$url` (the
        // owner falls back to the current request's URL when it has none of its own) and `$type`
        // (the same mapping) with a sibling row, so disambiguate on this row's own uid - it's
        // already guaranteed unique per owner and stable across environments.
        $id = $this->idFor($element->type, $url, $element->ownerId !== null ? $element->uid : null);

        return $this->add($element->toJsonLd(), $id);
    }
}
