<?php

namespace Bolt\Extension\Animal\Translate\Content;

use Bolt\Library as Lib;

class LocalizedContent extends \Bolt\Content
{

    /** @var boolean Whether this is a "real" contenttype or an embedded ones */
    private $isRootType;

    /**
     * @param \Silex\Application $app
     * @param string             $contenttype
     * @param array              $values
     * @param boolean            $isRootType
     */
    public function __construct(\Bolt\Application $app, $contenttype = '', $values = array(), $isRootType = true)
    {
        $this->app = $app;
        $this->isRootType = $isRootType;
        if (!empty($contenttype)) {
            // Set the contenttype
            $this->setContenttype($contenttype);
            // If this contenttype has a taxonomy with 'grouping', initialize the group.
            if (isset($this->contenttype['taxonomy'])) {
                foreach ($this->contenttype['taxonomy'] as $taxonomytype) {
                    if ($this->app['config']->get('taxonomy/' . $taxonomytype . '/behaves_like') == 'grouping') {
                        $this->setGroup('', '', $taxonomytype);
                    }
                    // add support for taxonomy default value when options is set
                    $defaultValue = $this->app['config']->get('taxonomy/' . $taxonomytype . '/default');
                    $options = $this->app['config']->get('taxonomy/' . $taxonomytype . '/options');
                    if (isset($options) &&
                            isset($defaultValue) &&
                            array_search($defaultValue, array_keys($options)) !== false) {
                        $this->setTaxonomy($taxonomytype, $defaultValue);
                        $this->sortTaxonomy();
                    }
                }
            }
        }
        $this->user = $this->app['users']->getCurrentUser();
        if (!empty($values)) {
            $this->setValues($values);
        } else {
            // Ininitialize fields with empty values.
            if ((is_array($this->contenttype) && is_array($this->contenttype['fields']))) {
                foreach ($this->contenttype['fields'] as $key => $parameters) {
                    // Set the default values.
                    if (isset($parameters['default'])) {
                        $values[$key] = $parameters['default'];
                    } else {
                        $values[$key] = '';
                    }
                }
            }
            if (!empty($this->contenttype['singular_name'])) {
                $contenttypename = $this->contenttype['singular_name'];
            } else {
                $contenttypename = "unknown";
            }
            // Specify an '(undefined contenttype)'.
            $values['name'] = "(undefined $contenttypename)";
            $values['title'] = "(undefined $contenttypename)";
            $this->setValues($values);
        }
    }

    /**
     * Set a Contenttype record's values.
     *
     * @param array $values
     */
    public function setValues(array $values){
        // replace the values with their translated counterparts
        $values = $this->localeHydrate($values);

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
     * Set a Contenttype record's individual value.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function setValue($key, $value)
    {
        // Don't set templateFields if not a real contenttype
        if (($key === 'templatefields') && (!$this->isRootType)) {
            return;
        }
        // Check if the value need to be unserialized.
        if (is_string($value) && substr($value, 0, 2) === "a:") {
            try {
                $unserdata = Lib::smartUnserialize($value);
            } catch (\Exception $e) {
                $unserdata = false;
            }
            if ($unserdata !== false) {
                $value = $unserdata;
            }
        }
        if ($key == 'id') {
            $this->id = $value;
        }
        // Set the user in the object.
        if ($key === 'ownerid' && !empty($value)) {
            $this->user = $this->app['users']->getUser($value);
        }
        // Only set values if they have are actually a field.
        $allowedcolumns = self::getBaseColumns();
        $allowedcolumns[] = 'taxonomy';
        if (!isset($this->contenttype['fields'][$key]) && !in_array($key, $allowedcolumns)) {
            return;
        }
        if (in_array($key, array('datecreated', 'datechanged', 'datepublish', 'datedepublish'))) {
            if (!preg_match("/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/", $value)) {
                // @todo Try better date-parsing, instead of just setting it to
                // 'now' (or 'the past' for datedepublish)
                if ($key == 'datedepublish') {
                    $value = null;
                } else {
                    $value = date('Y-m-d H:i:s');
                }
            }
        }
        if ($key === 'templatefields') {
            if ((is_string($value)) || (is_array($value))) {
                if (is_string($value)) {
                    try {
                        $unserdata = Lib::smartUnserialize($value);
                    } catch (\Exception $e) {
                        $unserdata = false;
                    }
                } else {
                    $unserdata = $value;
                }
                if (is_array($unserdata)) {
                    $templateContent = new LocalizedContent($this->app, $this->getTemplateFieldsContentType(), array(), false);
                    $value = $templateContent;
                    $this->populateTemplateFieldsContenttype($value);
                    $templateContent->setValues($unserdata);
                } else {
                    $value = null;
                }
            }
        }
        if (!isset($this->values['datechanged']) ||
            !preg_match("/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/", $this->values['datechanged'])) {
            $this->values['datechanged'] = date("Y-m-d H:i:s");
        }
        $this->values[$key] = $value;
    }

    /**
     * localeHydrate
     *
     * This method replaces values with their translated counterparts
     * in a single record.
     */
    private function localeHydrate($values)
    {
        $locales = $this->app['config']->get('general/locales');
        if($locales){
            $locale = reset($locales);
            $defaultLocaleSlug = $locale['slug'];
            $currentLocale = $this->app['request']->get('_locale');
            $matchedLocales = array_filter(
                $locales,
                function ($e) use ($currentLocale) {
                    return $e['slug'] === $currentLocale;
                }
            );

            if($this->app['request']->get('_locale') != $defaultLocaleSlug || !empty($matchedLocales)){

                $locale = key($matchedLocales);

                $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');

                $query = 'SELECT field, value FROM '.$prefix.'translation where locale = :locale and content_type = :contenttype and content_type_id = :id';
                $stmt = $this->app['db']->prepare($query);
                $stmt->bindValue('locale', $locale);
                $stmt->bindValue('contenttype', $this->contenttype['slug']);
                $stmt->bindValue('id', $values['id']);
                $stmt->execute();
                $values['delocalizedValues'] = array();
                while ($row = $stmt->fetch()) {
		            $this->values['delocalizedValues'][$row['field']] = $values[$row['field']];
                    $values[$row['field']] = $row['value'];
                }
            }
        }
        return $values;
    }
}
