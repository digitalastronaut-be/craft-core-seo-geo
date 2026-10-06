<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\models;

use craft\base\Model;

/**
 * Class StructuredData
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredData extends Model {
    /**
     * @var array<string, mixed> The decoded JSON-LD object, keyed by its own properties (e.g.
     * `@type`, `name`, `description`). This is what whoever renders the field's `<script
     * type="application/ld+json">` tag should read.
     *
     * @since v1.0.0
     */
    public array $data = [];

    /**
     * @var string|null The raw JSON-LD text as typed in the CP input, kept alongside `data` so
     * the textarea can be repopulated verbatim, even when it fails to parse as JSON.
     *
     * @since v1.0.0
     */
    public ?string $raw = null;

    /**
     * @return bool
     * @since       v1.0.0
     */
    public function isEmpty(): bool {
        return $this->data === [] && ($this->raw === null || trim($this->raw) === '');
    }
}
