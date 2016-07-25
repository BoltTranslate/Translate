<?php

namespace Bolt\Extension\Animal\Translate;

use Bolt\Extension\SimpleExtension;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Translate extension class.
 *
 * @author Svante Richter <svante.richter@gmail.com>
 */
class TranslateExtension extends SimpleExtension
{
    /** @var string */
    protected $localeSlug;

    /**
     * @return string
     */
    public function getLocaleSlug()
    {
        return $this->localeSlug;
    }

    /**
     * @inheritdoc
     */
    protected function registerServices(Application $app)
    {
        $this->registerTranslateServices($app);
        $this->registerOverrides($app);

        $app->before([$this, 'before']);

        // Set default localeSlug in the event before() is not called, e.g. a 404
        $config = $this->getConfig();
        $this->localeSlug = array_column($config['locales'], 'slug')[0];
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
        $config = $this->getConfig();
        $defaultSlug = array_column($config['locales'], 'slug')[0];
        $localeSlug = $request->get('_locale', $defaultSlug);

        if (isset($config['locales'][$localeSlug])) {
            $this->localeSlug = $config['locales'][$localeSlug]['slug'];
        } elseif (in_array($localeSlug, array_column($config['locales'], 'slug'))) {
            $this->localeSlug = $localeSlug;
        }
        $this->registerTwigGlobal($app);
    }

    /**
     * {@inheritdoc}
     */
    protected function subscribe(EventDispatcherInterface $dispatcher)
    {
        $app = $this->getContainer();
        $dispatcher->addSubscriber(new EventListener\StorageListener($app['config'], $this->getConfig(), $app['query'], $app['request_stack']));
    }

    /**
     * {@inheritdoc}
     */
    public function getServiceProviders()
    {
        return [
            $this,
            new Provider\FieldProvider(),
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
        ];
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
            function () {
                return $this->localeSlug;
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
        if ($app['translate.config']->isMenuOverride()) {
            $app['menu'] = $app->share(
                function ($app) {
                    return new Frontend\LocalizedMenuBuilder($app);
                }
            );
        }

        $config = $this->getConfig();
        $app['schema.content_tables'] = $app->extend(
            'schema.content_tables',
            function ($contentTables) use ($app, $config) {

                $platform = $app['db']->getDatabasePlatform();
                $prefix = $app['schema.prefix'];
                $contentTypes = $app['config']->get('contenttypes');

                foreach (array_keys($contentTypes) as $contentType) {
                    $contentTables[$contentType] = $app->share(function () use ($platform, $prefix, $config) {
                        return new Storage\ContentTypeTable($platform, $prefix, $config);
                    });
                }

                return $contentTables;
            }
        );
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
            function (\Twig_Environment $twig) use ($app) {
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
        $config = $this->getConfig();
        $locales = $config['locales'];
        $request = $app['request_stack']->getCurrentRequest();
        if ($request === null) {
            return $locales;
        }

        foreach ($locales as $iso => &$locale) {
            $requestAttributes = $request->attributes->get('_route_params');
            if ($config['translate_slugs'] === true && $locale['slug'] !== $requestAttributes['_locale'] && $request->get('slug')) {
                $repo = $app['storage']->getRepository('pages');
                $qb = $repo->createQueryBuilder();
                $qb->select($locale['slug'] . '_slug')
                    ->where($requestAttributes['_locale'] . '_slug = ?')
                    ->setParameter(0, $request->get('slug'))
                ;
                $newSlug = $repo->findOneWith($qb);
                if ($newSlug) {
                    $requestAttributes['slug'] = $newSlug;
                }
            }

            $requestAttributes['_locale'] = $locale['slug'];
            $locale['url'] = $app['url_generator']->generate($request->get('_route'), $requestAttributes);

            if ($this->localeSlug === $locale['slug']) {
                $locale['active'] = true;
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
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'locales' => [],
        ];
    }
}
