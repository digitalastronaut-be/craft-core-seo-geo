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
use craft\helpers\Html;

use yii\base\InvalidArgumentException;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Schema;

/**
 * Class SchemaOrgService
 *
 * Read-only lookups over `static/schemaorg/registry.php` - the compiled schema.org vocabulary
 * (see `console\controllers\SchemaController`). The registry is tiny and never changes at
 * runtime, so this just memoizes the `require` for the rest of the request rather than
 * reaching for a database/query builder for data that's closer to a config file than content.
 *
 * Also bridges our own registry to `spatie/schema-org`'s fluent builders via `build()`, so
 * templates can construct real schema.org objects for properties that need nested structure
 * (e.g. a `Review`'s `reviewRating`) instead of hand-writing JSON.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class SchemaOrgService extends Component {
    private const REGISTRY_PATH = __DIR__ . '/../static/schemaorg/registry.php';

    /**
     * Maps schema.org type names to their `spatie/schema-org` class name, for the handful of
     * types whose name isn't a valid PHP class name as-is. `3DModel` can't be a class name
     * (PHP identifiers can't start with a digit), so spatie's generator renamed it; every
     * other type's class name matches its schema.org name exactly (confirmed against every
     * class in the installed package).
     *
     * @var array<string, string>
     */
    private const TYPE_CLASS_OVERRIDES = [
        '3DModel' => 'ThreeDimensionalModel',
    ];

    /**
     * @var array{properties: array, types: array}|null
     */
    private ?array $_registry = null;

    /**
     * @param string $name 
     * @return array{label: string, description: string, parentTypes: string[], properties: string[]}|null
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
     * @param string $name 
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
     * @param string[] $names
     * @return array<string, string>
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getPropertyDescriptions(array $names): array {
        $descriptions = [];

        foreach ($names as $name) {
            $property = $this->getProperty($name);
            if ($property !== null) $descriptions[$name] = $property['description'];
        }

        return $descriptions;
    }

    /**
     * A rich-HTML tooltip for a property - its description (the registry's description text
     * already carries `<a href="https://schema.org/...">` links, absolutized at generation time)
     * plus the schema.org types/values it accepts, each linked to its own schema.org page.
     *
     * @param string $name A schema.org property name, e.g. `reviewRating`.
     * @return string Empty string if `$name` isn't a known property.
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getPropertyTooltip(string $name): string {
        $property = $this->getProperty($name);
        if ($property === null) return '';

        $description = trim($property['description']);
        if ($property['expectedTypes'] === []) return $description;

        $typeLinks = implode(', ', array_map(
            static fn(string $type) => Html::a(Html::encode($type), "https://schema.org/{$type}", [
                'target' => '_blank',
                'rel' => 'noopener',
            ]),
            $property['expectedTypes'],
        ));

        return $description . '<p>' . Craft::t('core-seo-geo', 'Accepts:') . ' ' . $typeLinks . '</p>';
    }

    /**
     * @param string[] $names
     * @return array<string, string>
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getPropertyTooltips(array $names): array {
        $tooltips = [];

        foreach ($names as $name) {
            $tooltip = $this->getPropertyTooltip($name);
            if ($tooltip !== '') $tooltips[$name] = $tooltip;
        }

        return $tooltips;
    }

    /**
     * Constructs a fresh `spatie/schema-org` builder for the given schema.org type, e.g.
     * `build('Rating')` returns a `Rating` instance ready for `->ratingValue()->bestRating()`
     * chaining.
     *
     * @param string $type a schema.org type name, e.g. `Review` or `Rating`
     * @return BaseType
     * @throws InvalidArgumentException if `$type` isn't a known schema.org type, or has no
     * corresponding `spatie/schema-org` builder (a scalar/`DataType`, or a type newer than the
     * installed package version)
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function build(string $type): BaseType {
        if ($this->getType($type) === null) {
            throw new InvalidArgumentException("\"{$type}\" is not a known schema.org type.");
        }

        $className = self::TYPE_CLASS_OVERRIDES[$type] ?? $type;
        $factoryMethod = lcfirst($className);

        if (!method_exists(Schema::class, $factoryMethod)) {
            throw new InvalidArgumentException("The schema.org type \"{$type}\" has no spatie/schema-org builder available.");
        }

        return Schema::{$factoryMethod}();
    }

    /**
     * @return array{properties: array, types: array}
     */
    private function _registry(): array {
        return $this->_registry ??= require self::REGISTRY_PATH;
    }
}
