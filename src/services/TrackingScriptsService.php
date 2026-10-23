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

use digitalastronaut\craftcoreseogeo\records\TrackingScript;

/**
 * Class TrackingScriptsService
 *
 * Reads and writes `coreseogeo_trackingscripts` rows - the user-added tracking scripts (GTM,
 * Meta Pixel, etc.) rendered into every page's `<head>`/`<body>`.
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.9
 */
class TrackingScriptsService extends Component {
    /**
     * Returns every tracking script, ordered by name for a stable, predictable list - there's no
     * manual reordering for this feature, so dragging rows isn't a concern.
     *
     * @return TrackingScript[]
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function getAllTrackingScripts(): array {
        return TrackingScript::find()
            ->orderBy(['name' => SORT_ASC])
            ->all();
    }

    /**
     * @param int $id
     * @return TrackingScript|null
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function getTrackingScriptById(int $id): ?TrackingScript {
        return TrackingScript::findOne($id);
    }

    /**
     * Persists the given tracking script's posted attributes.
     *
     * @param TrackingScript $trackingScript the record to update - a brand new, unsaved one for a create
     * @param string $name
     * @param bool $enabled
     * @param string|null $scriptTemplate
     * @param string $scriptLocation one of `TrackingScript::LOCATIONS`
     * @param string|null $bodyScriptTemplate
     * @param string $bodyScriptLocation one of `TrackingScript::LOCATIONS`
     * @return bool whether the save succeeded
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function saveTrackingScript(
        TrackingScript $trackingScript,
        string $name,
        bool $enabled,
        ?string $scriptTemplate,
        string $scriptLocation,
        ?string $bodyScriptTemplate,
        string $bodyScriptLocation,
    ): bool {
        $trackingScript->name = $name;
        $trackingScript->enabled = $enabled;
        $trackingScript->scriptTemplate = $scriptTemplate;
        $trackingScript->scriptLocation = $scriptLocation;
        $trackingScript->bodyScriptTemplate = $bodyScriptTemplate;
        $trackingScript->bodyScriptLocation = $bodyScriptLocation;

        return $trackingScript->save();
    }

    /**
     * @param TrackingScript $trackingScript
     * @return bool
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function deleteTrackingScript(TrackingScript $trackingScript): bool {
        return (bool)$trackingScript->delete();
    }

    /**
     * Splices every enabled tracking script's snippets into a rendered page's HTML, at the DOM
     * location each one was configured for - called from `PluginTrait::registerTrackingScriptsInjection()`
     * against the full output of every site request.
     *
     * A no-op (the `$html` passed in is returned untouched) when there's nothing enabled, or
     * nothing enabled has a snippet for a location the page actually has a matching tag for.
     *
     * @param string $html the full rendered page
     * @return string
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    public function injectIntoHtml(string $html): string {
        $snippetsByLocation = $this->_getSnippetsByLocation();

        if (array_filter($snippetsByLocation) === []) return $html;

        $insertions = $this->_findInsertions($html, $snippetsByLocation);

        if ($insertions === []) return $html;

        // Computed against the original (unmodified) `$html` above, so applied highest-offset-
        // first here: inserting at a later offset first never invalidates an earlier one still
        // waiting to be applied, while the reverse order would.
        usort($insertions, static fn(array $a, array $b): int => $b[0] <=> $a[0]);

        foreach ($insertions as [$offset, $snippet]) {
            $html = substr_replace($html, $snippet, $offset, 0);
        }

        return $html;
    }

    // Private Methods
    // =========================================================================

    /**
     * @return TrackingScript[]
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    private function _getEnabledTrackingScripts(): array {
        return TrackingScript::find()
            ->where(['enabled' => true])
            ->orderBy(['name' => SORT_ASC])
            ->all();
    }

    /**
     * Every enabled tracking script's non-empty snippets, grouped by their configured
     * `TrackingScript::LOCATION_*` and joined into one string per location - a script and its
     * body script are placed independently, so either (or both) can land in the same bucket as
     * another script's.
     *
     * @return array<string, string> keyed by `TrackingScript::LOCATION_*`
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    private function _getSnippetsByLocation(): array {
        $byLocation = array_fill_keys(TrackingScript::LOCATIONS, []);

        foreach ($this->_getEnabledTrackingScripts() as $trackingScript) {
            if ((string)$trackingScript->scriptTemplate !== '') {
                $byLocation[$trackingScript->scriptLocation][] = $trackingScript->scriptTemplate;
            }

            if ((string)$trackingScript->bodyScriptTemplate !== '') {
                $byLocation[$trackingScript->bodyScriptLocation][] = $trackingScript->bodyScriptTemplate;
            }
        }

        return array_map(static fn(array $snippets): string => implode("\n", $snippets), $byLocation);
    }

    /**
     * Resolves each location with a snippet to insert against an actual offset in `$html`: right
     * after the opening `<head>`/`<body>` tag for the `*_START` locations (matched with a regex
     * rather than a literal search, to tolerate either tag carrying attributes), right before the
     * closing `</head>`/`</body>` tag for the `*_END` ones. A location is skipped (not just left
     * with an empty snippet) when the page doesn't have a matching tag at all.
     *
     * @param string $html
     * @param array<string, string> $snippetsByLocation keyed by `TrackingScript::LOCATION_*`, as built by `_getSnippetsByLocation()`
     * @return array<int, array{0: int, 1: string}> each entry an `[$offset, $snippet]` pair
     *
     * @author      Digitalastronaut
     * @since       v1.0.9
     */
    private function _findInsertions(string $html, array $snippetsByLocation): array {
        $insertions = [];

        if ($snippetsByLocation[TrackingScript::LOCATION_HEAD_START] !== '' && preg_match('/<head\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            $insertions[] = [$match[0][1] + strlen($match[0][0]), $snippetsByLocation[TrackingScript::LOCATION_HEAD_START]];
        }

        if ($snippetsByLocation[TrackingScript::LOCATION_HEAD_END] !== '' && ($pos = stripos($html, '</head>')) !== false) {
            $insertions[] = [$pos, $snippetsByLocation[TrackingScript::LOCATION_HEAD_END]];
        }

        if ($snippetsByLocation[TrackingScript::LOCATION_BODY_START] !== '' && preg_match('/<body\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            $insertions[] = [$match[0][1] + strlen($match[0][0]), $snippetsByLocation[TrackingScript::LOCATION_BODY_START]];
        }

        if ($snippetsByLocation[TrackingScript::LOCATION_BODY_END] !== '' && ($pos = stripos($html, '</body>')) !== false) {
            $insertions[] = [$pos, $snippetsByLocation[TrackingScript::LOCATION_BODY_END]];
        }

        return $insertions;
    }
}
