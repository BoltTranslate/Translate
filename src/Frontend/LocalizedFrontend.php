<?php

namespace Bolt\Extension\Animal\Translate\Frontend;

use Bolt\Controller\Frontend;
use Bolt\Storage\Query\SelectQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalizedFrontend extends Frontend
{
    protected function getConfigurationRoutes()
    {
        $routes = $this->app['config']->get('routing', []);

        if($this->app['translate.config']['routing_override']){
            foreach ($routes as $name => &$route) {
                if($name !== "preview"){
                    $route['path'] = '/{_locale}'.$route['path'];
                    $route['requirements']['_locale'] = "^[a-z]{2}(_[A-Z]{2})?$";
                }
            }
            $routes['homepageredir'] = ['path' => '/', 'defaults' => [ '_controller' => 'controller.frontend:homepageRedirect' ]];
        }

        return $routes;
    }
    
    public function homepageRedirect(Request $request){
        return $this->app->redirect($this->app['translate.slug']);
    }
    
    /**
     * Controller for a single record page, like '/page/about/' or '/entry/lorum'.
     *
     * @param Request $request         The request
     * @param string  $contenttypeslug The content type slug
     * @param string  $slug            The content slug
     *
     * @return BoltResponse
     */
    public function record(Request $request, $contenttypeslug, $slug = '')
    {
        $contenttype = $this->getContentType($contenttypeslug);
        $localeSlug = $this->app['translate.slug'];
        
        $slug = $this->app['slugify']->slugify($slug);

        $repo = $this->app['storage']->getRepository($contenttype['slug']);
        $qb = $repo->createQueryBuilder();
        $qb->select('slug')
            ->where($localeSlug.'_slug = ?')
            ->setParameter(0, $slug)
            ->setMaxResults(1);

        $result = $qb->execute()->fetch();

        if (is_numeric($slug) || !$this->app['translate.config']['translate_slugs'] || !$result){
            return parent::record($request, $contenttypeslug, $slug);
        }

        return parent::record($request, $contenttypeslug, $result['slug']);
    }
}