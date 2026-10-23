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
use craft\base\Element;
use craft\elements\Entry;
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
     * @return array{description: string, expectedTypes: string[], nativeTypes: string[]}|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function getProperty(string $name): ?array {
        return $this->_registry()['properties'][$name] ?? null;
    }

    /**
     * @param string $name
     * @return array{label: string, description: string, parentTypes: string[], properties: array<string, array{description: string, expectedTypes: string[], nativeTypes: string[]}>}|null
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
     * @return array<int, array{name: string, label: string, isMatch: bool, children: array}>
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function getTypeTree(): array {
        $types = $this->_registry()['types'];
        $childNamesByParent = [];
        $rootNames = [];

        foreach ($types as $name => $type) {
            $primaryParent = $type['parentTypes'][0] ?? null;

            if ($primaryParent !== null && isset($types[$primaryParent])) {
                $childNamesByParent[$primaryParent][] = $name;
            } else {
                $rootNames[] = $name;
            }
        }

        return $this->_buildTypeTreeNodes($rootNames, $childNamesByParent);
    }

    /**
     * @param string[] $matchNames Ranked best match first, as `_rank()` returns them.
     * @return array<int, array{name: string, label: string, isMatch: bool, children: array}>
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function getFilteredTypeTree(array $matchNames): array {
        $types = $this->_registry()['types'];
        $matchSet = array_flip($matchNames);
        $include = [];
        $bestRank = [];

        foreach ($matchNames as $rank => $matchName) {
            foreach ($this->getPrimaryAncestorPath($matchName) as $ancestorName) {
                $include[$ancestorName] = true;
                $bestRank[$ancestorName] = min($bestRank[$ancestorName] ?? PHP_INT_MAX, $rank);
            }
        }

        $childNamesByParent = [];
        $rootNames = [];

        foreach (array_keys($include) as $name) {
            $primaryParent = $types[$name]['parentTypes'][0] ?? null;

            if ($primaryParent !== null && isset($include[$primaryParent])) {
                $childNamesByParent[$primaryParent][] = $name;
            } else {
                $rootNames[] = $name;
            }
        }

        return $this->_buildTypeTreeNodes($rootNames, $childNamesByParent, $matchSet, $bestRank);
    }

    /**
     * @param string[] $names
     * @param array<string, string[]> $childNamesByParent
     * @param array<string, int>|null $matchSet 
     * @param array<string, int>|null $bestRank 
     * @return array<int, array{name: string, label: string, isMatch: bool, children: array}>
     */
    private function _buildTypeTreeNodes(array $names, array $childNamesByParent, ?array $matchSet = null, ?array $bestRank = null): array {
        if ($bestRank !== null) {
            usort($names, static fn(string $a, string $b): int => ($bestRank[$a] ?? PHP_INT_MAX) <=> ($bestRank[$b] ?? PHP_INT_MAX) ?: strcmp($a, $b));
        } else {
            sort($names);
        }

        $nodes = [];

        foreach ($names as $name) {
            $nodes[] = [
                'name' => $name,
                'label' => $this->getType($name)['label'] ?? $name,
                'isMatch' => $matchSet === null || isset($matchSet[$name]),
                'children' => $this->_buildTypeTreeNodes($childNamesByParent[$name] ?? [], $childNamesByParent, $matchSet, $bestRank),
            ];
        }

        return $nodes;
    }

    /**
     * @param string $name
     * @return string[] 
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function getPrimaryAncestorPath(string $name): array {
        $types = $this->_registry()['types'];
        $path = [];
        $current = $name;

        while ($current !== null && isset($types[$current]) && !isset($path[$current])) {
            $path[$current] = true;
            $current = $types[$current]['parentTypes'][0] ?? null;
        }

        return array_keys($path);
    }

    /**
     * @param string $name
     * @return string[]
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function getAncestorChain(string $name): array {
        $chain = [];
        $queue = [$name];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($chain[$current])) continue;

            $type = $this->getType($current);
            if ($type === null) continue;

            $chain[$current] = true;

            foreach ($type['parentTypes'] as $parentName) {
                $queue[] = $parentName;
            }
        }

        return array_keys($chain);
    }

    /**
     * `getType($name)['properties']` is already flattened with inheritance resolved - every
     * property the type can use, with no record of which ancestor it came from. This re-groups
     * that same list back into "Properties from X" sections (closest ancestor first, `$name`
     * itself included), the way schema.org's own type pages present a type's properties -
     * matched against each property's `nativeTypes` (its `domainIncludes`).
     *
     * @param string $name
     * @return array<string, array{label: string, properties: string[]}> keyed by the ancestor
     * type name each group's properties are native to.
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    public function getGroupedTypeProperties(string $name): array {
        $type = $this->getType($name);
        if ($type === null) return [];

        $unclaimed = array_flip($type['properties']);
        $groups = [];

        foreach ($this->getAncestorChain($name) as $ancestorName) {
            $ancestor = $this->getType($ancestorName);
            if ($ancestor === null) continue;

            $members = [];

            foreach ($type['properties'] as $propertyName) {
                if (!isset($unclaimed[$propertyName])) continue;

                $property = $this->getProperty($propertyName);
                if ($property === null || !\in_array($ancestorName, $property['nativeTypes'], true)) continue;

                $members[] = $propertyName;
                unset($unclaimed[$propertyName]);
            }

            if ($members !== []) {
                $groups[$ancestorName] = [
                    'label' => $ancestor['label'],
                    'properties' => $members,
                ];
            }
        }

        // A property whose `nativeTypes` names none of $name's ancestors (dangling/edge-case
        // vocabulary data) would otherwise silently disappear from the picker instead of just
        // from its proper group.
        if ($unclaimed !== []) {
            $groups[''] = [
                'label' => Craft::t('core-seo-geo', 'Other properties'),
                'properties' => array_values(array_intersect($type['properties'], array_keys($unclaimed))),
            ];
        }

        return $groups;
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
     * Builds a `BreadcrumbList`'s `itemListElement` value from the same `{label, href}` shape
     * templates already build for their own on-page breadcrumb trail - so a template can pass
     * its existing `breadcrumbs` variable straight through, virtual/non-element crumbs (e.g. a
     * filtered archive segment with no entry of its own) included, instead of hand-rolling
     * `ListItem`s itself. The current site's homepage is prepended automatically, unless `$items`
     * already starts with it (so calling this from the homepage itself doesn't double it up).
     *
     * @param array<int, array{label: string, href: string}> $items ordered from just below the
     * homepage to the current page
     * @param bool $includeHomepage
     * @return array<int, array{'@type': string, position: int, name: string, item: string}> the
     * ready `itemListElement` value - plain arrays, not `spatie/schema-org` objects, so a
     * template can `json_encode` it directly
     *
     * @author      Digitalastronaut
     * @since       v1.7.0
     */
    public function breadcrumbs(array $items, bool $includeHomepage = true): array {
        if ($includeHomepage) {
            $homepage = Entry::find()
                ->uri(Element::HOMEPAGE_URI)
                ->siteId(Craft::$app->getSites()->getCurrentSite()->id)
                ->one();

            $alreadyHomepage = $homepage !== null && ($items[0]['href'] ?? null) === $homepage->getUrl();

            if ($homepage !== null && !$alreadyHomepage) {
                array_unshift($items, ['label' => $homepage->title, 'href' => $homepage->getUrl()]);
            }
        }

        $itemListElement = [];

        foreach ($items as $position => $item) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['label'],
                'item' => $item['href'],
            ];
        }

        return $itemListElement;
    }

    /**
     * @return array{properties: array, types: array}
     */
    private function _registry(): array {
        return $this->_registry ??= require self::REGISTRY_PATH;
    }
}
