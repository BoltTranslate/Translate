<?php

namespace Bolt\Extension\Animal\Translate\Frontend;

use Symfony\Component\HttpFoundation\Response;
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
            $match = $this->matchTranslatedSlug($app, $contenttype['slug'], $slug);
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
    
    private function matchTranslatedSlug($app, $contenttypeslug, $slug = '')
    {
        $prefix = $app['config']->get('general/database/prefix', 'bolt_');
        $locales = $app['config']->get('general/locales');
        $currentLocale = $app['request']->get('_locale');

        $matchedLocales = array_filter(
            $locales,
            function ($e) use ($currentLocale) {
                return $e['slug'] === $currentLocale;
            }
        );

        $locale = key($matchedLocales);
        $query = 'select content_type_id from '.$prefix.'translation where field = "slug" and locale = ? and content_type = ? and value = ?';
        $stmt = $app['db']->prepare($query);
        $stmt->bindValue(1, $locale);
        $stmt->bindValue(2, $contenttypeslug);
        $stmt->bindValue(3, $slug);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    public function homepageRedirect(\Silex\Application $app){
        $locales = $app['config']->get('general/locales');
        $locale = reset($locales);
        return $app->redirect($locale['slug']);
    }
    
}