<?php

namespace Bolt\Extension\Animal\Translate\Storage;

use Bolt\Legacy\Storage;
use Bolt\Legacy\Content;
use Bolt\Storage\Field\Collection\RepeatingFieldCollection;

class Legacy extends Storage
{
    /**
     * Override to set localized values before hydration in legacy storage
     */
    public function getContentObject($contenttype, $values = [])
    {
        $reflection = new \ReflectionClass($this);
        $prop = $reflection->getParentClass()->getProperty('app');
        $prop->setAccessible(true);
        $app = $prop->getValue($this);
        $this->localeValues = $values;
        
        $localeSlug = $app['translate.slug'];
        if(isset($values[$localeSlug.'_data'])){
            $localeData = json_decode($values[$localeSlug.'_data'], true);
            foreach ($localeData as $key => $value) {
                $values[$key] = is_array($value) ? json_encode($value) : $value;
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

    public function getContent($textquery, $parameters = '', &$pager = [], $whereparameters = [])
    {
        $result = parent::getContent($textquery, $parameters, $pager, $whereparameters);

        if($result){
            $reflection = new \ReflectionClass($this);
            $prop = $reflection->getParentClass()->getProperty('app');
            $prop->setAccessible(true);
            $app = $prop->getValue($this);

            if(is_array($result)){
                foreach ($result as &$record) {
                    $this->repeaterHydrate($record, $app);
                }
            }else{
                $this->repeaterHydrate($result, $app);
            }
        }
        return $result;
    }

    private function repeaterHydrate($record, $app) {

        $contentTypeName = $record->contenttype['slug'];

        $contentType = $app['config']->get('contenttypes/'.$contentTypeName);
        
        $values = $this->localeValues;
        $localeSlug = $app['translate.slug'];

        if(isset($values[$localeSlug.'_data'])){
            $localeData = json_decode($values[$localeSlug.'_data'], true);
            
            foreach ($localeData as $key => $value) {
                if ($contentType['fields'][$key]['type'] === 'repeater'){
                    // Hackish fix until #5533 gets fixed, after that L85-88 can be replaced by L89
                    $originalMapping[$key]['fields'] = $contentType['fields'][$key]['fields'];
                    $originalMapping[$key]['type'] = 'repeater';
                    $mapping = $app['storage.metadata']->getRepeaterMapping($originalMapping);
                    $record[$key] = new RepeatingFieldCollection($app['storage'], $mapping);
                    //$record[$key]->clear();
                    foreach ($value as $subValue) {
                        $record[$key]->addFromArray($subValue);
                    }
                }
            }
        }
    }
}