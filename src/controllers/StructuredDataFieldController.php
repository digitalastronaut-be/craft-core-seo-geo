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
        
        $search = $signals['search'] ?? '';
        $selectedType = $signals['type'] ?? '';

        $html = $this->getView()->renderTemplate('core-seo-geo/fields/structured-data/_schemaTypeSelectorOptions.twig', [
            'typeOptions' => $this->_rank(CoreSeoGeo::getInstance()->getSchemaOrg()->getTypeOptions(), $search),
            'selectedType' => $selectedType,
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

        $names = CoreSeoGeo::getInstance()->getSchemaOrg()->getType($typeName)['properties'] ?? [];

        $html = $this->getView()->renderTemplate('core-seo-geo/fields/structured-data/_schemaPropertiesSelectorOptions.twig', [
            'propertyOptions' => $this->_rank(array_combine($names, $names), $search),
            'propertyDescriptions' => CoreSeoGeo::getInstance()->getSchemaOrg()->getPropertyDescriptions($names),
        ]);

        return $this->_patch($html, '.core-seo-geo-property-options');
    }

    /**
     * Adds or removes a single property's row. The client already mutated the `properties`
     * signal before calling this, so whether `name` is still in it tells us which case we're
     * in - only that one row is ever rendered or patched, so no other row's `makeMonacoEditor()`
     * call gets re-run and nothing has to be guessed-at and disposed/recreated. The client
     * disposes the Monaco instance itself (`coreSeoGeoDisposeEditor`, registered in
     * `_settings.twig`) in the same click handler that triggers a removal, so the server side
     * of a removal never needs to render anything.
     *
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
     * Clears every mapped property at once, for when the schema type itself is switched or
     * removed - every existing row (and its Monaco editor) is being thrown away together here,
     * rather than one at a time, so there's no per-row id to target. The client disposes all of
     * them itself (`coreSeoGeoDisposeAllPropertyEditors`) before calling this, so the server
     * side never needs to render or dispose anything row-specific.
     *
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
     * Sends any number of element patches over a single SSE response, so a signal change that
     * affects more than one part of the page (e.g. the selected-item pills and the mapping table)
     * doesn't need a request per patch.
     *
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
        return 'types[' . Html::id(StructuredDataField::class) . ']';
    }

    /**
     * Builds the namespaced id selector for a property's row, matching whatever
     * `_schemaPropertyRow.twig` renders as that `<tr>`'s id (namespaced in bulk by
     * `_patchAll()`'s `namespaceInputs()` call) so a removal patch's selector actually finds it.
     *
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
     * @param array<string, string> $options
     * @param string $search
     * @return array<string, string>
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    private function _rank(array $options, string $search): array {
        if ($search === '') return $options;

        $needle = mb_strtolower($search);
        $exact = $prefix = $contains = [];

        foreach ($options as $value => $label) {
            $haystack = mb_strtolower($label);

            if ($haystack === $needle) {
                $exact[$value] = $label;
            } elseif (str_starts_with($haystack, $needle)) {
                $prefix[$value] = $label;
            } elseif (str_contains($haystack, $needle)) {
                $contains[$value] = $label;
            }
        }

        return $exact + $prefix + $contains;
    }
}
