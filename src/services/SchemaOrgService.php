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

use craft\base\Component;

/**
 * Class SchemaOrgService
 *
 * Read-only lookups over `static/schemaorg/registry.php` - the compiled schema.org vocabulary
 * (see `console\controllers\SchemaController`). The registry is tiny and never changes at
 * runtime, so this just memoizes the `require` for the rest of the request rather than
 * reaching for a database/query builder for data that's closer to a config file than content.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class SchemaOrgService extends Component {
    private const REGISTRY_PATH = __DIR__ . '/../static/schemaorg/registry.php';

    /**
     * @var array{properties: array, types: array}|null
     */
    private ?array $_registry = null;

    /**
     * @param string $name A schema.org type name, e.g. `Review`.
     * @return array{label: string, description: string, parentTypes: string[], properties: string[]}|null
     * `properties` here is just names - use `getTypeWithProperties()` for resolved definitions.
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getType(string $name): ?array {
        return $this->_registry()['types'][$name] ?? null;
    }

    /**
     * @param string $name A schema.org property name, e.g. `reviewRating`.
     * @return array{description: string, expectedTypes: string[]}|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getProperty(string $name): ?array {
        return $this->_registry()['properties'][$name] ?? null;
    }

    /**
     * Same as `getType()`, but with `properties` resolved from names into full definitions -
     * what a property-mapping UI actually wants to render.
     *
     * @param string $name A schema.org type name, e.g. `Review`.
     * @return array{label: string, description: string, parentTypes: string[], properties: array<string, array{description: string, expectedTypes: string[]}>}|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getTypeWithProperties(string $name): ?array {
        $type = $this->getType($name);
        if ($type === null) return null;

        $properties = [];

        foreach ($type['properties'] as $propertyName) {
            $property = $this->getProperty($propertyName);
            if ($property !== null) $properties[$propertyName] = $property;
        }

        $type['properties'] = $properties;

        return $type;
    }

    /**
     * Every pickable type's label, keyed by name - e.g. for a type-picker's option list.
     *
     * @return array<string, string>
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getTypeOptions(): array {
        $options = [];

        foreach ($this->_registry()['types'] as $name => $type) {
            $options[$name] = $type['label'];
        }

        return $options;
    }

    /**
     * @return array{properties: array, types: array}
     */
    private function _registry(): array {
        return $this->_registry ??= require self::REGISTRY_PATH;
    }
}
