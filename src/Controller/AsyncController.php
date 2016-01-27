<?php

namespace Bolt\Extension\Animal\Translate\Controller;

use Symfony\Component\HttpFoundation\Request;
use Silex\Application;
use Silex\ControllerProviderInterface;

class AsyncController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $router = $app['controllers_factory'];
        $router->match('/get', [$this, 'getTranslationAction'])
            ->method('GET');

        return $router;
    }

    public function getTranslationAction(Application $app, Request $request)
    {
        $default_locale = $app['config']->get('general/locale', 'en_GB');
        $prefix = $app['config']->get('general/database/prefix', 'bolt_');
        $translation_table_name = $prefix.'translation';

        $locale = $request->query->get('locale');
        $content_type = $request->query->get('content_type');
        $content_type_id = $request->query->get('content_type_id');

        $content_type_config = $app['config']->get('contenttypes/'.$content_type);
        $translatable_fields = $this->getTranslatableFields($content_type_config['fields']);

        $response = array();

        // Return default record if default locale
        if($locale === $default_locale) {
            $query = 'SELECT * FROM '.$prefix.$content_type.' WHERE id = :content_type_id';
            $default_content = $app['db']->fetchAssoc($query, array(
                ':content_type_id' => $content_type_id
            ));

            foreach($translatable_fields as $translatable_field) {
                $element = new \stdClass;
                $element->field = $translatable_field;
                $element->value = $default_content[$translatable_field];

                $response[] = $element;
            }

            return $app->json($response);
        }

        $query = 'SELECT field, value FROM '.$translation_table_name.' WHERE locale = :locale AND content_type = :content_type AND content_type_id = :content_type_id';
        $translated_content = $app['db']->fetchAll($query, array(
            ':locale' => $locale,
            ':content_type' => $content_type,
            ':content_type_id' => $content_type_id
        ));

        foreach($translatable_fields as $translatable_field) {
            $element = new \stdClass;
            $element->field = $translatable_field;
            $element->value = '';

            foreach($translated_content as $content) {
                if($content['field'] === $translatable_field) {
                    $element->value = $content['value'];
                }
            }

            $response[] = $element;
        }

        return $app->json($response);
    }

    private function getTranslatableFields($fields) {
        $translatable = array();

        foreach($fields as $name => $field) {
            if (isset($field['isTranslatable'])  && $field['isTranslatable'] === true && $field['type'] === 'templateselect') {
                $translatable[] = 'templatefields';
            }elseif(isset($field['isTranslatable']) && $field['isTranslatable'] === true) {
                $translatable[] = $name;
            }
        }

        return $translatable;
    }
}
