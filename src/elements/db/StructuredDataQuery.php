<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

use digitalastronaut\craftcoreseogeo\db\Table;

/**
 * Class StructuredDataQuery
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class StructuredDataQuery extends ElementQuery {
    public string|array|null $type = null;
    public mixed $mappingId = null;
    public mixed $ownerId = null;

    /**
     * @param string|string[]|null $value
     * @return static
     */
    public function type(array|string|null $value): static {
        $this->type = $value;

        return $this;
    }

    /**
     * @param mixed $value an id, array of ids, or the `:empty:`/`:notempty:` shorthand
     * @return static
     */
    public function mappingId(mixed $value): static {
        $this->mappingId = $value;

        return $this;
    }

    /**
     * @param mixed $value an id, array of ids, or the `:empty:`/`:notempty:` shorthand
     * @return static
     */
    public function ownerId(mixed $value): static {
        $this->ownerId = $value;

        return $this;
    }

    /**
     * @inheritdoc
     *
     * Only the select columns are added here rather than in `afterPrepare()`: this runs before
     * Craft's internal `_applySelectParam()`, which matters when Craft itself has restricted the
     * select to a single column - e.g. the search service's relevance-scoring subquery clones the
     * query with `->select('elements.id')` so it can embed it in an `IN (...)` condition. Adding
     * columns in `afterPrepare()` would get merged back in after that restriction is applied,
     * turning a one-column subquery into a multi-column one and breaking that `IN (...)` condition
     * with a SQL "Operand should contain 1 column(s)" error. `craft\elements\db\UserQuery`, which
     * joins its own dedicated table the same way, follows this same convention.
     *
     * The join itself can't move here too: its condition needs `elements_sites`, which Craft
     * doesn't join into `$this->subQuery` until after `beforePrepare()` returns, so it stays in
     * `afterPrepare()` below where that alias already exists.
     */
    protected function beforePrepare(): bool {
        $alias = 'coreseogeo_structureddata';

        $this->query->addSelect([
            "$alias.mappingId",
            "$alias.ownerId",
            "$alias.type",
            "$alias.properties",
            "$alias.sourceData",
        ]);

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function afterPrepare(): bool {
        $alias = 'coreseogeo_structureddata';
        $condition = "[[$alias.id]] = [[elements.id]] AND [[$alias.siteId]] = [[elements_sites.siteId]]";

        $this->subQuery->innerJoin([$alias => Table::STRUCTUREDDATA], $condition);
        $this->query->innerJoin([$alias => Table::STRUCTUREDDATA], $condition);

        if ($this->type !== null) {
            $this->subQuery->andWhere(Db::parseParam("$alias.type", $this->type));
        }

        if ($this->mappingId !== null) {
            $this->subQuery->andWhere(Db::parseParam("$alias.mappingId", $this->mappingId));
        }

        if ($this->ownerId !== null) {
            $this->subQuery->andWhere(Db::parseParam("$alias.ownerId", $this->ownerId));
        }

        return parent::afterPrepare();
    }
}
