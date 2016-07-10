<?php

namespace Bolt\Extension\Animal\Translate\Frontend;

use Bolt\Menu\MenuBuilder;
use Bolt\Menu\Menu;

class LocalizedMenuBuilder extends MenuBuilder
{
    private $prefix = "";
    
    public function resolve(array $menu)
    {
        $reflection = new \ReflectionClass($this);
        $prop = $reflection->getParentClass()->getProperty('app');
        $prop->setAccessible(true);
        $this->app = $prop->getValue($this);
        
        if(isset($this->app['translate.slug'])){
            $this->prefix = $this->app['translate.slug'] . '/';
        }
        
        return $this->menuBuilder($menu);
    }
    
    private function menuBuilder(array $menu)
    {
        foreach ($menu as $key => $item) {
            $menu[$key] = $this->menuHelper($item);
            if (isset($item['submenu'])) {
                $menu[$key]['submenu'] = $this->menuBuilder($item['submenu']);
            }
        }
        return $menu;
    }
    
    private function menuHelper($item)
    {
        // recurse into submenu's
        if (isset($item['submenu']) && is_array($item['submenu'])) {
            $item['submenu'] = $this->menuHelper($item['submenu']);
        }
        if (isset($item['route'])) {
            $param = !empty($item['param']) ?: array();
            $add = !empty($item['add']) ?: '';
            $item['link'] = Lib::path($item['route'], $param, $add);
        } elseif (isset($item['path'])) {
            $item = $this->resolvePathToContent($item);
        }
        return $item;
    }
    
    private function resolvePathToContent(array $item)
    {
        if ($item['path'] === 'homepage') {
            $item['link'] = $this->app['resources']->getUrl('root') . $this->prefix;

            return $item;
        }

        // We have a mistakenly placed URL, allow it but log it.
        if (preg_match('#^(https?://|//)#i', $item['path'])) {
            $item['link'] = $item['path'];
            $this->app['logger.system']->error(
                Trans::__(
                    'Invalid menu path (%PATH%) set in menu.yml. Probably should be a link: instead!',
                    ['%PATH%' => $item['path']]
                ),
                ['event' => 'config']
            );

            return $item;
        }

        // Get a copy of the path minus trailing/leading slash
        $path = ltrim(rtrim($item['path'], '/'), '/');


        // Pre-set our link in case the match() throws an exception
        $item['link'] = $this->app['resources']->getUrl('root') . $this->prefix . $path;

        try {
            // See if we have a 'content/id' or 'content/slug' path
            if (preg_match('#^([a-z0-9_-]+)/([a-z0-9_-]+)$#i', $path)) {
                // Determine if the provided path first matches any routes
                // that we have, this will catch any valid configured
                // contenttype slug and record combination, or throw a
                // ResourceNotFoundException exception otherwise
                $this->app['url_matcher']->match('/' . $this->prefix . $path);

                // If we found a valid routing match then we're still here,
                // attempt to retrieve the actual record and use its values.
                $item = $this->populateItemFromRecord($item, $path);
            }
        } catch (ResourceNotFoundException $e) {
            $this->app['logger.system']->error(
                Trans::__(
                    'Invalid menu path (%PATH%) set in menu.yml. Does not match any configured contenttypes or routes.',
                    ['%PATH%' => $item['path']]
                ),
                ['event' => 'config']
            );
        } catch (MethodNotAllowedException $e) {
            // Route is probably a GET and we're currently in a POST
        }

        return $item;
    }
    private function populateItemFromRecord(array $item, $path)
    {
        /** @var \Bolt\Legacy\Content $content */
        $content = $this->app['storage']->getContent($path, ['hydrate' => false]);

        if ($content) {
            if (empty($item['label'])) {
                $item['label'] = !empty($content->values['title']) ? $content->values['title'] : '';
            }

            if (empty($item['title'])) {
                $item['title'] = !empty($content->values['subtitle']) ? $content->values['subtitle'] : '';
            }

            $item['link'] = $content->link();
        }

        return $item;
    }
}