<?php

namespace Bolt\Extension\Animal\Translate;

use Bolt\BaseExtension;
use Silex\Application as BoltApplication;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\HttpFoundation\Request;
use Bolt\Events\StorageEvents;
use Bolt\Events\StorageEvent;
use Bolt\Events\HydrationEvent;
use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Controller\Zone;

class Extension extends BaseExtension
{
    /** @var string */
    protected $defaultLocale;

    public function initialize()
    {
        $this->app['config']->getFields()->addField(new Field\LocaleField());
        $this->app['twig.loader.filesystem']->addPath(__DIR__.'/assets/views');

        $this->app->mount(
            $this->app['config']->get('general/branding/path').'/async/translate',
            new Controller\AsyncController($this->app, $this->config)
        );
        
        $this->app->before(array($this, 'beforeCallback'));
        //$this->app->before(array($this, 'beforeEarlyCallback'), BoltApplication::EARLY_EVENT);

        // Locale switcher for frontend
        $this->addTwigFunction('localeswitcher', 'renderLocaleSwitcher');

        $this->app['dispatcher']->addListener(StorageEvents::PRE_SAVE, array($this, 'preSaveCallback'));
        $this->app['dispatcher']->addListener(StorageEvents::POST_DELETE, array($this, 'postDeleteCallback'));
        $this->app['dispatcher']->addListener(StorageEvents::PRE_HYDRATE, array($this, 'preHydrateCallback'));

        if ($this->app['config']->getWhichEnd() == 'backend') {
            $this->checkDb();
        }
    }

    public function beforeCallback(Request $request)
    {
        if (Zone::isBackend($request)) {
            $this->addCss(new Stylesheet('assets/css/field_locale.css'));
            
            $localeJs = new JavaScript('assets/js/field_locale.js');
            $localeJs
                ->setLate(true)
                ->setPriority(99);
            
            $this->addJavascript($localeJs);
        }

        // $routeParams = $request->get('_route_params');
        // $_locale = $routeParams['_locale'];
    }

    /**
     * preSaveCallback.
     *
     * This callback is used to store the content of translated fields
     * on content type update. It is called by the event dispatcher.
     */
    public function preSaveCallback(StorageEvent $event)
    {
        $default_locale = $this->app['config']->get('general/locale', 'en_GB');
        $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');

        $content_type = $event->getContentType();
        $content_type_id = $event->getId();
        $content = $event->getContent()->getValues();

        $content_type_config = $this->app['config']->get('contenttypes/'.$content_type);
        $locale_field = null;
        foreach ($content_type_config['fields'] as $name => $field) {
            if ($field['type'] == 'locale') {
                $locale_field = $name;
                break;
            }
        }

        if (!$content_type_id || !$locale_field || $content[$locale_field] === $default_locale) {
            return;
        }

        $translatable_fields = $this->getTranslatableFields($content_type_config['fields']);
        $query = 'SELECT * FROM '.$prefix.$content_type.' WHERE id = :content_type_id';
        $default_content = $this->app['db']->fetchAssoc($query, array(
            ':content_type_id' => $content_type_id,
        ));

        foreach ($translatable_fields as $translatable_field) {
            // Create/update translation entries
            $query = 'REPLACE INTO '.$prefix.'translation (locale, field, content_type_id, content_type, value) VALUES (?, ?, ?, ?, ?)';
            $this->app['db']->executeQuery($query, array(
                $content[$locale_field],
                $translatable_field,
                $content_type_id,
                $content_type,
                $content[$translatable_field],
            ));

            // Reset values to english
            $content[$translatable_field] = $default_content[$translatable_field];
        }

        $content[$locale_field] = $default_locale;
        $event->getContent()->setValues($content);
    }

    /**
     * postDeleteCallback.
     *
     * This callback takes care of deleting all translations,
     * associated with the deleted content.
     */
    public function postDeleteCallback(StorageEvent $event)
    {
        $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');
        $translation_table_name = $prefix.'translation';

        $subject = $event->getSubject();

        //var_dump($event->getId()); 
        //$entity = $event->getArgument('entity');
        //exit;

        // $app['db']->delete($prefix.$translation_table_name,
        //     array(
        //         'content_type' => 1,
        //         'content_type_id' => $subject['id']
        //     )
        // );
    }

    /**
     * preHydrateCallback.
     *
     * This callback is used to load translated content into the object.
     * It is called by the event dispatcher.
     */
    public function preHydrateCallback(HydrationEvent $event)
    {
        $subject = $event->getSubject();
        $entity = $event->getArgument('entity');
        $repository = $event->getArgument('repository');

        $_locale = $this->app['request']->get('_locale');
        //$content_type = $request->query->get('content_type');
        $content_type_id = $subject['id'];

        // if _locale == default locale
            // return

        // load translated fields
        // replace object content


        //echo json_encode($content_type_id);
        //echo '<br><br>';
        //echo json_encode($repository);
    }

    /**
     * renderLocaleSwitcher.
     *
     * Twig function to render a locale switcher in frontend
     */
    public function renderLocaleSwitcher($template = null)
    {
        if($template === null) {
            $template = '/twig/_localeswitcher.twig';
        }

        return $this->app['twig']->render($template, array(
            'locales' => $this->config['locales']
        ));
    }

    private function checkDb()
    {
        $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');
        $translation_table_name = $prefix.'translation';

        $this->app['integritychecker']->registerExtensionTable(
            function (Schema $schema) use ($translation_table_name) {
                $table = $schema->createTable($translation_table_name);
                $table->addColumn('locale',          'string', array('length' => 5,  'default' => ''));
                $table->addColumn('content_type',    'string', array('length' => 32, 'default' => ''));
                $table->addColumn('content_type_id', 'integer');
                $table->addColumn('field',           'string', array('length' => 32, 'default' => ''));
                $table->addColumn('value',           'text');
                $table->setPrimaryKey(array('locale', 'field', 'content_type_id'));

                return $table;
            }
        );
    }

    /**
     * Set the defaults for configuration parameters.
     *
     * @return array
     */
    protected function getDefaultConfig()
    {
        return array(
        );
    }

    public function getName()
    {
        return 'Translate';
    }

    private function getTranslatableFields($fields)
    {
        $translatable = array();

        foreach ($fields as $name => $field) {
            if (isset($field['isTranslatable']) && $field['isTranslatable'] === true) {
                $translatable[] = $name;
            }
        }

        return $translatable;
    }
}
