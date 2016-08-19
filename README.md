Translate
=========

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/aeda0c78-7b25-427e-aa90-39b21ab1f8df/mini.png)](https://insight.sensiolabs.com/projects/aeda0c78-7b25-427e-aa90-39b21ab1f8df)

This [bolt.cm](https://bolt.cm/) extension handles translation of content
within bolt. It is recommended to be used in combination with the Labels
extension.

![Screenshot, Backend](https://cloud.githubusercontent.com/assets/343392/10799822/23900e48-7daf-11e5-86ad-c7f7730a0b13.png)

## Installation

 1. Login to your Bolt installation
 2. Go to "Extend" or "Extras > Extend"
 3. Type `translate` into the input field
 4. Click on the extension name
 5. Click on "Browse Versions"
 6. Click on "Install This Version" on the latest stable version

## Configuration

 1. Modify the `locales` block in the extension configuration to suit your
    needs. The first one is the default locale and must be the same as bolts
    own locale:

    ```
    locales:
        en_GB:
            label: English
            slug: en
        de_AT:
            label: Deutsch
            slug: de
    ```

 2. Add the locale field to the contenttypes you want translated in `contenttypes.yml`:

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

 3. Add the `is_translateable` argument to all fields you want to be
    translatable. To translate templatefields you simply tag the templateselect
    with `is_translateable` and all the templatefields will be translateable.

    ```
    [...]
    title:
        type: text
        class: large
        group: content
        is_translateable: true
    [...]
    ```

 4. Use the `localeswitcher` twig-function to render a locale switcher in your
    theme: `{{ localeswitcher() }}` or
    `{{ localeswitcher(template = '_my_localeswitcher_template.twig') }}`

 5. (Optional) Activate/install the Labels extension, set your languages in
    it's config and mark any hardcoded text in your templates with
    `{{ l("Your text here") }}`.

 6. (Optional) Translate your Boltforms by switching
    `{% form_theme form 'boltforms_custom.twig' %}` to
    `{% form_theme form '@bolt/frontend/boltforms_theme_translated.twig' %}` at the top
    of a form template. This requires the Labels extension.

---

## Extras/Info

### Localeswitcher in menu

If you want to include the localeswitcher in your menu you can edit the `_sub_menu.twig`
file and add the following right before the closing `<ul>`. 
As you can see, you can add css classes to the localewitcher `<ul>`:

```
<li>Language
    {{ localeswitcher(classes = 'menu submenu vertical') }}
    </li>
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

---

## About

Started by [ANIMAL](http://animal.at), finished/ported to Bolt 3 by SahAssar

---

## License

This Bolt extension is open-sourced software licensed under the MIT License.
