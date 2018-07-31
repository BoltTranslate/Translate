Overrides
=========

This extension overrides Bolt in a few different places and sometimes you want
to revert some of it back to its default state. You can disable the overrides
for routing, menus and slug handling in the config.

### Selectively disable automatic routing override on a route basis

You might want to benefit from the global automatic routing override (using `routing_override: true` in the plugin configuration) but need to exclude specific URLs from being overridden with a locale. To do so, simply add the following requirement to the route you want to exclude from automatic locale routing:

```
    requirements:
        _locale: none
```

For example, you have old pages URLs that you want to keep as is while using the automatic routing override for the other routes. The following route will serve a page under `/{slug}` without a locale present (automatic routing would expect `/{_locale}/{slug}` instead):
```
oldpages:
    path: /{slug}
    defaults:
        _controller: controller.frontend:record
        contenttypeslug: page
    requirements:
        _locale: none
```

If the resulting content is translated, the default locale will be served.
