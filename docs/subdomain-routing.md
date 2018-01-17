Subdomain based routing
=======================

If you prefer to have your site locale based around the subdomain instead you
can do this by disabling the routing override in the extension config and
setting the following as your routing. Don't forget to change yourdomain.com to
your domain, the `_locale` default to your default locale, and the `_locale`
requirement to a list of your locales separated by `|`.

```yaml
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
