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
    public mixed $fieldId = null;
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
    public function fieldId(mixed $value): static {
        $this->fieldId = $value;

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
     */
    protected function afterPrepare(): bool {
        $alias = 'coreseogeo_structureddata';
        $condition = "[[$alias.id]] = [[elements.id]] AND [[$alias.siteId]] = [[elements_sites.siteId]]";

        $this->subQuery->innerJoin([$alias => Table::STRUCTUREDDATA], $condition);
        $this->query->innerJoin([$alias => Table::STRUCTUREDDATA], $condition);

        $this->query->addSelect([
            "$alias.fieldId",
            "$alias.ownerId",
            "$alias.type",
            "$alias.properties",
        ]);

        if ($this->type !== null) {
            $this->subQuery->andWhere(Db::parseParam("$alias.type", $this->type));
        }

        if ($this->fieldId !== null) {
            $this->subQuery->andWhere(Db::parseParam("$alias.fieldId", $this->fieldId));
        }

        if ($this->ownerId !== null) {
            $this->subQuery->andWhere(Db::parseParam("$alias.ownerId", $this->ownerId));
        }

        return parent::afterPrepare();
    }
}
