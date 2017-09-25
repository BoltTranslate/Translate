<?php

namespace Bolt\Extension\Animal\Translate\Frontend;

use Bolt\Controller\Frontend;
use Bolt\Extension\Animal\Translate\Config\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalizedFrontend extends Frontend
{
    protected function getConfigurationRoutes()
    {
        $routes = $this->app['config']->get('routing', []);

        if ($this->app['translate.config']->isRoutingOverride()) {
            /** @var Config $config */
            $config = $this->app['translate.config'];

            $requirements = '';

            foreach ($config->getLocales() as $locale) {
                $requirements = $locale->getSlug() . '|' . $requirements;
            }

            foreach ($routes as $name => &$route) {
                if ($name !== 'preview') {
                    $route['path'] = '/{_locale}' . $route['path'];
                    $route['requirements']['_locale'] = $requirements;
                }
            }

            $routes = array_merge([
                'homepageredir' => [
                    'path' => '/',
                    'defaults' => ['_controller' => 'controller.frontend:homepageRedirect']
                ]
            ], $routes);
        }

        return $routes;
    }

    public function homepageRedirect(Request $request)
    {
        return $this->app->redirect($this->app['translate.slug']);
    }

    /**
     * Controller for a single record page, like '/page/about/' or '/entry/lorum'.
     *
     * @param Request $request         The request
     * @param string  $contenttypeslug The content type slug
     * @param string  $slug            The content slug
     *
     * @return Response
     */
    public function record(Request $request, $contenttypeslug, $slug = '')
    {
        $contenttype = $this->getContentType($contenttypeslug);
        $localeSlug = $this->app['translate.slug'];


        $slug = $this->app['slugify']->slugify($slug);

        if (is_numeric($slug) || !$this->app['translate.config']->isTranslateSlugs() || !isset($contenttype['fields']['locale'])) {
            return parent::record($request, $contenttypeslug, $slug);
        }

        $repo = $this->app['storage']->getRepository($contenttype['slug']);
        $qb = $repo->createQueryBuilder();
        $qb->select('slug')
            ->where($localeSlug . 'slug = ?')
            ->setParameter(0, $slug)
            ->setMaxResults(1);

        $result = $qb->execute()->fetch();

        return parent::record($request, $contenttypeslug, $result['slug']);
    }

    /**
     * Nasty way of getting the listing params
     * Since the scope is set to private there is no other way to fetch our variables
     *
     * @param $contenttypeslug
     */
    private function getParentListingParameters($contenttypeslug)
    {
        $method = new \ReflectionMethod('Bolt\Controller\Frontend', 'getListingParameters');
        $method->setAccessible(true);

        return $method->invokeArgs($this, [$contenttypeslug]);
    }

    /**
     * The listing page controller.
     *
     * @param Request $request         The Symfony Request
     * @param string  $contenttypeslug The content type slug
     *
     * @return TemplateResponse
     */
    public function listing(Request $request, $contenttypeslug)
    {
        $localeSlug = $this->app['translate.slug'];
        $listingparameters = $this->getParentListingParameters($contenttypeslug);

        // Key to check if the content hass an
        $dbKey = $localeSlug . 'data';

        // Exclude content that hasn't got data in for the current language
        $whereparameters = [$dbKey => '!'];

        $content = $this->getContent($contenttypeslug, $listingparameters, $pager, $whereparameters);
        $contenttype = $this->getContentType($contenttypeslug);

        $template = $this->templateChooser()->listing($contenttype);

        // Make sure we can also access it as {{ pages }} for pages, etc. We set these in the global scope,
        // So that they're also available in menu's and templates rendered by extensions.
        $globals = [
            'records'        => $content,
            $contenttypeslug => $content,
            'contenttype'    => $contenttype['name'],
        ];

        return parent::render($template, [], $globals);

    }

}
