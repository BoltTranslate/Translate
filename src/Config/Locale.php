<?php

namespace Bolt\Extension\Animal\Translate\Config;

use ArrayAccess;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Locale configuration class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Locale extends ParameterBag implements ArrayAccess
{
    /**
     * @see ArrayAccess::offsetSet
     *
     * @param $offset
     * @param $value
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @see ArrayAccess::offsetUnset
     *
     * @param int $offset
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @see ArrayAccess::offsetExists
     *
     * @param $offset
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @see ArrayAccess::offsetGet
     *
     * @param $offset
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

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
