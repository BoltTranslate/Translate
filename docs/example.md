Example ContentType definition
==============================

Assuming your locales are en_GB and de_AT like in the above example your pages
ContentType should look something like this:

```yaml
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