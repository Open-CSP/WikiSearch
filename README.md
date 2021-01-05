# WSSearch

Creates a search app using Semantic MediaWiki and Elasticsearch and renders with Vue.js

features

*facets, highlighted results, pagers, date rage filter*

to do

*extend condition, allow more range filters*

## Parser function:
```
{{#WSSearch: <condition>
  |<facet property>
  |?<result property>
  }}
```

example

```
{{#WSSearch: <condition>
  |Version
  |Tag
  |Space
  |?Title  //first result property will be used as title
  |?Version
  }}
```
