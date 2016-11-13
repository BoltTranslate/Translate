<?php

namespace Bolt\Extension\Animal\Translate\EventListener;

use Bolt\Extension\Animal\Translate\Config\Config;
use Silex\Application;
use Silex\EventListener\LocaleListener as BaseLocaleListener;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 *  Initializes the locale based on the current request.
 *
 * @author Peter Verraedt <peter@verraedt.be>
 */
class LocaleListener extends BaseLocaleListener
{
   private $config;

   public function __construct(Config $config, Application $app, RequestContextAwareInterface $router = null, RequestStack $requestStack = null)
   {
       $this->config = $config;
       parent::__construct($app, $router, $requestStack);
   }

   public function onKernelRequest(GetResponseEvent $event)
   {
       parent::onKernelRequest($event);

       $localeSlug = $event->getRequest()->getLocale();

       /** @var Config\Locale $locale */
       $locales = $this->config->getLocales();

       foreach ($locales as $name => $locale) {
           if ($locale->getSlug() === $localeSlug) {
               $this->app['locale'] = $name;
           }
       }
   }
}
