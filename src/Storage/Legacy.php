<?php

namespace Bolt\Extension\Animal\Translate\Storage;

use Bolt\Legacy\Content;
use Bolt\Legacy\Storage;
use Bolt\Storage\Field\Collection\RepeatingFieldCollection;

class Legacy extends Storage
{
    /**
     * Override to set localized values before hydration in legacy storage
     */
    public function getContentObject($contenttype, $values = [], $isRootType = true)
    {
        $reflection = new \ReflectionClass($this);
        $prop = $reflection->getParentClass()->getProperty('app');
        $prop->setAccessible(true);
        $app = $prop->getValue($this);
        $this->localeValues = $values;
        
        $localeSlug = $app['translate.slug'];
        if (isset($values[$localeSlug . 'data'])) {
            $localeData = json_decode($values[$localeSlug . 'data'], true);
            if(is_array($localeData)){
                foreach ($localeData as $key => $value) {
                    $values[$key] = is_array($value) ? json_encode($value) : $value;
                }
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
        
        $content['originalValues'] = $this->localeValues;
        return $content;
    }

    public function getContent($textquery, $parameters = '', &$pager = [], $whereparameters = [])
    {
        $result = parent::getContent($textquery, $parameters, $pager, $whereparameters);

        if ($result) {
            $reflection = new \ReflectionClass($this);
            $prop = $reflection->getParentClass()->getProperty('app');
            $prop->setAccessible(true);
            $app = $prop->getValue($this);

            if (is_array($result)) {
                foreach ($result as &$record) {
                    $this->repeaterHydrate($record, $app);
                }
            } else {
                $this->repeaterHydrate($result, $app);
            }
        }

        return $result;
    }

    private function repeaterHydrate($record, $app)
    {
        $contentTypeName = $record->contenttype['slug'];

        $contentType = $app['config']->get('contenttypes/' . $contentTypeName);
        
        $values = $this->localeValues;
        $localeSlug = $app['translate.slug'];

        if (isset($values[$localeSlug . 'data'])) {
            $localeData = json_decode($values[$localeSlug . 'data'], true);

            if ($localeData !== null) {
                foreach ($localeData as $key => $value) {
                    if ($key === 'templatefields' && !( $record['template']==Null && !isset($contentType['record_template']) )) {
                        if (isset($record['template']) && $record['template']==Null) {
                            $templateFields = $app['config']->get('theme/templatefields/' .  $contentType['record_template'] . '/fields');
                        } else {
                            $templateFields = $app['config']->get('theme/templatefields/' . $record['template'] . '/fields');
                        }
                        if ( is_array($templateFields) ) {
                            foreach ($templateFields as $key => $field) {
                                if ($field['type'] === 'repeater') {
                                    $localeData = json_decode($value[$key], true);
                                    $originalMapping = null;
                                    $originalMapping[$key]['fields'] = $templateFields[$key]['fields'];
                                    $originalMapping[$key]['type'] = 'repeater';

                                    $mapping = $app['storage.metadata']->getRepeaterMapping($originalMapping);
                                    $repeater = new RepeatingFieldCollection($app['storage'], $mapping);
                                    $repeater->setName($key);

                                    foreach ($localeData as $subValue) {
                                        $repeater->addFromArray($subValue);
                                    }

                                    $record['templatefields'][$key] = $repeater;
                                }
                            }
                        }
                    }

                    /** 
                    *Fix for field type blocks
                    */
                    if (isset($contentType['fields'][$key]) && $contentType['fields'][$key]['type'] === 'block'  && $value !== null) {
                        $originalMapping=[];
                        $originalMapping[$key]['fields'] = $contentType['fields'][$key]['fields'];
                        $originalMapping[$key]['type'] = 'block';
                        $mapping = $app['storage.metadata']->getRepeaterMapping($originalMapping);
                        $record[$key] = new RepeatingFieldCollection($app['storage'], $mapping);
                        foreach ($value as $group => $block) {
                            foreach ($block as $blockName => $fields) {
                                $fields = $fields;
                                array_shift($fields);
                                if (is_array($fields)) {
                                    $record[$key]->addFromArray($fields, $group, null, $blockName);
                                }
                            }
                        }
                    }

                    if (isset($contentType['fields'][$key]) && $contentType['fields'][$key]['type'] === 'repeater'  && $value !== null) {
                        /**
                        * Hackish fix until #5533 gets fixed, after that the
                        * following five (5) lines can be replaced with
                        * "$record[$key]->clear();"
                        */
                        $originalMapping=[];
                        $originalMapping[$key]['fields'] = $contentType['fields'][$key]['fields'];
                        $originalMapping[$key]['type'] = 'repeater';
                        $mapping = $app['storage.metadata']->getRepeaterMapping($originalMapping);
                        $record[$key] = new RepeatingFieldCollection($app['storage'], $mapping);

                        foreach ($value as $subValue) {
                            $record[$key]->addFromArray($subValue);
                        }
                    }
                }
            }
        }
    }
}
