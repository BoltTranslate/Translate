<?php

namespace Bolt\Extension\Animal\Translate\Storage\Schema;


use Doctrine\DBAL\Schema\Schema;
use Silex\Application;

class Manager extends \Bolt\Storage\Database\Schema\Manager
{
    /** @var \Silex\Application */
    private $app;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->app = $app;
    }

    public function getSchemaTables()
    {
        if ($this->schemaTables !== null) {
            return $this->schemaTables;
        }

        /** @var array $builder */
        $builder = $this->app['schema.builder'];

        /** @deprecated Deprecated since 3.0, to be removed in 4.0. */
        $builder['extensions']->addPrefix($this->app['schema.prefix']);

        $schema = new Schema();

        /** @var \Bolt\Extension\Animal\Translate\Config\Config $translateConfig */
        $translateConfig = $this->app['translate.config'];

        $this->config->set('locales', $translateConfig->getLocales());

        $tables = array_merge(
            $builder['base']->getSchemaTables($schema),
            $builder['content']->getSchemaTables($schema, $this->config),
            $builder['extensions']->getSchemaTables($schema),
            $this->app['trans.schema.builder']->getSchemaTables($schema, $this->config)
        );

        $this->schema = $schema;

        return $this->schemaTables = $tables;
    }
}