# SMWS

Creates a search app using Semantic MediaWiki and Elasticsearch and renders with Vue.js

features

*facets, highlighted results, pagers*

to do

*extend condition, allow number and date filters, add extra parser parameters*

## Parser function:
```
{{#SMWS: <condition> | <filters>|<output title>|<output snippet>}}
```

example

```
{{#SMWS:Class=Article|Author,Tag,Type|Title|Content}}
```

## MW.Api

```
 var params = {
           action: 'query',
           meta: 'smws',
           format: 'json',
           smfilter: [<active filters>],
           smtitle: <ouptput title property id>,
           smexerpt: <output snippet>,
           smaggs: <filters>,
           smclass:<condition>,
           smterm:<search term>


          },
      api = new mw.Api();

      api.post(  params ).done( function ( data ) {

      //do stuff with data

      })
