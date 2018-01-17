Localeswitcher in menu
======================

If you want to include the localeswitcher in your menu you can edit the
`_sub_menu.twig` file and add the following right before the closing `<ul>`:

```html
<li>Language
    {{ localeswitcher(classes = 'menu submenu vertical') }}
```
