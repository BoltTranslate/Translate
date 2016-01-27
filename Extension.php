<?php

namespace Bolt\Extension\Animal\Translate;

use Bolt\BaseExtension;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\HttpFoundation\Request;
use Bolt\Events\StorageEvents;
use Bolt\Events\StorageEvent;
use Bolt\Library as Lib;

class Extension extends BaseExtension
{
    /** @var string */
    protected $defaultLocale;
    
    private $serializedFieldTypes = array(
            'geolocation',
            'imagelist',
            'image',
            'file',
            'filelist',
            'video',
            'select',
            'templateselect',
            'checkbox'
        );
    

    public function initialize()
    {
        
        $this->config = ['locales' => $this->app['config']->get('general/locales')];

        $this->app['config']->getFields()->addField(new Field\LocaleField());
        $this->app['twig.loader.filesystem']->addPath(__DIR__.'/assets/views');

        $this->app->mount(
            $this->app['config']->get('general/branding/path').'/async/translate',
            new Controller\AsyncController($this->app, ['locales' => $this->app['config']->get('general/locales')])
        );
        
        $this->app->before(array($this, 'beforeCallback'));
        //$this->app->before(array($this, 'beforeEarlyCallback'), BoltApplication::EARLY_EVENT);

        // Locale switcher for frontend
        $this->addTwigFunction('localeswitcher', 'renderLocaleSwitcher');
        
        $this->addTwigFunction('get_slug_from_locale', 'getSlugFromLocale');

        $this->app['dispatcher']->addListener(StorageEvents::PRE_SAVE, array($this, 'preSaveCallback'));
        $this->app['dispatcher']->addListener(StorageEvents::POST_DELETE, array($this, 'postDeleteCallback'));
        //$this->app['dispatcher']->addListener(StorageEvents::PRE_HYDRATE, array($this, 'preHydrateCallback'));

        if ($this->app['config']->getWhichEnd() == 'backend') {
            $this->checkDb();
        }
    }

    public function beforeCallback(Request $request)
    {
        if ($this->app['config']->getWhichEnd() == 'backend') {
            $routeParams = $request->get('_route_params');
            if(array_key_exists('contenttypeslug', $routeParams)) {
                $this->addCss('assets/css/field_locale.css');
                
                if(!empty($routeParams['id'])) {
                    $this->addJavascript('assets/js/field_locale.js', array('late' => true));
                }
            }
        }
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
            
            $fieldtype = $content_type_config['fields'][$translatable_field]['type'];
            
            if(is_a($content[$translatable_field], 'Bolt\\Content')){
                $content[$translatable_field] = json_encode($content[$translatable_field]->getValues(true, true));
                
            }
            if(in_array($fieldtype, $this->serializedFieldTypes) && !is_string($content[$translatable_field])){
                $content[$translatable_field] = json_encode($content[$translatable_field]);
            }
            $content_type_config['fields'][$translatable_field];
            // Create/update translation entries
            $query = 'REPLACE INTO '.$prefix.'translation (locale, field, content_type_id, content_type, value) VALUES (?, ?, ?, ?, ?)';
            $this->app['db']->executeQuery($query, array(
                $content[$locale_field],
                $translatable_field,
                $content_type_id,
                $content_type,
                (string)$content[$translatable_field],
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

    public function localeHydrate($content = "")
    {
        $locales = $this->app['config']->get('general/locales');
        if($locales){
            $defaultLocaleSlug = $locales[0]['slug'];
            $matchedLocales = array_filter(
                $locales,
                function ($e) {
                    return $e['slug'] == $this->app['request']->get('_locale');
                }
            );
                
            if($this->app['request']->get('_locale') != $defaultLocaleSlug || !empty($matchedLocales)){
                
                
                $locale = key($matchedLocales);
                
                if(is_a($content, "Bolt\Content")){
                    $this->localeHydrateRecord($content, $locale);
                }elseif(is_array($content)){
                    foreach ($content as &$record) {
                        $record = $this->localeHydrateRecord($record, $locale);
                    }
        
                }
            }
        }
        return $content;
    }
    
    public function getSlugFromLocale($content, $locale)
    {
        if(is_a($content, "Bolt\Content")){
            $query = "select value from bolt_translation where field = 'slug' and locale = ? and content_type = ? and content_type_id = ? ";
            $stmt = $this->app['db']->prepare($query);
            $stmt->bindValue(1, $locale);
            $stmt->bindValue(2, $content->contenttype['slug']);
            $stmt->bindValue(3, $content->id);
            $stmt->execute();
            $slug =  $stmt->fetch();
            if(!empty($slug)){
                return $slug['value'];
            }
            return $content->delocalizedValues['slug'];
        }
        return false;
    }
    
    public function matchSlug($contenttypeslug, $slug = '')
    {
        $locales = $this->app['config']->get('general/locales');
        $defaultLocaleSlug = $locales[0]['slug'];
        $matchedLocales = array_filter(
            $locales,
            function ($e) {
                return $e['slug'] == $this->app['request']->get('_locale');
            }
        );
        
        $locale = key($matchedLocales);
        $query = "select content_type_id from bolt_translation where field = 'slug' and locale = ? and content_type = ? and value = ? ";
        $stmt = $this->app['db']->prepare($query);
        $stmt->bindValue(1, $locale);
        $stmt->bindValue(2, $contenttypeslug);
        $stmt->bindValue(3, $slug);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    private function localeHydrateRecord($record, $locale){
        $query = "select field, value from bolt_translation where locale = ? and content_type = ? and content_type_id = ?";
        $stmt = $this->app['db']->prepare($query);
        $stmt->bindValue(1, $locale);
        $stmt->bindValue(2, $record->contenttype['slug']);
        $stmt->bindValue(3, $record->id);
        $stmt->execute();
        $record->delocalizedValues = [];
        while ($row = $stmt->fetch()) {
            $record->delocalizedValues[$row['field']] = $record->values[$row['field']];
            $record->values[$row['field']] = $row['value'];
        }
        
        return $record;
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
            'locales' => $this->app['config']->get('general/locales')
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
            if (isset($field['isTranslatable'])  && $field['isTranslatable'] === true && $field['type'] === 'templateselect') {
                $translatable[] = 'templatefields';
            }elseif (isset($field['isTranslatable']) && $field['isTranslatable'] === true) {
                $translatable[] = $name;
            }
        }

        return $translatable;
    }
}
