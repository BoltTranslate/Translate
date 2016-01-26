<?php

namespace Bolt\Extension\Animal\Translate\Frontend;

use Bolt\Library as Lib;

Class LocalizedFrontend extends \Bolt\Controllers\Frontend
{
    public function record(\Silex\Application $app, $contenttypeslug, $slug = '')
    {
        $contenttype = $app['storage']->getContentType($contenttypeslug);

        // If the contenttype is 'viewless', don't show the record page.
        if (isset($contenttype['viewless']) && $contenttype['viewless'] === true) {
            return $app->abort(Response::HTTP_NOT_FOUND, "Page $contenttypeslug/$slug not found.");
        }

        // Perhaps we don't have a slug. Let's see if we can pick up the 'id', instead.
        if (empty($slug)) {
            $slug = $app['request']->get('id');
        }

        $slug = $app['slugify']->slugify($slug);

        // First, try to get it by slug.
        $content = $app['storage']->getContent($contenttype['slug'], array('slug' => $slug, 'returnsingle' => true, 'log_not_found' => !is_numeric($slug)));

        if (!$content && !is_numeric($slug)) {
            // And otherwise try getting it by translated slugs
            $extension  = new \Bolt\Extension\Animal\Translate\Extension($app);
            $match = $extension->matchSlug($contenttype['slug'], $slug);
            if(!empty($match)){
                $content = $app['storage']->getContent($contenttype['slug'], array('id' => $match['content_type_id'], 'returnsingle' => true));
            }
        }
        if (!$content && is_numeric($slug)) {
            // And otherwise try getting it by ID
            $content = $app['storage']->getContent($contenttype['slug'], array('id' => $slug, 'returnsingle' => true));
        }

        // No content, no page!
        if (!$content) {
            return $app->abort(Response::HTTP_NOT_FOUND, "Page $contenttypeslug/$slug not found.");
        }

        // Then, select which template to use, based on our 'cascading templates rules'
        $template = $app['templatechooser']->record($content);

        $paths = $app['resources']->getPaths();

        // Setting the canonical URL.
        if ($content->isHome() && ($template == $app['config']->get('general/homepage_template'))) {
            $app['resources']->setUrl('canonicalurl', $paths['rooturl']);
        } else {
            $url = $paths['canonical'] . $content->link();
            $app['resources']->setUrl('canonicalurl', $url);
        }

        // Setting the editlink
        $app['editlink'] = Lib::path('editcontent', array('contenttypeslug' => $contenttype['slug'], 'id' => $content->id));
        $app['edittitle'] = $content->getTitle();

        // Make sure we can also access it as {{ page.title }} for pages, etc. We set these in the global scope,
        // So that they're also available in menu's and templates rendered by extensions.
        $app['twig']->addGlobal('record', $content);
        $app['twig']->addGlobal($contenttype['singular_slug'], $content);

        // Render the template and return.
        return $this->render($app, $template, $content->getTitle());
    }
        
    public function homepageRedirect(\Silex\Application $app){
        return $app->redirect(reset($app['config']->get('general/locales'))['slug']);
    }
}