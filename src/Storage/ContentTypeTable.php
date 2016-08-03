<?php

namespace Bolt\Extension\Animal\Translate\Storage;

use Bolt\Extension\Animal\Translate\Config;
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
     * @param Config\Config    $config
     */
    public function __construct(AbstractPlatform $platform, $tablePrefix, Config\Config $config)
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
        /** @var Config\Locale $locale */
        foreach ($this->config->getLocales() as $locale) {
            $this->table->addColumn($locale->getSlug() . '_slug', 'string', ['length'  => 191, 'default' => '']);
            $this->table->addColumn($locale->getSlug() . '_data', 'text',   ['notnull' => false]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function addIndexes()
    {
        parent::addIndexes();
        foreach ($this->config->getLocales() as $locale) {
            $this->table->addIndex([$locale->getSlug() . '_slug']);
        }
    }
}
