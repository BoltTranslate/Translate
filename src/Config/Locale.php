<?php

namespace Bolt\Extension\Animal\Translate\Config;

use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Locale configuration class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Locale extends ParameterBag
{
    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->get('label');
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->set('label', $label);
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->get('slug');
    }

    /**
     * @param string $slug
     */
    public function setSlug($slug)
    {
        $this->set('slug', $slug);
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->get('url');
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->set('url', $url);
    }

    /**
     * @return boolean
     */
    public function isActive()
    {
        return $this->getBoolean('active', false);
    }

    /**
     * @param boolean $active
     */
    public function setActive($active)
    {
        $this->set('active', $active);
    }
}
