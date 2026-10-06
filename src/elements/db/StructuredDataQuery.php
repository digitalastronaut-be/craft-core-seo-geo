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

    /**
     * @param string|string[]|null $value
     * @return static
     */
    public function type(array|string|null $value): static {
        $this->type = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool {
        $this->joinElementTable(Table::STRUCTUREDDATA);

        $this->query->addSelect([
            'coreseogeo_structureddata.type',
            'coreseogeo_structureddata.properties',
        ]);

        if ($this->type) {
            $this->subQuery->andWhere(Db::parseParam('coreseogeo_structureddata.type', $this->type));
        }

        return parent::beforePrepare();
    }
}
