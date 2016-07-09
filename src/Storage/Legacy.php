<?php

namespace Bolt\Extension\Animal\Translate\Storage;

use Bolt\Legacy\Storage;
use Silex\Application;
use Bolt\Legacy\Content;

class Legacy extends Storage
{
    public function getContentObject($contenttype, $values = [])
    {
        $reflection = new \ReflectionClass($this);
        $prop = $reflection->getParentClass()->getProperty('app');
        $prop->setAccessible(true);
        $app = $prop->getValue($this);
        
        $default = array_values($app['translate.config']['locales'])[0]['slug'];
        $localeSlug = $app['request']->get('_locale', $default);
        if(isset($values[$localeSlug.'_data'])){
            $localeData = json_decode($values[$localeSlug.'_data']);
            foreach ($localeData as $key => $value) {
                $values[$key] = $value;
            }
        }
        
        // Make sure $contenttype is an array, and not just the slug.
        if (!is_array($contenttype)) {
            $contenttype = $this->getContentType($contenttype);
        }
        
        // If the contenttype has a 'class' specified, and the class exists,
        // Initialize the content as an object of that class.
        if (!empty($contenttype['class']) && class_exists($contenttype['class'])) {
            $content = new $contenttype['class']($app, $contenttype, $values);
            // Check if the class actually extends \Bolt\Legacy\Content.
            if (!($content instanceof Content)) {
                throw new \Exception($contenttype['class'] . ' does not extend \\Bolt\\Legacy\\Content.');
            }
        } else {
            $content = new Content($app, $contenttype, $values);
        }
        return $content;
    }
}