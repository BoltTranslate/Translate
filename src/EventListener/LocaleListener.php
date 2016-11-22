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

       $request = $event->getRequest();

       if ($this->config->isTranslateDashboard()) {
           // If the dashboard is translated, we need to get _locale from routing attributes (frontend) and from the query (backend).
           $localeSlug = $request->get('_locale');
       }
       else {
           // If the dashboard is not translated, we only need to check routing attributes.
           $localeSlug = $request->attributes->get('_locale');
       }

       if ($localeSlug) {
           /** @var Config\Locale $locale */
           $locales = $this->config->getLocales();

           foreach ($locales as $name => $locale) {
               if ($locale->getSlug() === $localeSlug) {
                   // Override $request locale
                   $request->setLocale($name);

                   // Reset $app['locale']
                   $this->app['locale'] = $name;
               }
           }
       }
   }
}
