<?php

use digitalastronaut\craftcoreseogeo\services\SchemaOrgService;
use yii\base\InvalidArgumentException;

/**
 * Exercises SchemaOrgService against the real, shipped `static/schemaorg/registry.php` --
 * it's a static vocabulary file, not content, so there's no need to fake it. Fixtures below
 * (Person/Organization/LocalBusiness/Thing/Place parent chains, the `name`/`address`/`url`
 * properties) are schema.org vocabulary facts stable enough to assert on directly; verified
 * against the installed registry before writing these.
 *
 * breadcrumbs() with the default $includeHomepage is the one branch not covered here -- it
 * queries Entry::find() for the current site's homepage and needs a booted app/DB.
 */
function findSchemaTreeNode(array $nodes, string $name): ?array {
    foreach ($nodes as $node) {
        if ($node['name'] === $name) return $node;

        $found = findSchemaTreeNode($node['children'], $name);
        if ($found !== null) return $found;
    }

    return null;
}

// =========================================================================
// getType() / getProperty() / getTypeWithProperties()
// =========================================================================

it('returns a known type with its label and primary parent', function () {
    $type = (new SchemaOrgService())->getType('Person');

    expect($type['label'])->toBe('Person');
    expect($type['parentTypes'])->toBe(['Thing']);
});

it('returns null for an unknown type', function () {
    expect((new SchemaOrgService())->getType('NotARealSchemaType'))->toBeNull();
});

it('returns a known property with its expected and native types', function () {
    $property = (new SchemaOrgService())->getProperty('name');

    expect($property['expectedTypes'])->toBe(['Text']);
    expect($property['nativeTypes'])->toBe(['Thing']);
});

it('returns null for an unknown property', function () {
    expect((new SchemaOrgService())->getProperty('notARealProperty'))->toBeNull();
});

it('expands a type\'s properties list into full property definitions', function () {
    $type = (new SchemaOrgService())->getTypeWithProperties('Person');

    expect($type['properties'])->toHaveKey('name');
    expect($type['properties']['name']['expectedTypes'])->toBe(['Text']);
});

it('returns null when expanding an unknown type\'s properties', function () {
    expect((new SchemaOrgService())->getTypeWithProperties('NotARealSchemaType'))->toBeNull();
});

// =========================================================================
// getTypeOptions()
// =========================================================================

it('maps every type name to its label', function () {
    $options = (new SchemaOrgService())->getTypeOptions();

    expect($options['Person'])->toBe('Person');
    expect($options['Organization'])->toBe('Organization');
});

// =========================================================================
// getPrimaryAncestorPath() / getAncestorChain()
// =========================================================================

it('walks only the primary parent for getPrimaryAncestorPath()', function () {
    expect((new SchemaOrgService())->getPrimaryAncestorPath('LocalBusiness'))
        ->toBe(['LocalBusiness', 'Organization', 'Thing']);
});

it('walks every parent for getAncestorChain(), not just the primary one', function () {
    expect((new SchemaOrgService())->getAncestorChain('LocalBusiness'))
        ->toBe(['LocalBusiness', 'Organization', 'Place', 'Thing']);
});

it('returns just the type itself when it has no parents', function () {
    expect((new SchemaOrgService())->getPrimaryAncestorPath('Thing'))->toBe(['Thing']);
});

// =========================================================================
// getGroupedTypeProperties()
// =========================================================================

it('groups a property under the ancestor it is actually native to', function () {
    $groups = (new SchemaOrgService())->getGroupedTypeProperties('Person');

    expect($groups['Person']['properties'])->toContain('address');
    expect($groups['Thing']['properties'])->toContain('name')->toContain('url');
});

it('returns an empty array for an unknown type', function () {
    expect((new SchemaOrgService())->getGroupedTypeProperties('NotARealSchemaType'))->toBe([]);
});

// =========================================================================
// getTypeTree() / getFilteredTypeTree()
// =========================================================================

it('nests a type under its primary parent in the full type tree', function () {
    $tree = (new SchemaOrgService())->getTypeTree();

    $thing = findSchemaTreeNode($tree, 'Thing');
    expect($thing)->not->toBeNull();
    expect(findSchemaTreeNode($thing['children'], 'Person'))->not->toBeNull();
});

it('filters the type tree down to a match\'s ancestor path only', function () {
    $tree = (new SchemaOrgService())->getFilteredTypeTree(['Person']);

    expect($tree)->toHaveCount(1);
    expect($tree[0]['name'])->toBe('Thing');
    expect($tree[0]['isMatch'])->toBeFalse();
    expect($tree[0]['children'])->toHaveCount(1);
    expect($tree[0]['children'][0]['name'])->toBe('Person');
    expect($tree[0]['children'][0]['isMatch'])->toBeTrue();
});

// =========================================================================
// getPropertyDescriptions() / getPropertyTooltip(s)()
// =========================================================================

it('returns descriptions only for known properties', function () {
    $descriptions = (new SchemaOrgService())->getPropertyDescriptions(['name', 'notARealProperty']);

    expect($descriptions)->toHaveKey('name');
    expect($descriptions)->not->toHaveKey('notARealProperty');
});

it('builds a tooltip with a schema.org link for each expected type', function () {
    $tooltip = (new SchemaOrgService())->getPropertyTooltip('name');

    expect($tooltip)
        ->toContain('The name of the item.')
        ->toContain('Accepts:')
        ->toContain('href="https://schema.org/Text"');
});

it('returns an empty tooltip for an unknown property', function () {
    expect((new SchemaOrgService())->getPropertyTooltip('notARealProperty'))->toBe('');
});

it('omits unknown properties entirely when building multiple tooltips', function () {
    $tooltips = (new SchemaOrgService())->getPropertyTooltips(['name', 'notARealProperty']);

    expect($tooltips)->toHaveKey('name');
    expect($tooltips)->not->toHaveKey('notARealProperty');
});

// =========================================================================
// build()
// =========================================================================

it('builds a spatie/schema-org object for a known type', function () {
    $person = (new SchemaOrgService())->build('Person');

    expect($person)->toBeInstanceOf(\Spatie\SchemaOrg\Person::class);
});

it('maps a type whose name is not a valid PHP class name to its override', function () {
    $model = (new SchemaOrgService())->build('3DModel');

    expect($model)->toBeInstanceOf(\Spatie\SchemaOrg\ThreeDimensionalModel::class);
});

it('throws for a type that is not in the registry', function () {
    (new SchemaOrgService())->build('NotARealSchemaType');
})->throws(InvalidArgumentException::class, 'is not a known schema.org type.');

it('throws for a registry type with no spatie/schema-org builder', function () {
    (new SchemaOrgService())->build('Text');
})->throws(InvalidArgumentException::class, 'has no spatie/schema-org builder available.');

// =========================================================================
// breadcrumbs() (without the homepage, which needs a booted app/DB)
// =========================================================================

it('builds a flat BreadcrumbList itemListElement without the homepage', function () {
    $items = [
        ['label' => 'Blog', 'href' => 'https://example.com/blog'],
        ['label' => 'My Post', 'href' => 'https://example.com/blog/my-post'],
    ];

    $result = (new SchemaOrgService())->breadcrumbs($items, includeHomepage: false);

    expect($result)->toBe([
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Blog', 'item' => 'https://example.com/blog'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'My Post', 'item' => 'https://example.com/blog/my-post'],
    ]);
});

it('returns an empty itemListElement for an empty trail without the homepage', function () {
    expect((new SchemaOrgService())->breadcrumbs([], includeHomepage: false))->toBe([]);
});
