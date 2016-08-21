<?php

namespace Bolt\Extension\Animal\Translate\Config;

use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * General configuration class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Config extends ParameterBag
{
    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        foreach ($config['locales'] as $name => $parameters) {
            $this->parameters['locales'][$name] = new Locale($parameters);
        }
    }

    /**
     * @param string $name
     *
     * @return Locale
     */
    public function getLocale($name)
    {
        if (!isset($this->parameters['locales'][$name])) {
            return null;
        }

        return $this->parameters['locales'][$name];
    }

    /**
     * @param string $name
     * @param Locale $locale
     */
    public function setLocale($name, Locale $locale)
    {
        $this->parameters['locales'][$name] = $locale;
    }

    /**
     * @return Locale[]
     */
    public function getLocales()
    {
        return $this->get('locales');
    }

    /**
     * @param Locale[] $locales
     */
    public function setLocales(array $locales)
    {
        $this->set('locales', $locales);
    }

    /**
     * @return boolean
     */
    public function isRoutingOverride()
    {
        return $this->getBoolean('routing_override', true);
    }

    /**
     * @param boolean $routingOverride
     */
    public function setRoutingOverride($routingOverride)
    {
        $this->set('routing_override', $routingOverride);
    }

    /**
     * @return boolean
     */
    public function isMenuOverride()
    {
        return $this->getBoolean('menu_override', true);
    }

    /**
     * @param boolean $menuOverride
     */
    public function setMenuOverride($menuOverride)
    {
        $this->set('menu_override', $menuOverride);
    }

    /**
     * @return boolean
     */
    public function isTranslateSlugs()
    {
        return $this->get('translate_slugs', true);
    }

    /**
     * @param boolean $translateSlugs
     */
    public function setTranslateSlugs($translateSlugs)
    {
        $this->set('translate_slugs', $translateSlugs);
    }

    /**
     * @return boolean
     */
    public function isUrlGeneratorOverride()
    {
        return $this->get('url_generator_override', true);
    }

    /**
     * @param boolean $urlGeneratorOverride
     */
    public function setUrlGeneratorOverride($urlGeneratorOverride)
    {
        $this->set('url_generator_override', $urlGeneratorOverride);
    }

    /**
     * @return boolean
     */
    public function isUseAcceptLanguageHeader()
    {
        return $this->getBoolean('use_accept_language_header', true);
    }

    /**
     * @param boolean $useAcceptLanguageHeader
     */
    public function setUseAcceptLanguageHeader($useAcceptLanguageHeader)
    {
        $this->set('use_accept_language_header', $useAcceptLanguageHeader);
    }
}
