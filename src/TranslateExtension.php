<?php

namespace Bolt\Extension\Animal\Translate;

use Bolt\Asset\Widget\Widget;
use Bolt\Asset\Target;
use Bolt\Controller\Zone;
use Bolt\Extension\Animal\Translate\Config;
use Bolt\Extension\SimpleExtension;
use Silex\Application;
use Silex\LazyUrlMatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Translate extension class.
 *
 * @author Svante Richter <svante.richter@gmail.com>
 */
class TranslateExtension extends SimpleExtension
{
    /**
     * @inheritdoc
     */
    protected function registerServices(Application $app)
    {
        $this->registerTranslateServices($app);
        $this->registerOverrides($app);

        $app->before([$this, 'before']);
    }

    /**
     * Before handler that sets the localeSlug for future use and sets the
     * locales global in twig.
     *
     * @param Request     $request
     * @param Application $app
     */
    public function before(Request $request, Application $app)
    {
        $this->registerTwigGlobal($app);
    }

    /**
     * {@inheritdoc}
     */
    protected function subscribe(EventDispatcherInterface $dispatcher)
    {
        $app = $this->getContainer();
        if (count($app['translate.config']->getLocales()) !== 0) {
            $dispatcher->addSubscriber(
                new EventListener\StorageListener(
                    $app['config'],
                    $app['translate.config'],
                    $app['query'],
                    $app['request_stack']
                )
            );
            $urlMatcher = new LazyUrlMatcher(function () use ($app) {
                    return $app['url_matcher'];
                    });
            $dispatcher->addSubscriber(
                new EventListener\LocaleListener(
                    $app['translate.config'],
                    $app,
                    $urlMatcher,
                    $app['request_stack']
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function registerFields()
    {
        return [
            new Field\LocaleField(),
            new Field\LocaleDataField()
        ];
    }


    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [
            'templates' => ['position' => 'prepend', 'namespace' => 'bolt'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigFunctions()
    {
        return [
            'localeswitcher' => ['localeSwitcher', ['is_variadic' => true]],
            'flag_icon' => ['flagIcon', ['is_safe' => ['html']]],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerAssets()
    {
        $asset = new Widget();
        $asset->setZone(Zone::BACKEND)
            ->setLocation(Target::WIDGET_BACK_OVERVIEW_ASIDE_TOP)
            ->setCallback([$this, 'callbackWidget'])
            ->setDefer(false)
        ;

        return [
            $asset,
        ];
    }

    public function callbackWidget()
    {
        return $this->renderTemplate('backend/widget.twig');
    }

    /**
     * Register translate services/values on the app container
     *
     * @param Application $app
     */
    private function registerTranslateServices(Application $app)
    {
        $app['translate'] = $app->share(
            function () {
                return $this;
            }
        );
        $app['translate.config'] = $app->share(
            function () {
                return new Config\Config($this->getConfig());
            }
        );
        $app['translate.slug'] = $app->share(
            function ($app) {
                /** @var Config\Config $config */
                $config = $app['translate.config'];
                if (count($config->getLocales()) === 0) {
                    return;
                }
                $request = $app['request_stack']->getCurrentRequest();
                /** @var Config\Locale $locale */
                $locale = $config->getLocales();
                $locale = reset($locale);
                $defaultSlug = $locale->getSlug();

                if ($request === null) {
                    return $defaultSlug;
                }

                if ($config->isUseAcceptLanguageHeader()) {
                    $localeNames = array();
                    foreach ($config->getLocales() as $name => $locale) {
                        /** @var Config\Locale $locale */
                        $localeNames[$name] = $locale->getSlug();
                        if (preg_match('/([a-z]{2})_[A-Z]{2}/', $name, $match)) {
                            $localeNames[$match[1]] = $locale->getSlug();
                        }
                    }
                    $defaultName = $request->getPreferredLanguage(array_keys($localeNames));
                    $defaultSlug = $localeNames[$defaultName];
                }

                $localeSlug = $request->get('_locale', $defaultSlug);
                if ($config->getLocale($localeSlug) !== null) {
                    return $config->getLocale($localeSlug)->getSlug();
                }

                foreach ($config->getLocales() as $locale) {
                    if ($localeSlug === $locale->getSlug()) {
                        return $localeSlug;
                    }
                }

                return $defaultSlug;
            }
        );        
        $app['translate.locales'] = $app->share(
            function ($app) {
                $config = $app['translate.config'];
                if (count($config->getLocales()) === 0) {
                    return;
                } else {
                   return $config->getLocales();
                }

            }
        );         
    }

    /**
     * Register overrides for Bolt's services
     *
     * @param Application $app
     */
    private function registerOverrides(Application $app)
    {
        if (count($app['translate.config']->getLocales()) !== 0) {
            $app['storage.legacy'] = $app->extend(
                'storage.legacy',
                function ($storage) use ($app) {
                    return new Storage\Legacy($app);
                }
            );

            $app['controller.frontend'] = $app->share(
                function ($app) {
                    $frontend = new Frontend\LocalizedFrontend();
                    $frontend->connect($app);

                    return $frontend;
                }
            );

            if ($app['translate.config']->isUrlGeneratorOverride()) {
                $app['url_generator'] = $app->extend(
                    'url_generator',
                    function (UrlGeneratorInterface $urlGenerator) use ($app) {
                        return new Routing\LocalizedUrlGenerator($urlGenerator, $app);
                    }
                );
            }

            if ($app['translate.config']->isMenuOverride()) {
                $app['menu'] = $app->share(
                    function ($app) {
                        return new Frontend\LocalizedMenuBuilder($app);
                    }
                );
            }

            // Load resources for all languages
            $app['translator.resources'] = $app->extend(
                'translator.resources',
                // Note: is called before request stack is available.
                function (array $resources, $app) {
                    foreach ($app['translate.config']->getLocales() as $name => $locale) {
                        // $app['locale'] contains default locale at this point
                        if ($locale !== $app['locale'] && !in_array($locale,  $app['locale_fallbacks'])) {
                            $resources = array_merge(\Bolt\Provider\TranslationServiceProvider::addResources($app, $name), $resources);
                        }
                    }
                    return $resources;
                }
            );
        }
    }

    /**
     * Register twig global
     *
     * @param Application $app
     */
    private function registerTwigGlobal(Application $app)
    {
        $app['twig'] = $app->extend(
            'twig',
            function (\Twig_Environment $twig) {
                $twig->addGlobal('locales', $this->getCurrentLocaleStructure());

                return $twig;
            }
        );
    }

    /**
     * Helper to get a the current locale structure
     *
     * @return array
     */
    public function getCurrentLocaleStructure()
    {
        $app = $this->getContainer();
        $config = $app['translate.config'];
        $locales = $config->getLocales();
        $request = $app['request_stack']->getCurrentRequest();
        if ($request === null || !Zone::isFrontend($request)) {
            return $locales;
        }

        /** @var Config\Locale $locale */
        foreach ($locales as $iso => $locale) {
            $requestAttributes = $request->attributes->get('_route_params');
            $requestLocale = $request->get('_locale');
            if ($config->isTranslateSlugs() && $locale->getSlug() !== $requestLocale && $request->get('slug')) {
                foreach ($app['config']->get('contenttypes') as $key => $ct) {
                    if (($request->get('contenttypeslug') !== $ct['slug'] && $request->get('contenttypeslug') !== $ct['singular_slug']) || !isset($ct['fields']['locale'])) {
                        continue;
                    }

                    $repo = $app['storage']->getRepository($ct['slug']);
                    $qb = $repo->createQueryBuilder();
                    $qb->select($locale->getSlug() . 'slug')
                        ->where($request->get('_locale') . 'slug = ?')
                        ->setParameter(0, $request->get('slug'))
                    ;
                    $query = $qb->execute();
                    $newSlug = $query->fetchAll();
                    if ($newSlug && isset($newSlug[0][$locale->getSlug() . 'slug']) && !empty($newSlug[0][$locale->getSlug() . 'slug'])) {
                        $requestAttributes['slug'] = $newSlug[0][$locale->getSlug() . 'slug'];
                    } else {
                        $requestAttributes['slug'] = $request->get('slug');
                    }
                }
            }

            $requestAttributes['_locale'] = $locale->getSlug();
            if ($request->get('_route')) {
                $locale->setUrl($app['url_generator']->generate($request->get('_route'), $requestAttributes));
            }
            if ($app['translate.slug'] === $locale->getSlug()) {
                $locale->setActive(true);
            }
        }

        return $locales;
    }

    /**
     * Twig helper to render a locale switcher on the frontend
     *
     * @param array $args
     *
     * @return \Twig_Markup
     */
    public function localeSwitcher(array $args = [])
    {
        $defaults = [
              'classes'  => '',
              'template' => '@bolt/frontend/_localeswitcher.twig',
        ];
        $args = array_merge($defaults, $args);

        $html = $this->renderTemplate($args['template'], [
            'classes' => $args['classes'],
        ]);

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Twig function to insert a nice SVG flag icon.
     */
    public function flagIcon($locale = '', $width = 21, $height = 16, $margin = '3px')
    {
        $config = $this->getConfig();

        if (!$config['show_flags']) {
            return;
        }

        $country = explode('_', $locale); //strtolower(end(split('_', 'en_gb')));

        if (!empty(end($country))) {
            $country = strtolower(end($country));
        }

        try {
            $flag_icon = $this->renderTemplate('@bolt/flag_icons/' . $country . '.svg');
            $flag_image = sprintf(
                '<img src="data:image/svg+xml;base64,%s" width="%s" height="%s" alt="Flag of %s" style="vertical-align: middle; display: inline-block; margin-bottom: %s;">',
                base64_encode($flag_icon),
                $width,
                $height,
                $country,
                $margin
            );
        } catch(\Exception $e) {
            $flag_image = null;
        }

        return $flag_image;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'locales' => [],
            'show_flags' => true
        ];
    }
}
