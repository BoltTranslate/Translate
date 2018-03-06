<?php

namespace Bolt\Extension\Animal\Translate\Storage\Schema\Builder;

use Bolt\Config;
use Bolt\Storage\Database\Schema\Builder\ContentTables;
use Bolt\Storage\Field\Manager as FieldManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Bolt\Extension\Animal\Translate\Storage\Schema\Tables\ContentTypeTranslation;

class ContentTranslationTables extends ContentTables
{
    public function getSchemaTables(Schema $schema, Config $config)
    {
        /** @var $fieldManager FieldManager */
        $fieldManager = $config->getFields();
        $contentTypes = $this->getNormalisedContentTypes($config);
        $tables = [];

        foreach ($this->tables->keys() as $name) {
            $contentType = $contentTypes[$name];
            /** @var ContentTypeTranslation $table */
            $table = $this->tables[$name];

            foreach ($config->get('locales') as $locale) {
                $tableLocaleName = $name . '_' . $locale['slug'];
                $tables[$tableLocaleName] = $table->buildTable($schema, $tableLocaleName, $this->charset, $this->collate);

                if (isset($contentType['fields']) && is_array($contentType['fields'])) {
                    $this->addContentTypeTableColumns($this->tables[$name], $tables[$tableLocaleName], $contentType['fields'], $fieldManager);
                }
            }
        }

        return $this->tableSchemas = $tables;
    }

    private function getNormalisedContentTypes(Config $config)
    {
        $normalised = [];
        $contentTypes = $config->get('contenttypes');
        foreach ($contentTypes as $contentType) {
            $normalised[$contentType['tablename']] = $contentType;
        }

        return $normalised;
    }

    private function addContentTypeTableColumns(ContentTypeTranslation $tableObj, Table $table, array $fields, FieldManager $fieldManager)
    {
        // Check if all the fields are present in the DB.
        foreach ($fields as $fieldName => $values) {
            /** @var \Doctrine\DBAL\Platforms\Keywords\KeywordList $reservedList */
            $reservedList = $this->connection->getDatabasePlatform()->getReservedKeywordsList();
            if ($reservedList->isKeyword($fieldName)) {
                $error = sprintf(
                    "You're using '%s' as a field name, but that is a reserved word in %s. Please fix it, and refresh this page.",
                    $fieldName,
                    $this->connection->getDatabasePlatform()->getName()
                );
                $this->flashLogger->error($error);
                continue;
            }

            $this->addContentTypeTableColumn($tableObj, $table, $fieldName, $values, $fieldManager);
        }
    }

    private function addContentTypeTableColumn(ContentTypeTranslation $tableObj, Table $table, $fieldName, array $values, FieldManager $fieldManager)
    {
        if ($tableObj->isKnownType($values['type'])) {
            // Use loose comparison on true as 'true' in YAML is a string
            $addIndex = isset($values['index']) && (bool) $values['index'] === true;
            // Add the ContentType's specific fields
            $tableObj->addCustomFields($fieldName, $this->getContentTypeTableColumnType($values), $addIndex);
        } elseif ($handler = $fieldManager->getDatabaseField($values['type'])) {
            $type = ($handler->getStorageType() instanceof Type) ? $handler->getStorageType()->getName() : $handler->getStorageType();
            /** @var $handler \Bolt\Storage\Field\FieldInterface */
            $table->addColumn($fieldName, $type, $handler->getStorageOptions());
        }
    }

    private function getContentTypeTableColumnType(array $values)
    {
        // Multi-value selects are stored as JSON arrays
        if (isset($values['type']) && $values['type'] === 'select' && isset($values['multiple']) && $values['multiple'] === true) {
            return 'selectmultiple';
        }

        return $values['type'];
    }
}