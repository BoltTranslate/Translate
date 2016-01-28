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
        
        $this->app['menu'] = $this->app->share(
            function ($app) {
                $builder = new Menu\LocalizedMenuBuilder($app);
                return $builder;
            }
        );

        $this->app['config']->getFields()->addField(new Field\LocaleField());
        $this->app['twig.loader.filesystem']->addPath(__DIR__.'/assets/views');

        $this->app->mount(
            $this->app['config']->get('general/branding/path').'/async/translate',
            new Controller\AsyncController($this->app)
        );

        $this->app->before(array($this, 'beforeCallback'));

        // Locale switcher for frontend
        $this->addTwigFunction('localeswitcher', 'renderLocaleSwitcher');
        
        $this->addTwigFunction('get_slug_from_locale', 'getSlugFromLocale');

        $this->app['dispatcher']->addListener(StorageEvents::PRE_SAVE, array($this, 'preSaveCallback'));
        $this->app['dispatcher']->addListener(StorageEvents::POST_DELETE, array($this, 'postDeleteCallback'));

        if ($this->app['config']->getWhichEnd() == 'backend') {
            $this->checkDb();
        }
    }
    
    /**
     * beforeCallback.
     *
     * This callback adds the CSS/JS for the localeswitcher on the backend
     * and checks that we are on a valid locale when on the frontend
     */
    public function beforeCallback(Request $request)
    {
        $routeParams = $request->get('_route_params');
        if ($this->app['config']->getWhichEnd() == 'backend') {
            if (array_key_exists('contenttypeslug', $routeParams)) {
                $this->addCss('assets/css/field_locale.css');
                if(!empty($routeParams['id'])) {
                    $this->addJavascript('assets/js/field_locale.js', array('late' => true));
                }
            }
        } else {
            if (isset($routeParams['_locale'])) {
                $locales = $this->app['config']->get('general/locales');
                foreach($locales as $isolocale => $locale) {
                    if ($locale['slug'] == $routeParams['_locale']) {
                        $foundLocale = $isolocale;
                    }
                }
                if (isset($foundLocale)) {
                    setlocale(LC_ALL, $foundLocale);
                    $this->app['config']->set('general/locale', $foundLocale);
                } else {
                    $routeParams['_locale'] = reset($locales)['slug'];
                    return $this->app->redirect(Lib::path($request->get('_route'), $routeParams));
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
        $subject = $event->getSubject();

        $query = 'DELETE FROM '.$prefix.'translation where content_type = ? and content_type_id = ?';
        $stmt = $this->app['db']->prepare($query);
        $stmt->bindValue(1, $event->getArgument('contenttype'));
        $stmt->bindValue(2, $subject['id']);
        $stmt->execute();
    }

    /**
     * localeHydrate
     *
     * This method calls localeHydrateRecord with each record
     * in a collection with the locale for the current route
     */
    public function localeHydrate($content = "")
    {
        $locales = $this->app['config']->get('general/locales');
        if($locales){
            $defaultLocaleSlug = reset($locales)['slug'];
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
    
    /**
     * localeHydrateRecord
     *
     * This method replaces values with their translated counterparts
     * in a single record.
     */
    private function localeHydrateRecord($record, $locale){
        $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');
        
        $query = 'SELECT field, value FROM '.$prefix.'translation where locale = ? and content_type = ? and content_type_id = ?';
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

    /**
     * getSlugFromLocale.
     *
     * Twig function to get the slug for a record in a different locale
     */
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
