Translate
=========

[![SensioLabsInsight][sensio.png]][sensio]

This [bolt.cm](https://bolt.cm/) extension handles translation of content within
bolt. It is recommended to be used in combination with the Labels extension.

**Warning: the old (Bolt 3.0) versions of this extension are not compatible with
the Bolt 3.1+ version**
![Screenshot, Backend][screenshot]

## Installation

 1. Login to your Bolt installation
 2. Go to "Extend" or "Extras > Extend"
 3. Type `translate` into the input field
 4. Click on the extension name
 5. Click on "Browse Versions"
 6. Click on "Install This Version" on the latest stable version

## Configuration

 1. Modify the `locales` block in the extension configuration (which you can
    find under the `Extend` screen in bolt's backend) to suit your needs. The
    first one is the default locale and must be the same as bolts own locale
    which you've set in bolts' main config.yml:

    ```
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

    ```
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

    ```
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
    ```
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

    ```
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

    If you only want to a custom class to the unordered list don't make a custom
    template. Adding a class is as simple as:

    `{{ localeswitcher(classes = 'custom-class another-class') }}`

 6. (Optional) Activate/install the Labels extension, set your languages in
    it's config and mark any hardcoded text in your templates with
    `{{ l("Your text here") }}`.

 7. (Optional) Translate your Boltforms by switching
    `{% form_theme form 'boltforms_custom.twig' %}` to
    `{% form_theme form '@bolt/frontend/boltforms_theme_translated.twig' %}` at the top
    of a form template. This requires the Labels extension.

 8. (Optional) Translate your menu labels by chaning `{{ menu(template = 'partials/_sub_menu.twig') }}`
    to `{{ menu(template = '@bolt/frontend/_sub_menu_translated.twig') }}` in your template.
    You can then have locale specific menu labels by adding them like this:

    ```
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

---

## Extras/Info

### Example ContentType definition

Assuming your locales are en_GB and de_AT like in the above example your pages
ContentType should look something like this:

```
pages:
    name: Pages
    singular_name: Page
    fields:
        title:
            type: text
            class: large
            group: content
            translatable: true
        slug:
            type: slug
            uses: title
            translatable: true
        image:
            type: image
            translatable: true
        teaser:
            type: html
            height: 150px
            translatable: true
        body:
            type: html
            height: 300px
            translatable: true
        template:
            type: templateselect
            filter: '*.twig'
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
    taxonomy: [ groups ]
    recordsperpage: 100
```

### Repeater Filetypes

When using repeater fields, the get-syntax (`repeaterfield`.get(`fieldname`))
must be used. This is partly because of a bug in the Bolt core and we won't fix
it until Bolt 4.0 is released. See [this issue](issue90) for more information.

### Localeswitcher in menu

If you want to include the localeswitcher in your menu you can edit the `_sub_menu.twig`
file and add the following right before the closing `<ul>`:

```
<li>Language
    {{ localeswitcher(classes = 'menu submenu vertical') }}
```

### Localeswitcher anywhere/anyhow

If you want to output the localeswitcher (or some part of it) anywhere you have
access to an array called `locales` in basically any template that you use.
Using this you can craft basically any locale selector you want, see #30 for
more info. To see the structure please dump it by using `{{ dump(locales) }}`.
The array `locales` can be ordered based on the active locale by using the
`|order()` filter, like this: `{% for locale in locales|order('-active') %}`.

### Overrides

This extension overrides Bolt in a few different places and sometimes you want
to revert some of it back to it's default state. You can disable the overrides
for routing, menus and slug handling in the config.

### Making sure search engines find out about translations

To make sure that search engines can find out about your site structure you
can add the following to the `<head>` of your site:

```
{% for key, locale in locales %}
    <link rel="alternate" hreflang="{{key|replace({'_': '-'})}}" href="{{locale.getUrl()}}">
{% endfor %}
```

### Flag icons

There is a built in function to output flags for use in localeswitchers or
other places. It is called with `{{ flag_icon(key) }}` where key is a country
code (like `gb` or `de`). The flags are then embedded in an SVG format. A list
of available flags can be seen [here](https://github.com/AnimalDesign/bolt-translate/tree/master/templates/flag_icons).

### Subdomain based routing

If you prefer to have your site locale based around the subdomain instead you
can do this by disabling the routing override in the extension config and
setting the following as your routing. Don't forget to change yourdomain.com
to your domain, the \_locale default to your default locale, and the \_locale
requirement to a list of your locales separated by `|`.

```
homepage:
    path: /
    host: "{_locale}.yourdomain.com"
    defaults:
        _controller: controller.frontend:homepage
        _locale: en
    requirements:
        _locale: "en|de"

search:
    path: /search
    host: "{_locale}.yourdomain.com"
    defaults:
        _controller: controller.frontend:search
        _locale: en
    requirements:
        _locale: "en|de"

preview:
    path: /preview/{contenttypeslug}
    host: "{_locale}.yourdomain.com"
    defaults:
        _controller: controller.frontend:preview
        _locale: en
    requirements:
        contenttypeslug: controller.requirement:anyContentType
        _locale: "en|de"

contentlink:
    path: /{contenttypeslug}/{slug}
    host: "{_locale}.yourdomain.com"
    defaults:
        _controller: controller.frontend:record
        _locale: en
    requirements:
        contenttypeslug: controller.requirement:anyContentType
        _locale: "en|de"

taxonomylink:
    path: /{taxonomytype}/{slug}
    host: "{_locale}.yourdomain.com"
    defaults:
        _controller: controller.frontend:taxonomy
        _locale: en
    requirements:
        taxonomytype: controller.requirement:anyTaxonomyType
        _locale: "en|de"

contentlisting:
    path: /{contenttypeslug}
    host: "{_locale}.yourdomain.com"
    defaults:
        _controller: controller.frontend:listing
        _locale: en
    requirements:
        contenttypeslug: controller.requirement:pluralContentTypes
        _locale: "en|de"
```

---

## About

Started by [ANIMAL](http://animal.at), finished/ported to Bolt 3 by SahAssar

---

## License

This Bolt extension is open-sourced software licensed under the MIT License.

The [Flagkit][flagkit] icons are licensed under the MIT License.

[issue90]: https://github.com/AnimalDesign/bolt-translate/issues/90
[flagkit]: https://github.com/madebybowtie/FlagKit
[screenshot]: https://cloud.githubusercontent.com/assets/343392/10799822/23900e48-7daf-11e5-86ad-c7f7730a0b13.png
[sensio.png]: https://insight.sensiolabs.com/projects/aeda0c78-7b25-427e-aa90-39b21ab1f8df/mini.png
[sensio]: https://insight.sensiolabs.com/projects/aeda0c78-7b25-427e-aa90-39b21ab1f8df
