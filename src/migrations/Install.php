<?php
/**
 * Core SEO/GEO plugin for Craft CMS
 *
 * A no fluff SEO/GEO solution for Craft CMS websites.
 *
 * @link      https://digitalastronaut.be
 * @copyright Copyright (c) 2026 Digitalastronaut
 */

namespace digitalastronaut\craftcoreseogeo\migrations;

use craft\db\Migration;
use craft\db\Table as CraftTable;

use digitalastronaut\craftcoreseogeo\db\Table;
use digitalastronaut\craftcoreseogeo\records\Redirect;
use digitalastronaut\craftcoreseogeo\records\TrackingScript;

/**
 * Class Install
 *
 * @author      Digitalastronaut
 * @package     CoreSeoGeo
 * @since       v1.0.0
 */
class Install extends Migration {
    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function safeUp(): bool {
        $this->createTable(Table::STRUCTUREDDATAMAPPINGS, [
            'id' => $this->primaryKey(),
            'sectionHandle' => $this->string()->notNull()->defaultValue(''),
            'label' => $this->string(),
            'type' => $this->string()->notNull(),
            'properties' => $this->json(),
            'previewElementId' => $this->integer(),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, Table::STRUCTUREDDATAMAPPINGS, ['sectionHandle']);
        $this->addForeignKey(null, Table::STRUCTUREDDATAMAPPINGS, ['previewElementId'], CraftTable::ELEMENTS, ['id'], 'SET NULL', null);

        $this->createTable(Table::STRUCTUREDDATA, [
            'id' => $this->integer()->notNull(),
            'mappingId' => $this->integer(),
            'ownerId' => $this->integer(),
            'siteId' => $this->integer()->notNull(),
            'type' => $this->string()->notNull(),
            'properties' => $this->json()->notNull(),
            'sourceData' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id, siteId)',
        ]);

        $this->addForeignKey(null, Table::STRUCTUREDDATA, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::STRUCTUREDDATA, ['mappingId'], Table::STRUCTUREDDATAMAPPINGS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::STRUCTUREDDATA, ['ownerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::STRUCTUREDDATA, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::STRUCTUREDDATA, ['mappingId', 'ownerId', 'siteId'], true);

        $this->createTable(Table::SEOSCORE, [
            'id' => $this->primaryKey(),
            'elementId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'fieldId' => $this->integer()->notNull(),
            'score' => $this->smallInteger(),
            'pending' => $this->boolean()->notNull()->defaultValue(false),
            'metaTitle' => $this->string(500),
            'metaDescription' => $this->string(500),
            'checks' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::SEOSCORE, ['elementId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::SEOSCORE, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::SEOSCORE, ['fieldId'], CraftTable::FIELDS, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::SEOSCORE, ['elementId', 'siteId', 'fieldId'], true);
        $this->createIndex(null, Table::SEOSCORE, ['siteId', 'metaTitle']);
        $this->createIndex(null, Table::SEOSCORE, ['siteId', 'metaDescription']);

        $this->createTable(Table::SEODATA, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'sectionHandle' => $this->string()->notNull()->defaultValue(''),
            'sectionLabel' => $this->string(),
            'titleConfig' => $this->json(),
            'descriptionConfig' => $this->json(),
            'robots' => $this->json(),
            'canonicalConfig' => $this->json(),
            'imageId' => $this->integer(),
            'sitemapEnabled' => $this->boolean()->notNull()->defaultValue(true),
            'llmsTxtTitle' => $this->string(),
            'llmsTxtSummary' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::SEODATA, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::SEODATA, ['imageId'], CraftTable::ELEMENTS, ['id'], 'SET NULL', null);
        $this->createIndex(null, Table::SEODATA, ['siteId', 'sectionHandle'], true);

        $this->createTable(Table::TRACKINGSCRIPTS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'scriptTemplate' => $this->mediumText(),
            'scriptLocation' => $this->string()->notNull()->defaultValue(TrackingScript::LOCATION_HEAD_END),
            'bodyScriptTemplate' => $this->mediumText(),
            'bodyScriptLocation' => $this->string()->notNull()->defaultValue(TrackingScript::LOCATION_BODY_START),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::REDIRECTS, [
            'id' => $this->primaryKey(),
            'matchType' => $this->string(10)->notNull()->defaultValue(Redirect::MATCH_TYPE_EXACT),
            'sourceUri' => $this->string(255)->notNull(),
            'destinationUri' => $this->string(255)->null(),
            'statusCode' => $this->smallInteger()->notNull()->defaultValue(Redirect::STATUS_CODE_MOVED_PERMANENTLY),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'sourceElementId' => $this->integer()->null(),
            'hitCount' => $this->integer()->notNull()->defaultValue(0),
            'dateLastHit' => $this->dateTime()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::REDIRECTS, ['sourceElementId'], CraftTable::ELEMENTS, ['id'], 'SET NULL', null);
        $this->createIndex(null, Table::REDIRECTS, ['sourceUri']);

        $this->createTable(Table::REDIRECTSITES, [
            'id' => $this->primaryKey(),
            'redirectId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::REDIRECTSITES, ['redirectId'], Table::REDIRECTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::REDIRECTSITES, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::REDIRECTSITES, ['redirectId', 'siteId'], true);

        $this->createTable(Table::NOTFOUNDLOG, [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer()->notNull(),
            'uri' => $this->string(255)->notNull(),
            'referrer' => $this->string(2000)->null(),
            'hitCount' => $this->integer()->notNull()->defaultValue(1),
            'resolved' => $this->boolean()->notNull()->defaultValue(false),
            'dateLastHit' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::NOTFOUNDLOG, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::NOTFOUNDLOG, ['siteId', 'uri'], true);

        $this->createTable(Table::METRICS, [
            'id' => $this->primaryKey(),
            'type' => $this->string()->notNull(),
            'entityId' => $this->integer()->notNull()->defaultValue(0),
            'siteId' => $this->integer()->notNull(),
            'timestamp' => $this->dateTime()->notNull(),
            'hitCount' => $this->integer()->notNull()->defaultValue(1),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, Table::METRICS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::METRICS, ['type', 'entityId', 'siteId', 'timestamp'], true);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @author      Digitalastronaut
     * @since       v1.0.0
     */
    public function safeDown(): bool {
        $this->dropTableIfExists(Table::STRUCTUREDDATA);
        $this->dropTableIfExists(Table::SEOSCORE);
        $this->dropTableIfExists(Table::SEODATA);
        $this->dropTableIfExists(Table::STRUCTUREDDATAMAPPINGS);
        $this->dropTableIfExists(Table::TRACKINGSCRIPTS);
        $this->dropTableIfExists(Table::REDIRECTSITES);
        $this->dropTableIfExists(Table::REDIRECTS);
        $this->dropTableIfExists(Table::NOTFOUNDLOG);
        $this->dropTableIfExists(Table::METRICS);

        return true;
    }
}
