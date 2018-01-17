Making sure search engines find out about translations
======================================================

To make sure that search engines can find out about your site structure you
can add the following to the `<head>` of your site:

```html
{% for key, locale in locales|default([]) %}
    <link rel="alternate" hreflang="{{key|replace({'_': '-'})}}" href="{{locale.getUrl()}}">
{% endfor %}
```
