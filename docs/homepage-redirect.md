# Homepage Redirect

This extension includes a function to automatically detect the preferred
language by using the Accept-Language header supplied by the web browser. The
appropriate routing entry is supplied by the extension so long as the
`routing_override` config entry is set to `true`.

Custom Routing
-----------

If you are instead supplying your own routing, but wish to make use of this
functionality, you'll need to add the entry manually. The entry should be
placed at the bottom of your custom routing rules (to avoid conflicts), and
look something like this:

```yaml
homepageredir:
    path:               /
    defaults:           { _controller: 'controller.frontend:homepageRedirect'}
```

Explanation
----------

This assigns a custom controller to that path, which calls the homepageRedirect
function (located in
`extensions/vendor/animal/translate/src/Frontend/LocalizedFrontend.php` lines
48-80).
