<?php

namespace Bolt\Extension\sahassar\translate\Content;

use Bolt\Extension\sahassar\translate\Extension as Extension;
use Bolt\Library as Lib;

class LocalizedContent extends \Bolt\Content
{

    /** @var boolean Whether this is a "real" contenttype or an embedded ones */
    private $isRootType;

    public function setValues(array $values){
        // Since Bolt 1.4, we use 'ownerid' instead of 'username' in the DB tables. If we get an array that has an
        // empty 'ownerid', attempt to set it from the 'username'. In $this->setValue the user will be set, regardless
        // of ownerid is an 'id' or a 'username'.
        if (empty($values['ownerid']) && !empty($values['username'])) {
            $values['ownerid'] = $values['username'];
            unset($values['username']);
        }
        foreach ($values as $key => $value) {
            if ($key !== 'templatefields') {
                $this->setValue($key, $value);
            }
        }
        // If default status is set in contentttype.
        if (empty($this->values['status']) && isset($this->contenttype['default_status'])) {
            $this->values['status'] = $this->contenttype['default_status'];
        }
        $serializedFieldTypes = array(
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
        
        // replace the values with their translated counterparts
        $this->localeHydrate();

        // Check if the values need to be unserialized, and pre-processed.
        foreach ($this->values as $key => $value) {
            if ((in_array($this->fieldtype($key), $serializedFieldTypes)) || ($key == 'templatefields')) {
                if (!empty($value) && is_string($value) && (substr($value, 0, 2) == "a:" || $value[0] === '[' || $value[0] === '{')) {
                    try {
                        $unserdata = Lib::smartUnserialize($value);
                    } catch (\Exception $e) {
                        $unserdata = false;
                    }
                    if ($unserdata !== false) {
                        $this->values[$key] = $unserdata;
                    }
                }
            }
            if ($this->fieldtype($key) == "video" && is_array($this->values[$key]) && !empty($this->values[$key]['url'])) {
                $video = $this->values[$key];
                // update the HTML, according to given width and height
                if (!empty($video['width']) && !empty($video['height'])) {
                    $video['html'] = preg_replace("/width=(['\"])([0-9]+)(['\"])/i", 'width=${1}' . $video['width'] . '${3}', $video['html']);
                    $video['html'] = preg_replace("/height=(['\"])([0-9]+)(['\"])/i", 'height=${1}' . $video['height'] . '${3}', $video['html']);
                }
                $responsiveclass = "responsive-video";
                // See if it's widescreen or not.
                if (!empty($video['height']) && (($video['width'] / $video['height']) > 1.76)) {
                    $responsiveclass .= " widescreen";
                }
                if (strpos($video['url'], "vimeo") !== false) {
                    $responsiveclass .= " vimeo";
                }
                $video['responsive'] = sprintf('<div class="%s">%s</div>', $responsiveclass, $video['html']);
                // Mark them up as Twig_Markup.
                $video['html'] = new \Twig_Markup($video['html'], 'UTF-8');
                $video['responsive'] = new \Twig_Markup($video['responsive'], 'UTF-8');
                $this->values[$key] = $video;
            }
            if ($this->fieldtype($key) == "date" || $this->fieldtype($key) == "datetime") {
                if ($this->values[$key] === "") {
                    $this->values[$key] = null;
                }
            }
        }
        // Template fields need to be done last
        // As the template has to have been selected
        if ($this->isRootType) {
            if (empty($values['templatefields'])) {
                $this->setValue('templatefields', array());
            } else {
                $this->setValue('templatefields', $values['templatefields']);
            }
        }
    }
    /**
     * localeHydrate
     *
     * This method replaces values with their translated counterparts
     * in a single record.
     */
    private function localeHydrate($content = "")
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

                $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');

                $query = 'SELECT field, value FROM '.$prefix.'translation where locale = :locale and content_type = :contenttype and content_type_id = :id';
                $stmt = $this->app['db']->prepare($query);
                $stmt->bindValue('locale', $locale);
                $stmt->bindValue('contenttype', $this->contenttype['slug']);
                $stmt->bindValue('id', $this->id);
                $stmt->execute();
                $this->delocalizedValues = [];
                while ($row = $stmt->fetch()) {
                    $this->delocalizedValues[$row['field']] = $this->values[$row['field']];
                    $this->setValue($row['field'],$row['value']);
                }
            }
        }
    }
}