Localeswitcher anywhere/anyhow
==============================

If you want to output the localeswitcher (or some part of it) anywhere you have
access to an array called `locales` in basically any template that you use.
Using this you can craft basically any locale selector you want, see #30 for
more info. To see the structure please dump it by using `{{ dump(locales) }}`.
The array `locales` can be ordered based on the active locale by using the
`|order()` filter, like this: `{% for locale in locales|order('-active') %}`.

