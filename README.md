# Translation plugin for Bolt CMS (Work in progress)

![Screenshot, Backend](https://cloud.githubusercontent.com/assets/343392/10799822/23900e48-7daf-11e5-86ad-c7f7730a0b13.png)

## Setup

First set your contenttype to use the `LocalizedContent` class 
and add the locale field in `contenttypes.yml`:

```
pages:
    name: Pages
    slug: pages
    singular_name: Page
    singular_slug: page
    class: Bolt\Extension\Animal\Translate\Content\LocalizedContent
    fields:
        locale:
            type: locale
            group: content
[...]
```

Add the `isTranslatable` argument to all translatable fields:

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
preferred default locale, a full example can be found in the
`routing.yml.dist` in this dir:

```
contentlink:
    path: '/{_locale}/{contenttypeslug}/{slug}'
    defaults:
        _locale: sv
        _controller: 'Bolt\Extension\Animal\Translate\Frontend\LocalizedFrontend::record'
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

## Configuration

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

## State of the Extension

### System

- [x] Setup configuration
- [ ] Dynamically extend routing (/{_locale}/route) (wontfix?)
- [x] Database: New Table for translations (bolt_translation)
- [x] Set system language in frontend

### Backend

- [x] Field Type Locale (to include locale switcher)
- [x] Field attribute to mark translatable fields (isTranslatable)
- [x] Ajax Controller to load translated content
- [x] Save on update, depending on locale (Event: PRE_SAVE)
- [x] Add icons to mark translatable fields
- [x] Move locale selector to tab navigation
- [x] Hide Locale selector on content type creation
- [x] Reset locale selector to default language or don't reset content of fields after save/update
- [x] Cleanup on delete

### Frontend

- [x] Load content in correct language (Event: preHydrate)
- [x] Language fallback, if not exists ("fixed" by redirecting to default language)
- [x] Basic locale switcher (twig function)
- [x] Override menu to account for translated slugs and locales

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

„We build it“ — [ANIMAL](http://animal.at)
