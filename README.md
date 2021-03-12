# WSSearch

## Hooks

### `WSSearchBeforeElasticQuery`
This hook is called right before the query is sent to ElasticSearch. It has the following signature:

```php
function onWSSearchBeforeElasticQuery( array &$query, array &$hosts ) {}
```

The hook has access to and can alter the given `$query`. It can also add or remove hosts from the
`$hosts` array.

### `WSSearchApplyResultTranslations`
This hook is called right before returning the final results to the API. It can be used
to alter the `$results` array. This can be useful to filter any pages the user is not allowed
to see or add additional data to the query result.

It has the following signature:

```php
function onWSSearchApplyResultTranslations( array &$results ) {}
```

### `WSSearchPermissionCheck`
This hook is called when determining whether a page should be visible in the ElasticSearch query results. It
has the following signature:

```php
function onWSSearchPermissionCheck( Revision $revision, Title $title, bool &$is_allowed ) {}
```

* `Revision $revision`: The Revision we are evaluating
* `Title $title`: The Title object associated with the Revision
* `bool &$is_allowed`: Set to `false` to hide the page from the search results, leave unchanged otherwise

### `WSSearchOnLoadFrontend`
This hook must be implemented by any WSSearch frontend. It gets called when the `#loadSeachEngine` parser function
is called. It has the following signature:

```php
function onWSSearchOnLoadFrontend( string &$result, \WSSearch\SearchEngineConfig $config, Parser $parser, array $parameters ) {}
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

WSSearch has several configuration variables that influence its default behaviour.

* `$wgWSSearchElasticStoreIndex`: Sets the name of the ElasticStore index to use (defaults to `"smw-data-" . strtolower( wfWikiID() )`)
* `$wgWSSearchDefaultResultLimit`: Sets the number of results to return when no explicit limit is given (defaults to `10`)
* `$wgWSSearchHighlightFragmentSize`: Sets the maximum number of characters in the highlight fragment (defaults to `150`)
* `$wgWSSearchHighlightNumberOfFragments`: Sets the maximum number of highlight fragments to return per result (defaults to `1`)
* `$wgWSSearchElasticSearchHosts`: Sets the list of ElasticSearch hosts to use (defaults to `["localhost:9200"]`)
* `$wgWSSearchAPIRequiredRights`: Sets the list of rights required to query the WSSearch API (defaults to `["read", "wssearch-execute-api"]`)
* `$wgWSSearchSearchFieldOverride`: Sets the search page to redirect to when using Special:Search. The user is redirected to the specified wiki article with the query parameter `search_query` specified through the search page if it is available. Does not change the behaviour of the search snippets shown when using the inline search field.

## Parser functions

WSSearch defines two parser functions.

### `#searchEngineConfig` (case-sensitive)
The `#searchEngineConfig` parser function is used to set several configuration variables that cannot be passed to the API for security
reasons. It sets the search condition for that page, the list of facet properties, and the list of result properties.

```
{{#searchEngineConfig: <condition>
  |<facet property>
  |?<result property>
}}
```

```
{{#searchEngineConfig: Class=Foobar
  |Version
  |Tag
  |Space
  |?Title
  |?Version
}}
```

Note: Only one call to `#searchEngineConfig` is allowed per page. Multiple calls will result in unexpected behaviour.

### `#loadSearchEngine` (case-sensitive)
The `#loadSearchEngine` parser function is used to load the frontend. The parameters and return value of this parser function
depend completely on the frontend.

## API
The API has the following parameters:

* `pageid`: The page ID to use for retrieving the appropriate `searchEngineConfig`
* `filter`: The ElasticSearch filters to apply
* `aggregations`: The aggregations to add to the query
* `term`: The term to search for
* `from`: The result offset
* `limit`: The maximum number of results to return

An example API call looks like this:

`https://pidsdev02.wikibase.nl/api.php?action=query&format=json&meta=WSSearch&pageid=1&filter=[{%22value%22:%222021%22,%22key%22:%22Date%22,%22range%22:{%22P:29.datField%22:{%22gte%22:2459209,%22lte%22:2459574}}},{%22value%22:%22Last+month%22,%22key%22:%22Date%22,%22range%22:{%22P:29.datField%22:{%22gte%22:2459205,%22lte%22:2459236}}},{%22value%22:%223500%22,%22key%22:%22Namespace%22},{%22value%22:%22Admin%22,%22key%22:%22User%22}]&term=e&from=0`

This resulted in the following:

```json
{
  "batchcomplete": "",
  "result": {
    "total": 0,
    "hits": [],
    "aggs": {
      "Foo": {
        "doc_count_error_upper_bound": 0,
        "sum_other_doc_count": 0,
        "buckets": []
      }
    }
  }
}
```

### Filters syntax

The `filters` parameter takes a list of objects. These objects have the following form:

#### PropertyRangeFilter

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

```
{
    "key": "Class",
    "value": "Manual"
}
```

The above filter only includes pages where the property `Class` has the value `Manual`.

See also: https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-range-query.html

### Aggregations syntax

The `aggregations` parameter takes a list of objects. These objects have the following form:

See also: https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-term-query.html

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