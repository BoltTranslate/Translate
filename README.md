Translate
======================

This [bolt.cm](https://bolt.cm/) extension handles translation of content within bolt. It is recomended to be
used in combination with the labels extension. 

![Screenshot, Backend](https://cloud.githubusercontent.com/assets/343392/10799822/23900e48-7daf-11e5-86ad-c7f7730a0b13.png)

### Installation
1. Login to your Bolt installation
2. Go to "Extend" or "Extras > Extend"
3. Type `translate` into the input field
4. Click on the extension name
5. Click on "Browse Versions"
6. Click on "Install This Version" on the latest stable version

### Configuration

1. Modify the `locales` block in the extension configuration to suit your needs.
The first one is the default locale and must be the same as bolts own locale:

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

3. Add the `is_translateable` argument to all fields you want to be translatable.
To translate templatefields you simply tag the templateselect with `is_translateable`
and all the templatefields will be translateable.

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
theme: `{{ localeswitcher() }}` or `{{ localeswitcher('_my_localeswitcher_template.twig') }}`
5. (Optional) Activate/install the labels extension, set your languages in it's config
and mark any hardcoded text in your templates with `{{l("Your text here")}}`.
6. (Optional) Translate your boltforms by switching `{% form_theme form 'boltforms_custom.twig' %}`
to `{% form_theme form 'twig/boltforms_theme_translated.twig' %}` at the top of
a form template. This requires the labels extension.

---
###Extras/Info

####Localeswitcher in menu
If you want to include the localeswitcher in your menu you can edit the `_sub_menu.twig`
file and add the following right before the closing `<ul>`:

```
<li>Language
    {{localeswitcher(null, 'menu submenu vertical')}}
```

####Overrides

This extension overrides bolt in a few different places and sometimes you want
to revert some of it back to it's default state. You can disable the overrides
for routing, menus and slug handling in the config.

---

## About

Started by [ANIMAL](http://animal.at), finished/ported to Bolt 3 by SahAssar

---

### License

This Bolt extension is open-sourced software licensed under the MIT License.