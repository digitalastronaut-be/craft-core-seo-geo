<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\controllers;

use Craft;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\web\Controller;

use digitalastronaut\craftcoreseogeo\CoreSeoGeo;
use digitalastronaut\craftcoreseogeo\fields\StructuredDataField;
use Fuse\Fuse;
use starfederation\datastar\ServerSentEventGenerator;
use yii\web\Response;

/**
 * Class StructuredDataFieldController

 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredDataFieldController extends Controller {
    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function actionSearchTypes(): Response {
        $this->requireCpRequest();

        $signals = ServerSentEventGenerator::readSignals();

        $search = trim((string)($signals['search'] ?? ''));
        $selectedType = (string)($signals['type'] ?? '');
        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();

        if ($search === '') {
            $typeTree = $schemaOrg->getTypeTree();
        } else {
            $ranked = $this->_rank($schemaOrg->getTypeOptions(), $search);
            $typeTree = $this->_highlightTree(
                $schemaOrg->getFilteredTypeTree(array_keys($ranked)),
                array_map(static fn(array $result): array => $result['indices'], $ranked),
            );
        }

        $html = $this->getView()->renderTemplate('core-seo-geo/fields/structured-data/_schemaTypeSelectorOptions.twig', [
            'typeTree' => $typeTree,
            'expandedTypes' => $selectedType !== '' ? $schemaOrg->getPrimaryAncestorPath($selectedType) : [],
            // While searching, the tree is already pruned down to results + their ancestor
            // path - there's nothing left to collapse, so every branch should render open
            // regardless of which type (if any) is currently selected.
            'forceOpen' => $search !== '',
        ]);

        return $this->_patch($html, '.core-seo-geo-type-options');
    }

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function actionSearchProperties(): Response {
        $this->requireCpRequest();

        $signals = ServerSentEventGenerator::readSignals();
        $search = trim((string)($signals['propertySearch'] ?? ''));
        $typeName = (string)($signals['type'] ?? '');

        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();
        $names = $schemaOrg->getType($typeName)['properties'] ?? [];

        $propertyLabels = [];
        if ($search !== '') {
            foreach ($this->_rank(array_combine($names, $names), $search) as $name => $result) {
                $propertyLabels[$name] = self::_highlightMatches($result['label'], $result['indices']);
            }
        }

        $html = $this->getView()->renderTemplate('core-seo-geo/fields/structured-data/_schemaPropertiesSelectorOptions.twig', [
            'searching' => $search !== '',
            'propertyLabels' => $propertyLabels,
            'propertyGroups' => $search === '' ? $schemaOrg->getGroupedTypeProperties($typeName) : [],
            'propertyTooltips' => $schemaOrg->getPropertyTooltips($names),
        ]);

        return $this->_patch($html, '.core-seo-geo-property-options');
    }

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function actionSyncProperty(): Response {
        $this->requireCpRequest();

        $signals = ServerSentEventGenerator::readSignals();
        $typeName = (string)($signals['type'] ?? '');
        $properties = \is_array($signals['properties'] ?? null) ? $signals['properties'] : [];
        $name = (string)Craft::$app->getRequest()->getQueryParam('name', '');

        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();

        $pillsHtml = $this->getView()->renderTemplate('core-seo-geo/fields/structured-data/_schemaSelectedPills.twig', [
            'typeName' => $typeName,
            'typeLabel' => $typeName !== '' ? ($schemaOrg->getType($typeName)['label'] ?? $typeName) : '',
            'names' => array_keys($properties),
        ]);

        $patches = [
            ['html' => $pillsHtml, 'selector' => '.core-seo-geo-structured-data-selectors-bottom'],
        ];

        if (isset($properties[$name])) {
            $rowHtml = $this->_renderTemplateWithRegisteredJs('core-seo-geo/fields/structured-data/_schemaPropertyRow.twig', [
                'name' => $name,
                'template' => $properties[$name]['template'] ?? '',
                'tooltip' => $schemaOrg->getPropertyTooltip($name),
            ]);

            $patches[] = ['html' => $rowHtml, 'selector' => '.core-seo-geo-mapping-table-options tbody', 'mode' => 'append'];
        } else {
            $patches[] = ['html' => '', 'selector' => self::_propertyRowSelector($name), 'mode' => 'remove'];
        }

        return $this->_patchAll($patches);
    }

    /**
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function actionClearRows(): Response {
        $this->requireCpRequest();

        $signals = ServerSentEventGenerator::readSignals();
        $typeName = (string)($signals['type'] ?? '');

        $schemaOrg = CoreSeoGeo::getInstance()->getSchemaOrg();

        $pillsHtml = $this->getView()->renderTemplate('core-seo-geo/fields/structured-data/_schemaSelectedPills.twig', [
            'typeName' => $typeName,
            'typeLabel' => $typeName !== '' ? ($schemaOrg->getType($typeName)['label'] ?? $typeName) : '',
            'names' => [],
        ]);

        return $this->_patchAll([
            ['html' => $pillsHtml, 'selector' => '.core-seo-geo-structured-data-selectors-bottom'],
            ['html' => '', 'selector' => '.core-seo-geo-mapping-table-options tbody', 'mode' => 'inner'],
        ]);
    }

    /**
     * @param string $template
     * @param array $variables
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    private function _renderTemplateWithRegisteredJs(string $template, array $variables): string {
        $view = Craft::$app->getView();
        $originalNamespace = $view->getNamespace();

        ob_start();
        ob_implicit_flush(false);

        $view->beginPage();
        $view->head();
        $view->beginBody();
        $view->setNamespace(self::_namespace());
        echo $view->renderTemplate($template, $variables);
        $view->setNamespace($originalNamespace);
        $view->endBody();
        $view->endPage(true);

        return ob_get_clean();
    }

    /**
     * @param string $html
     * @param string $selector
     * @param string $mode
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    private function _patch(string $html, string $selector, string $mode = 'outer'): Response {
        return $this->_patchAll([
            ['html' => $html, 'selector' => $selector, 'mode' => $mode],
        ]);
    }

    /**
     * @param array<int, array{html: string, selector: string, mode?: string}> $patches
     * @return Response
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    private function _patchAll(array $patches): Response {
        $sse = new ServerSentEventGenerator();
        $sse->sendHeaders();

        foreach ($patches as $patch) {
            $html = Craft::$app->getView()->namespaceInputs($patch['html'], self::_namespace());
            $sse->patchElements($html, ['selector' => $patch['selector'], 'mode' => $patch['mode'] ?? 'outer']);
        }

        $response = Craft::$app->getResponse();
        $response->isSent = true;

        return $response;
    }

    /**
     * @return string
     *
     * @since v1.0.0
     */
    private static function _namespace(): string {
        return StructuredDataField::settingsNamespace();
    }

    /**
     * @param string $name
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    private static function _propertyRowSelector(string $name): string {
        $id = Craft::$app->getView()->namespaceInputId(
            'core-seo-geo-property-row-' . StringHelper::toKebabCase($name),
            self::_namespace(),
        );

        return '#' . $id;
    }

    /**
     * @param array<string, string> $options value => label
     * @param string $search
     * @return array<string, array{label: string, indices: array<int, array{0: int, 1: int}>}>
     * Ranked best match first; `indices` are the 0-based, inclusive character ranges within
     * each result's label that `$search` actually matched (via Fuse's `includeMatches`),
     * passed to `_highlightMatches()` to show which part of the label matched.
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    private function _rank(array $options, string $search): array {
        if ($search === '') {
            $ranked = [];
            foreach ($options as $value => $label) $ranked[$value] = ['label' => $label, 'indices' => []];
            return $ranked;
        }

        $values = array_keys($options);
        $fuse = new Fuse(array_values($options), ['includeMatches' => true]);

        $ranked = [];
        foreach ($fuse->search($search) as $result) {
            $value = $values[$result['refIndex']];
            $indices = [];

            foreach ($result['matches'] ?? [] as $match) {
                foreach ($match['indices'] ?? [] as $range) $indices[] = $range;
            }

            $ranked[$value] = ['label' => $options[$value], 'indices' => $indices];
        }

        return $ranked;
    }

    /**
     * Wraps the character ranges in `$indices` - as `_rank()` returns them - in `<mark>`,
     * HTML-escaping everything else. Shows which part of a label actually matched the user's
     * search term in the type/property dropdowns.
     *
     * @param string $label
     * @param array<int, array{0: int, 1: int}> $indices
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    private static function _highlightMatches(string $label, array $indices): string {
        if ($indices === []) return Html::encode($label);

        usort($indices, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        $html = '';
        $cursor = 0;

        foreach ($indices as [$start, $end]) {
            if ($start < $cursor) continue;

            $html .= Html::encode(mb_substr($label, $cursor, $start - $cursor));
            $html .= '<mark>' . Html::encode(mb_substr($label, $start, $end - $start + 1)) . '</mark>';
            $cursor = $end + 1;
        }

        $html .= Html::encode(mb_substr($label, $cursor));

        return $html;
    }

    /**
     * Recursively replaces each matched node's plain `label` with `_highlightMatches()`'s HTML
     * - ancestor-context nodes (not themselves a search result) are left as plain text, since
     * `$matchIndices` only has entries for actual matches.
     *
     * @param array<int, array{name: string, label: string, isMatch: bool, children: array}> $nodes
     * @param array<string, array<int, array{0: int, 1: int}>> $matchIndices
     * @return array<int, array{name: string, label: string, isMatch: bool, children: array}>
     *
     * @author      Digitalastronaut
     * @since       v1.4.0
     */
    private function _highlightTree(array $nodes, array $matchIndices): array {
        foreach ($nodes as &$node) {
            if (isset($matchIndices[$node['name']])) {
                $node['label'] = self::_highlightMatches($node['label'], $matchIndices[$node['name']]);
            }

            $node['children'] = $this->_highlightTree($node['children'], $matchIndices);
        }

        return $nodes;
    }
}
