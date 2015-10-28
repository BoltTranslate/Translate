# Translation plugin for Bolt CMS (Work in progress)

## Setup

First add the locale field to your contenttype in `contenttypes.yml`:

````
locale:
	type: locale
    group: content
[...]
````

Add the `isTranslatable` argument to all translatable fields:

````
[...]
title:
	type: text
	class: large
	group: content
	isTranslatable: true
[...]
````

Use the `localeswitcher` twig-function to render a locale switcher in your theme:

````
{{ localeswitcher()|raw }}
````
or
````
{{ localeswitcher('_my_localeswitcher_template.twig')|raw }}
````

## Configuration

tba.

## State of the Extension

### System

- [x] Setup configuration
- [ ] Dynamically extend routing (/{_locale}/route)
- [x] Database: New Table for translations (bolt_translation)
- [ ] Set system language in frontend ($this->app['session']->set('lang', $lang) ?)

### Backend

- [x] Field Type Locale (to include locale switcher)
- [x] Field attribute to mark translatable fields (isTranslatable)
- [x] Ajax Controller to load translated content
- [x] Save on update, depending on locale (Event: PRE_SAVE)
- [x] Add icons to mark translatable fields
- [x] Move locale selector to tab navigation
- [x] Hide Locale selector on content type creation
- [ ] Reset locale selector to default language or don't reset content of fields after save/update
- [ ] Cleanup on delete (blocked by [bolt/bolt/#4269](https://github.com/bolt/bolt/issues/4269))

### Frontend

- [ ] Load content in correct language (Event: preHydrate)
- [ ] Language fallback, if not exists (Event: EARLY_EVENT or preHydrate)
- [x] Basic locale switcher (twig function)

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
