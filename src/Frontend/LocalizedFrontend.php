<?php

namespace Bolt\Extension\Animal\Translate\Frontend;

use Bolt\Controller\Frontend;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalizedFrontend extends Frontend
{
    protected function getConfigurationRoutes()
    {
        $routes = $this->app['config']->get('routing', []);

        if ($this->app['translate.config']->isRoutingOverride()) {
            /** @var Config\Config $config */
            $config = $this->app['translate.config'];
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

        $repo = $this->app['storage']->getRepository($contenttype['slug']);
        $qb = $repo->createQueryBuilder();
        $qb->select('slug')
            ->where($localeSlug . '_slug = ?')
            ->setParameter(0, $slug)
            ->setMaxResults(1);

        $result = $qb->execute()->fetch();

        if (is_numeric($slug) || !$this->app['translate.config']->isTranslateSlugs()) {
            return parent::record($request, $contenttypeslug, $slug);
        }

        return parent::record($request, $contenttypeslug, $result['slug']);
    }
}
