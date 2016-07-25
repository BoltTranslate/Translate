<?php

namespace Bolt\Extension\Animal\Translate\Storage;

use Bolt\Storage\Database\Schema\Table\ContentType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class ContentTypeTable extends ContentType
{
    private $config;

    /**
     * ContentTypeTable constructor
     *
     * @param AbstractPlatform $platform
     * @param string           $tablePrefix
     * @param array            $config
     */
    public function __construct(AbstractPlatform $platform, $tablePrefix, array $config)
    {
        parent::__construct($platform, $tablePrefix);

        $this->config = $config;
    }

    /**
     *  Add custom fields to the content table schema
     */
    protected function addColumns()
    {
        parent::addColumns();
        foreach ($this->config['locales'] as $locale) {
            $this->table->addColumn($locale['slug'] . '_slug', 'string', ['length' => 256, 'default' => '']);
            $this->table->addColumn($locale['slug'] . '_data', 'text', ['notnull' => false]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function addIndexes()
    {
        parent::addIndexes();
        foreach ($this->config['locales'] as $locale) {
            $this->table->addIndex([$locale['slug'] . '_slug']);
        }
    }
}
