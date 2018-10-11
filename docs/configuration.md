
## Configuration

 1. Modify the `locales` block in the extension configuration (which you can
    find under the `Extend` screen in bolt's backend) to suit your needs. The
    first one is the default locale and must be the same as bolts own locale
    which you've set in bolts' main config.yml:

```yaml
locales:
    en_GB:
        label: English
        slug: en
    de_AT:
        label: Deutsch
        slug: de
```

 2. Add the locale field to the contenttypes you want translated in
    `contenttypes.yml`:

```yaml
pages:
    name: Pages
    slug: pages
    singular_name: Page
    singular_slug: page
    fields:
        locale:
            type: locale
            group: content
[...]
```

 3. Add the `translatable` argument to all fields you want to be
    translatable.

    **Note: In older versions the config `translatable` was misspelled as
    `is_translateable`. The old way is still supported, but deprecated and
    will be removed in a future version.**

```yaml
[...]
title:
    type: text
    class: large
    group: content
    translatable: true
[...]
```

    To translate templatefields you simply tag the templateselect
    with `translatable` and all the templatefields will be translatable.

```yaml
[...]
templateselect:
    type: templateselect
    translatable: true
    filter: '*.twig'
[...]
```

 4. Add the hidden fields to all the contenttypes that have translatable
    fields, two for each locale: one called `your_localedata` and one called
    `your_localeslug`. So for the above `locales` example you would put:

```yaml
fields:
[...]
    dedata:
        type: hidden
    deslug:
        type: locale_data
        index: true
    endata:
        type: hidden
    enslug:
        type: locale_data
        index: true
[...]
```

 5. Use the `localeswitcher` twig-function to render a locale switcher in your
    theme: `{{ localeswitcher() }}` or
    `{{ localeswitcher(template = '_my_localeswitcher_template.twig') }}` if you want
    to use a custom template. The base template being used is '_localeswitcher.twig'.

    The `{{ localeswitcher }}` function generates an unordered list with the
    labels of the languages you've set in the config file.

    If you only want to add a custom class to the unordered list don't make a custom
    template. Adding a class is as simple as:

    `{{ localeswitcher(classes = 'custom-class another-class') }}`

 6. (Optional) Activate/install the Labels extension, set your languages in
    it's config and mark any hardcoded text in your templates with
    `{{ l("Your text here") }}`.

 7. (Optional) Translate your Boltforms by switching
    `{% form_theme form 'boltforms_custom.twig' %}` to
    `{% form_theme form '@bolt/frontend/boltforms_theme_translated.twig' %}` at the top
    of a form template. This requires the Labels extension.

 8. (Optional) Translate your menu labels by changing `{{ menu(template = 'partials/_sub_menu.twig') }}`
    to `{{ menu(template = '@bolt/frontend/_sub_menu_translated.twig') }}` in your template.
    You can then have locale specific menu labels by adding them like this:

```yaml
main:
    - label: Home
        delabel: Startseite
        title: This is the first menu item.
        detitle: Dies ist der erste Menüpunkt.
        path: homepage
        class: first
    - label: Second item
        delabel: Zweite Position
        path: entry/1
[...]
```

    If no title/label is set for the current locale it will use the unprefixed ones.
