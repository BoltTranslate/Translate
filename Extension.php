<?php

namespace Bolt\Extension\Animal\Translate;

use Bolt\BaseExtension;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\HttpFoundation\Request;
use Bolt\Events\StorageEvents;
use Bolt\Events\StorageEvent;
use Bolt\Library as Lib;

use Bolt\Extension\Bolt\Labels;

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

        $locales = $this->app['config']->get('general/locales');

        $this->app['htmlsnippets'] = true;

        if($locales){
            $this->app['config']->getFields()->addField(new Field\LocaleField());
            $this->app['twig.loader.filesystem']->addPath(__DIR__.'/assets/views');

            $this->app->mount(
                $this->app['config']->get('general/branding/path').'/async/translate',
                new Controller\AsyncController($this->app)
            );

            $this->app->before(array($this, 'beforeCallback'));

            // Locale switcher for frontend
            $this->addTwigFunction('localeswitcher', 'renderLocaleSwitcher');

            // Twig function to get the translated slug for a specific record, used in localeswitcher
            $this->addTwigFunction('get_slug_from_locale', 'getSlugFromLocale');

            // Twig function to translate/add labels to a forms placeholder and label attributes
            $this->addTwigFunction('translate_form', 'translateForm');

            $this->app['dispatcher']->addListener(StorageEvents::PRE_SAVE, array($this, 'preSaveCallback'));
            $this->app['dispatcher']->addListener(StorageEvents::POST_DELETE, array($this, 'postDeleteCallback'));
    
            if ($this->app['config']->getWhichEnd() == 'backend') {
                $this->checkDb();
            }
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
                $this->app['menu'] = $this->app->share(
                    function ($app) {
                        $builder = new Menu\LocalizedMenuBuilder($app);
                        return $builder;
                    }
                );
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

            if($fieldtype === "video"){
                $content[$translatable_field]['html'] = (string)$content[$translatable_field]['html'];
                $content[$translatable_field]['responsive'] = (string)$content[$translatable_field]['responsive'];
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
     * translateForm.
     *
     * Badly hacked way to replace labels and placeholders in forms.
     */
    public function translateForm($form = null)
    {
        foreach($form->children as $key => $value){
            if($value->vars['label']){
                $value->vars['label'] = $this->app['twig']->render('/twig/trans.twig', ['value' => $value->vars['label'] ]);
            }
            if($value->vars['attr']['placeholder']){
                $value->vars['attr']['placeholder'] = $this->app['twig']->render('/twig/trans.twig', ['value' => $value->vars['attr']['placeholder'] ]);
            }
        }
        return $form;
    }

    /**
     * getSlugFromLocale.
     *
     * Twig function to get the slug for a record in a different locale
     */
    public function getSlugFromLocale($content, $locale)
    {
        if(!isset($content->contenttype['slug'])){
            return false;
        }
        $query = "select value from bolt_translation where field = 'slug' and locale = ? and content_type = ? and content_type_id = ? ";
        $stmt = $this->app['db']->prepare($query);
        $stmt->bindValue(1, $locale);
        $stmt->bindValue(2, $content->contenttype['slug']);
        $stmt->bindValue(3, $content->id);
        $stmt->execute();
        $slug =  $stmt->fetch();
        if(isset($slug['value'])){
            return $slug['value'];
        }
        if(isset($content->delocalizedValues['slug'])){
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
