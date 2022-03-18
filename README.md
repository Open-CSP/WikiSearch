# WikiSearch

This document describes how to use WikiSearch. It is meant for both wiki-administrators as well as external users using the
API endpoint.

## Performing a search

Performs a search and returns the list of search results. If the API is in debug mode, this endpoint also returns the raw
ElasticSearch query that was used to perform the search.

### Parameters

| Parameter      | Type      | Description                                                                                                                                                                                              |
|----------------|-----------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `pageid`       | `integer` | The MediaWiki page ID of the page from which the search configuration should be retrieved. Needs to be a valid page ID of a page containing a configuration.                                             |
| `term`         | `string`  | The search term query to use for the main free-text search. This corresponds to the main search field on a search page. Defaults to the empty string. When no `term` is given, all results are returned. |
| `from`         | `integer` | The cursor to use for pagination. `from` specifies the offset of the results to return. Defaults to `0`.                                                                                                 |
| `limit`        | `integer` | The limit on the number of results to return (inclusive). Defaults to `10`.                                                                                                                              |
| `filter`       | `list`    | The filters to apply to the search. Defaults to the empty list. See below for additional information about the syntax.                                                                                   |
| `aggregations` | `list`    | The aggregations to generate from the search. Defaults to the empty list. See below for additional information and how to specify the aggregations.                                                      |
| `sorting`      | `list`    | The sortings to apply to the search. Defaults to the empty list. See below for additional information about and how to specify the sortings.                                                             |

### Example request

Example request (cURL):
```
curl https://wiki.example.org/api.php \
-d action=query \
-d format=json \
-d meta=WikiSearch \
-d filter=[{"value":"5","key":"Average rating","range":{"gte":5,"lte":6}}] \
-d from=0 \
-d limit=10 \
-d pageid=698 \
-d aggregations=[
    {"type":"range","ranges":[
        {"from":1,"to":6,"key":"1"},
        {"from":2,"to":6,"key":"2"},
        {"from":3,"to":6,"key":"3"},
        {"from":4,"to":6,"key":"4"},
        {"from":5,"to":6,"key":"5"}
    ],"property":"Average rating"}
]
```

Example response:
```
{
    "batchcomplete": "",
    "result": {
        "hits": "[<TRUNCATED, SEE BELOW FOR PARSING>]",
        "total": 1,
        "aggs": {
            "Average rating": {
                "meta": [],
                "doc_count": 1,
                "Average rating": {
                    "buckets": {
                        "1": {
                            "from": 1,
                            "to": 6,
                            "doc_count": 1
                        },
                        "2": {
                            "from": 2,
                            "to": 6,
                            "doc_count": 1
                        },
                        "3": {
                            "from": 3,
                            "to": 6,
                            "doc_count": 1
                        },
                        "4": {
                            "from": 4,
                            "to": 6,
                            "doc_count": 1
                        },
                        "5": {
                            "from": 5,
                            "to": 6,
                            "doc_count": 1
                        }
                    }
                }
            }
        }
    }
}
```

### Parsing the response

This section assumes you have successfully made a request to the API using PHP and have stored the raw API result in the
variable `$response`.

The `$response` object is a JSON encoded string, and needs to be decoded before it can be used:

```php
$response = json_decode($response, true);
```

After having decoded the `$response` object, the response usually contains two keys (three if debug mode is enabled):

| Field           | Type     | Description                                                                                                       |
|-----------------|----------|-------------------------------------------------------------------------------------------------------------------|
| `batchcomplete` | `string` | Added by MediaWiki and not relevant for API users.                                                                |
| `result`        | `object` | Contains the result object of the performed search.                                                               |
| `query`         | `object` | The raw ElasticSearch query used to perform this search. This field is only available when debug mode is enabled. |

Generally, we are only interested in the API result object, so we can create a new variable only containing that field:

```php
$result = $response["result"];
```

This `$result` field will look something like this:

```json
{
    "hits": "[<TRUNCATED, SEE BELOW FOR PARSING>]",
    "total": 1,
    "aggs": {
        "Average rating": {
            "meta": [],
            "doc_count": 1,
            "Average rating": {
                "buckets": {
                    "1": {
                        "from": 1,
                        "to": 6,
                        "doc_count": 1
                    },
                    "2": {
                        "from": 2,
                        "to": 6,
                        "doc_count": 1
                    },
                    "3": {
                        "from": 3,
                        "to": 6,
                        "doc_count": 1
                    },
                    "4": {
                        "from": 4,
                        "to": 6,
                        "doc_count": 1
                    },
                    "5": {
                        "from": 5,
                        "to": 6,
                        "doc_count": 1
                    }
                }
            }
        }
    }
}
```

#### The `hits` field

The `hits` field contains a JSON-encoded string of the ElasticSearch search results. This field needs to be decoded
using `json_decode` before it can be used. The field directly corresponds to the `hits.hits` field from the
ElasticSearch response. See the
[ElasticSearch documentation](https://www.elastic.co/guide/en/elasticsearch/reference/master/search-your-data.html#run-an-es-search)
for very detailed documentation about what this field looks like.

To get the associated page name of any search result, the `subject.namespacename` and `subject.title` hit-field in the 
`hits` field may be concatenated using a colon, like so:

```php
$hits = json_decode($result["hits"], true);

foreach ($hits as $hit) {
    $namespace_name = $hit["subject"]["namespacename"];
    $page_title = $hit["subject"]["title"];

    $page_name = sprintf("%s:%s", $namespace_name, $page_title);

    echo $page_name;
}
```

The `subject.namespacename` hit-field contains the name of the namespace in which the search result lives, and the `subject.title` hit-field contains the name of the page that matched the search (without a namespace prefix). To get the full URL for this page, you can prepend `http://<wikiurl>/index.php/` to the page name.

The `hits` field also contains the generated highlighted snippets, if they are available. These can be accessed through the `highlight` hit-field, like so:

```php
$hits = json_decode($result["hits"], true);

foreach ($hits as $hit) {
    $highlights = $hit["highlight"];
    
    foreach ($highlights as $highlight) {
        // $highlight is an array of highlighted snippets

        $highlight_string = implode("", $highlight);
    
        echo $highlight_string;
    }
}
```

See also the [ElasticSearch Highlighting documentation](https://www.elastic.co/guide/en/elasticsearch/reference/7.12/highlighting.html).

#### The `aggs` field

The `aggs` field directly corresponds to the `aggregations` field from the ElasticSearch response. See the [ElasticSearch documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations.html) for further details.

#### The `total` field

The `total` field contains the total number of results found by ElasticSearch. This field is not influenced by the `limit` and always displays the total number of results available, regardsless of how many were actually returned.

### Filters syntax

The `filter` parameter takes a list of objects. These objects have the following form:

#### PropertyRangeFilter

This filter only returns pages that have the specified property with a value in the specified range.

```
{
    "key": "Age",
    "range": {
        "gte": 0,
        "lt": 100
    }
}
```

The above filter only includes pages where property `Age` has a value that is greater than
or equal to `0`, but strictly less than `100`.

The `range` parameter takes an object that allows the following properties:

- `gte`: Greater-than or equal to
- `gt`: Strictly greater-than
- `lte`: Less-than or equal to
- `lt`: Strictly less-than

#### PropertyValueFilter

This filter only returns pages that have the specified property with the specified value.

```
{
    "key": "Class",
    "value": "Manual"
}
```

The above filter only includes pages where the property `Class` has the value `Manual`. The `value` may
by any of the following data types:

* string
* boolean
* integer
* float
* double

See also: https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-range-query.html

#### PropertyValuesFilter

This filter only returns pages that have the specified property with any of the specified values.

```
{
    "key": "Class",
    "value": ["foo", "bar"]
}
```

The above filter only includes pages where the property `Class` has the value `foo` or `bar`.

See also: https://www.elastic.co/guide/en/elasticsearch/reference/6.8//query-dsl-terms-query.html

#### HasPropertyFilter

This filter only returns pages that have the specified property with any value.

```
{
    "key": "Class",
    "value": "+"
}
```

The above filter only includes pages that have the property `Class`. It does not take the value of the property into account.

See also: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-query.html

#### PropertyTextFilter

This filter only returns pages that have the specified property with a value that matches the given search query string.

```
{
    "key": "Class",
    "value": "Foo | (Bar + Quz)",
    "type": "query"
}
```

The above filter executes the given query and only includes pages that matched the executed query. The query syntax is identical to the simple query syntax used by ElasticSearch.

See also: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-simple-query-string-query.html

### Aggregations syntax

The `aggregations` parameter takes a list of objects. These objects have the following form:

#### PropertyRangeAggregation

```
{
    "type": "range",
    "ranges": [
        { "to": 50 },
        { "from": 50, "to": 100 },
        { "from": 100 }
    ],
    "property": "Price",
    "name": "Prices" # Optional, property name when empty
}
```

> **Note:** The `from` parameter is inclusive, and the `to` parameter is
> exclusive. This means that for an aggregation from (and including) `1` up to and
> including `5`, the `from` and `to` parameters should be `1` and `6` (!) respectively.

See also: https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-range-aggregation.html

#### PropertyAggregation

```
{
    "type": "property",
    "property": "Genre",
    "name": "Genres" # Optional, property name when empty
}
```

See also: https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-terms-aggregation.html

### Sortings syntax

The `sortings` parameter takes a list of objects. These objects have the following form:

#### PropertySort

```
{
    "type": "property",
    "property": "Genre",
    "order": "asc" # Optional, undefined when empty
}
```

The above filter sorts the results based on the value of the property `Genre` in an `asc`ending order. It is also possible to sort in a `desc`ending order.

> **Note:** Sorting on a property that does not exist will result in an error.

### Highlight API

> **Note:** This API is mostly for internal use.

The highlight API has the following properties:

* `query`: The query to generate highlighted terms from
* `properties`: The properties over which the highlights need to be calculated
* `page_id`: The page ID of the page on which the highlights need to be calculated
* `limit`: The number of highlighted terms to calculate; this does not always correspond directly with the number of terms returned, since duplicates are removed after the query to ElasticSearch
* `size`: The (approximate) size of snippets to generate, leave blank to highlight individual words

## Chained properties

WikiSearch provides support for creating filters with chained properties. Chained properties can be used in any filter. They can also be used as a search term property.

```
{
    "key": "Subpage.Foobar",
    "value": "+"
}
```

For instance, the above filter matches any page for which the value of the property "Subpage" is a page that contains the property "Foobar".

See also: https://www.semantic-mediawiki.org/wiki/Help:Subqueries_and_property_chains

## Special properties

There are a number of special properties defined by Semantic MediaWiki that are worth pointing out. These properties act just like regular properties, but do not appear in Special:Browse.

* `text_copy`: (from SemanticMediaWiki documentation) this mapping is used to enable wide proximity searches on textual annotated elements. The `text_copy` field is a compound field for all strings to be searched when a specific property is unknown.
* `text_raw`: this mapping contains unstructured, unprocessed raw text from an article.
* `attachment-title`: this mapping contains the title of a file attachment.
* `attachment-content`: this mapping contains the content of a file attachment.

For example, if you want to search through PDF files linked through the `Pdf` property, you can use the chained property `Pdf.attachment-content`.

## Hooks

### `WikiSearchBeforeElasticQuery`

This hook is called right before the query is sent to ElasticSearch. It has the following signature:

```php
function onWikiSearchBeforeElasticQuery( array &$query, array &$hosts ) {}
```

The hook has access to and can alter the given `$query`. It can also add or remove hosts from the
`$hosts` array.

### `WikiSearchApplyResultTranslations`

This hook is called right before returning the final results to the API. It can be used
to alter the `$results` array. This can be useful to filter any pages the user is not allowed
to see or add additional data to the query result.

It has the following signature:

```php
function onWikiSearchApplyResultTranslations( array &$results ) {}
```

### `WikiSearchOnLoadFrontend`

This hook must be implemented by any WikiSearch frontend. It gets called when the `#loadSeachEngine` parser function
is called. It has the following signature:

```php
function onWikiSearchOnLoadFrontend( 
    string &$result, 
    \WikiSearch\SearchEngineConfig $config, 
    Parser $parser, 
    array $parameters 
) {}
```

* `string &$result`: The result of the call to the parser function. This is the text that will be transcluded on the page.
* `SearchEngineConfig $config`: The SearchEngineConfig object of the current page. The SearchEngineConfig object exposes the following methods:
    * `getTitle(): Title`: The Title associated with this SearchEngineConfig
    * `getConditionProperty(): PropertyInfo`: The PropertyInfo object associated with the property in the search condition (e.g. `Class` for `Class=Foobar`)
        * The `PropertyInfo` class exposes the following methods:
            * `getPropertyID(): int`: Returns the property ID
            * `getPropertyType(): string`: Returns the property type (e.g. `txtField` or `wpgField`)
            * `getPropertyName(): string`: Returns the name of the property (e.g. `Class`)
    * `getConditionValue(): string`: Returns the value in the condition (e.g. `Foobar` in `Class=Foobar`)
    * `getFacetProperties(): array`: Returns the facet properties in the config (facet properties are the properties that are **not** prefixed with `?`). May be the
      name of a property (e.g. "Foobar") or a translation pair (e.g. "Foobar=Boofar")
    * `getFacetPropertyIDs(): array`: Returns a key-value pair list where the key is the ID of the facet property and the value the type of that property
    * `getResultProperties(): array`: Returns the result properties in the config as PropertyInfo objects (result properties are the properties prefixed with `?`)
    * `getResultPropertyIDs(): array`: Returns a key-value pair list where the key is the name of the result property and the value the ID of that property
    * `getSearchParameters(): array`: Returns a key-value pair list of additional search parameters
* `Parser $parser`: The current Parser object
* `array $parameters`: The parameters passed to the `#loadSearchEngine` call

## Config variables

WikiSearch has several configuration variables that influence its default behaviour.

* `$wgWikiSearchElasticStoreIndex`: Sets the name of the ElasticStore index to use (defaults to `"smw-data-" . strtolower( wfWikiID() )`)
* `$wgWikiSearchDefaultResultLimit`: Sets the number of results to return when no explicit limit is given (defaults to `10`)
* `$wgWikiSearchHighlightFragmentSize`: Sets the maximum number of characters in the highlight fragment (defaults to `250`)
* `$wgWikiSearchHighlightNumberOfFragments`: Sets the maximum number of highlight fragments to return per result (defaults to `1`)
* `$wgWikiSearchElasticSearchHosts`: Sets the list of ElasticSearch hosts to use (defaults to `["localhost:9200"]`)
* `$wgWikiSearchAPIRequiredRights`: Sets the list of rights required to query the WikiSearch API (defaults to `["read", "wikisearch-execute-api"]`)
* `$wgWikiSearchSearchFieldOverride`: Sets the search page to redirect to when using Special:Search. The user is redirected to the specified wiki article with the query parameter `search_query` specified through the search page if it is available. Does not change the behaviour of the search snippets shown when using the inline search field.
* `$wgWikiSearchMaxChainedQuerySize`: Sets the maximum number of results to retrieve for a chained property query (defaults to `1000`). Setting this to an extreme value may cause ElasticSearch to run out of memory when performing a large chained query.

### Debug mode

To enable debug mode, set `$wgWikiSearchEnableDebugMode` to `true`.

## Parser functions

WikiSearch defines two parser functions.

### `#WikiSearchConfig` (case-sensitive)

The `#WikiSearchConfig` parser function is used to set several configuration variables that cannot be passed to the API for security
reasons. It sets the search condition for that page, the list of facet properties, and the list of result properties.

```
{{#WikiSearchConfig:
  |<facet property>
  |?<result property>
}}
```

```
{{#WikiSearchConfig:
  |Version
  |Tag
  |Space
  |?Title
  |?Version
}}
```

Note: Only one call to `#WikiSearchConfig` is allowed per page. Multiple calls will result in unexpected behaviour.

#### Search parameters

Certain configuration parameters can also be given through the search engine config. This section documents these parameters and their
behaviour.

##### `base query`

The `base query` configuration parameter can be used to add a base query to the search. This base query is given as a Semantic MediaWiki query. A
document will only be included in the search if it matched both the base query and the generated query.

##### `highlighted properties`

The `highlighted properties` configuration parameter can be used to specify alternate properties that should be highlighted. Please note that these
properties do need to be part of the search space.

##### `search term properties`

The `search term properties` configuration parameter can be used to specify alternate properties to search through when doing a free-text search. These
properties may also be chained properties.

A weight can be added to each field in the search term properties by using the `^%d` syntax. For example, to give additional weight to the title, you can do the following:

```
|search term properties=Title^10,Content^2,Pdf.attachment-content
```

The weight determines the ranking when sorting on relevance. A match in a field with a higher weight will count more towards the relevance score than a match in a field with a lower weight. When no weight is given, the weight is set to `1`.

##### `default operator`

The `default operator` configuration parameter can be used to change the default operator of the free-text search. The default operator inserted between
each term is `or` and this configuration parameters allows the administrator to change that to an `and` if required.

##### `post filter properties`

The `post filter properties` configuration parameter can be used to specify which filters should be added as a post filter instead
of a regular filter. This parameter takes a comma-separated list of property names. Each filter that applies to any of the given property names
will be added as a post filter. The difference between post filters and regular filters is explained [here](https://www.elastic.co/guide/en/elasticsearch/reference/6.8/search-request-post-filter.html).
This configuration parameter is especially useful when you have disjunct checkbox properties.

### `#WikiSearchFrontend` (case-sensitive)

The `#WikiSearchFrontend` parser function is used to load the frontend. The parameters and return value of this parser function
depend completely on the frontend.

## Installation

* Download and place the file(s) in a directory called WikiSearch in your extensions/ folder.
* Add the following code at the bottom of your LocalSettings.php:
  * wfLoadExtension( 'WikiSearch' );
* Run the update script which will automatically create the necessary database tables that this extension needs.
* Run Composer.
* Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

## Copyright

Faceted search for MediaWiki.
Copyright (C) 2021 Marijn van Wezel, Robis Koopmans

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
