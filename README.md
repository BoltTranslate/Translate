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

1. Add the `locales` block to the extension configuration with your locales, the first
one is the default locale and must be the same as bolts own locale:

    ```
    locales:
        en_GB:
            label: English
            slug: en
        de_AT:
            label: Deutsch
            slug: de
    ```

2. Do a database update.
3. Add the locale field to the contenttypes you want translated in `contenttypes.yml`:

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

4. Add the `isTranslatable` argument to all fields you want to be translatable.
To translate templatefields you simply tag the templateselect with 
`isTranslatable` and all the templatefields will be translateable. (untested on 3.0)

    ```
    [...]
    title:
        type: text
        class: large
        group: content
        isTranslatable: true
    [...]
    ```
5. Use the `localeswitcher` twig-function to render a locale switcher in your
theme: `{{ localeswitcher() }}` or `{{ localeswitcher('_my_localeswitcher_template.twig') }}`
6. Activate/install the labels extension, set your languages in it's config
and mark any hardcoded text in your templates with `{{l("Your text here")}}`.
7. Translate your boltforms by switching `{% form_theme form 'boltforms_custom.twig' %}`
to `{% form_theme form 'twig/boltforms_theme_translated.twig' %}` at the top of
a form template. This requires the labels extension.

---

## About

Started by [ANIMAL](http://animal.at), finished by SahAssar (see commit history)

---

### License

This Bolt extension is open-sourced software licensed under the [Your preferred License]