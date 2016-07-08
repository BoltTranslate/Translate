<?php

namespace Bolt\Extension\Animal\Translate;

use Bolt\Events\HydrationEvent;
use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;

use Bolt\Extension\SimpleExtension;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Silex\Application;

/**
 * Translate extension class.
 *
 * @author Your Name <you@example.com>
 */
class TranslateExtension extends SimpleExtension
{
    /**
     * @inheritdoc
     *
     * @param Application $app
     */
    protected function registerServices(Application $app)
    {
        $this->app = $app;
        $this->config = $this->getConfig();
        $this->registerContentTableSchema($app);
        $this->registerLegacyStorage($app);
        
        $app['translate'] = $app->share(
            function () {
                return $this;
            }
        );
        $app['translate.config'] = $app->share(
            function () {
                return $this->config;
            }
        );
    }
    public function getServiceProviders()
    {
        return [
            $this,
            new Provider\FieldProvider()
        ];
    }

    protected function registerTwigPaths()
    {
        return [
            'templates' => ['position' => 'prepend', 'namespace' => 'bolt']
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $parentEvents = parent::getSubscribedEvents();
        $localEvents = [
            StorageEvents::PRE_HYDRATE => [
                ['preHydrate', 0],
            ],
            StorageEvents::PRE_SAVE => [
                ['preSave', 0],
            ],
        ];

        return $parentEvents + $localEvents;
    }
    
    /**
     * AccessControlEvents::LOGIN_SUCCESS event callback.
     *
     * @param AccessControlEvent $event
     */
    public function preSave(StorageEvent $event)
    {
        $contenttype = $this->app['config']->get('contenttypes/'.$event->getContentType());
        $translateableFields = $this->getTranslatableFields($contenttype['fields']);
        if(empty($translateableFields)){
            return;
        }
        $record = $event->getContent();
        $values = $record->serialize();
        
        if($values['locale'] == array_keys($this->config['locales'])[0]){
            return;
        }

        $localeSlug = $this->config['locales'][$values['locale']]['slug'];
        
        $localeValues = [];
        
        $record->set($localeSlug.'_slug', $values['slug']);
        $defaultContent = $this->app['query']->getContent($event->getContentType(), ['id' => $values['id'], 'returnsingle' => true])->serialize();
        foreach ($translateableFields as $value) {
            $localeValues[$value] = $values[$value];
            $record->set($value, $defaultContent[$value]);
        }
        $localeJson = json_encode($localeValues);
        $record->set($localeSlug.'_data', $localeJson);

    }

    /**
     * StorageEvents::PRE_HYDRATE event callback.
     *
     * @param HydrationEvent $event
     */
    public function preHydrate(HydrationEvent $event)
    {
        $entity = $event->getArgument('entity');
        $subject = $event->getSubject();

        if(get_class($entity) !== "Bolt\Storage\Entity\Content"){
            return;
        }

        $localeSlug = $this->app['request']->get('_locale');

        if(isset($subject[$localeSlug.'_data'])){
            $localeData = json_decode($subject[$localeSlug.'_data']);
            foreach ($localeData as $key => $value) {
                $subject[$key] = $value;
            }
        }
    }

    /**
     * Register own table schema class for the content tables
     * to add all custom fields
     *
     * @param Application $app
     */
    private function registerContentTableSchema(Application $app)
    {
        $config = $this->getConfig();
        $app['schema.content_tables'] = $app->extend(
            'schema.content_tables',
            function ($contentTables) use ($app, $config) {

                $platform = $app['db']->getDatabasePlatform();
                $prefix = $app['schema.prefix'];
                $contentTypes = $app['config']->get('contenttypes');

                foreach (array_keys($contentTypes) as $contentType) {
                    $contentTables[$contentType] = $app->share(function () use ($platform, $prefix, $config) {
                        return new Storage\ContentTypeTable($platform, $prefix, $config);
                    });
                }
                return $contentTables;
            }
        );
    }

    /**
     * Register own table schema class for the content tables
     * to add all custom fields
     *
     * @param Application $app
     */
    private function registerLegacyStorage(Application $app)
    {
        $config = $this->getConfig();
        $this->app['storage.legacy'] = $app->extend(
            'storage.legacy',
            function ($storage) use ($app) {
                return new Storage\Legacy($app);
            }
        );
    }

    private function getTranslatableFields($fields)
    {
        $translatable = [];
        foreach ($fields as $name => $field) {
            if (isset($field['isTranslatable'])  && $field['isTranslatable'] === true && $field['type'] === 'templateselect') {
                $translatable[] = 'templatefields';
            }elseif (isset($field['isTranslatable']) && $field['isTranslatable'] === true) {
                $translatable[] = $name;
            }
        }
        return $translatable;
    }
}
