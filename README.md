# Translation plugin for Bolt CMS

This plugin handles translation of content within bolt. It is recomended to be
used in combination with the labels extension. 

![Screenshot, Backend](https://cloud.githubusercontent.com/assets/343392/10799822/23900e48-7daf-11e5-86ad-c7f7730a0b13.png)

## Setup

Add the `locales` block to the main configuration with your locales, the first
one is the default locale:

```
locales:
    en_GB:
        label: English
        slug: en
	de_AT:
	    label: Deutsch
        slug: de
```

First set your contenttype to use the `LocalizedContent` class 
and add the locale field in `contenttypes.yml`:

```
pages:
    name: Pages
    slug: pages
    singular_name: Page
    singular_slug: page
    class: Bolt\Extension\sahassar\translate\Content\LocalizedContent
    fields:
        locale:
            type: locale
            group: content
[...]
```

Add the `isTranslatable` argument to all fields you want to be translatable:

```
[...]
title:
	type: text
	class: large
	group: content
	isTranslatable: true
[...]
```

Setup routing in `routing.yml` like below but replacing `sv` with your
preferred default locale, a full example is at the bottom of this file:

```
contentlink:
    path: '/{_locale}/{contenttypeslug}/{slug}'
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\sahassar\translate\Frontend\LocalizedFrontend::record'
    requirements:
        contenttypeslug: 'Bolt\Controllers\Routing::getAnyContentTypeRequirement'
        _locale: "^[a-zA-Z_]{2,5}$"
```

Use the `localeswitcher` twig-function to render a locale switcher in your
theme:

```
{{ localeswitcher()|raw }}
```
or
```
{{ localeswitcher('_my_localeswitcher_template.twig')|raw }}
```

To translate a boltform you can add `{% set form = translate_form(form) %}`
at the top of a form template. This requires the labels extension. (the current
solution is very hacky, WIP)

## Links

- https://docs.bolt.cm/howto/building-multilingual-websites
- https://github.com/bolt/bolt/issues/513
- https://github.com/bolt/bolt/issues/234
- https://github.com/bolt/bolt/issues/2484
- https://github.com/bolt/bolt/issues/3933
- https://vivait.co.uk/labs/updating-entities-when-an-insert-has-a-duplicate-key-in-doctrine
- https://dev.mysql.com/doc/refman/5.0/en/insert-on-duplicate.html
- http://stackoverflow.com/questions/1132571/implementing-update-if-exists-in-doctrine-orm

## About

Started by [ANIMAL](http://animal.at), finished by SahAssar (see commit history)

## Full routing example:
```
homepage:
    path: '/{_locale}'
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\sahassar\translate\Frontend\LocalizedFrontend::homepage'
    requirements:
        _locale: "^[a-zA-Z_]{2,5}$"

# The next two routes are for when you use the sitemap extension

sitemapxml_with_locale:
    path: /{_locale}/sitemap.xml
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\Bolt\Sitemap\Extension::sitemapXml'
    requirements:
        _locale: "^[a-zA-Z_]{2,5}$"

sitemaphtml_with_locale:
    path: /{_locale}/sitemap
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\Bolt\Sitemap\Extension::sitemap'
    requirements:
        _locale: "^[a-zA-Z_]{2,5}$"

search:
    path: '/{_locale}/search'
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\sahassar\translate\Frontend\LocalizedFrontend::search'
    requirements:
        _locale: "^[a-zA-Z_]{2,5}$"

preview:
    path: '/{_locale}/preview/{contenttypeslug}'
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\sahassar\translate\Frontend\LocalizedFrontend::preview'
    requirements:
        contenttypeslug: 'Bolt\Controllers\Routing::getAnyContentTypeRequirement'
        _locale: "^[a-zA-Z_]{2,5}$"

contentlink:
    path: '/{_locale}/{contenttypeslug}/{slug}'
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\sahassar\translate\Frontend\LocalizedFrontend::record'
    requirements:
        contenttypeslug: 'Bolt\Controllers\Routing::getAnyContentTypeRequirement'
        _locale: "^[a-zA-Z_]{2,5}$"

taxonomylink:
    path: '/{_locale}/{taxonomytype}/{slug}'
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\sahassar\translate\Frontend\LocalizedFrontend::taxonomy'
    requirements:
        taxonomytype: 'Bolt\Controllers\Routing::getAnyTaxonomyTypeRequirement'
        _locale: "^[a-zA-Z_]{2,5}$"

contentlisting:
    path: '/{_locale}/{contenttypeslug}'
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\sahassar\translate\Frontend\LocalizedFrontend::listing'
    requirements:
        contenttypeslug: 'Bolt\Controllers\Routing::getPluralContentTypeRequirement'
        _locale: "^[a-zA-Z_]{2,5}$"

pagebinding:
    path: '/{_locale}/{slug}'
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\sahassar\translate\Frontend\LocalizedFrontend::record'
        contenttypeslug: 'sida'
    contenttype: sidor
    requirements:
        _locale: "^[a-zA-Z_]{2,5}$"
```