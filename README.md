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
    * `getFacetPropertyIDs(): array`: Returns a key-value pair list where the key is the name of the facet property and the value the ID of that property
    * `getResultProperties(): array`: Returns the result properties in the config as PropertyInfo objects (result properties are the properties prefixed with `?`)
    * `getResultPropertyIDs(): array`: Returns a key-value pair list where the key is the name of the result property and the value the ID of that property
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
* `dates`: The aggregate date ranges to allow
* `term`: The term to search for
* `from`: The result offset
* `limit`: The maximum number of results to return

An example API call looks like this:

`https://pidsdev02.wikibase.nl/api.php?action=query&format=json&meta=WSSearch&pageid=1&filter=[{%22value%22:%222021%22,%22key%22:%22Date%22,%22range%22:{%22P:29.datField%22:{%22gte%22:2459209,%22lte%22:2459574}}},{%22value%22:%22Last+month%22,%22key%22:%22Date%22,%22range%22:{%22P:29.datField%22:{%22gte%22:2459205,%22lte%22:2459236}}},{%22value%22:%223500%22,%22key%22:%22Namespace%22},{%22value%22:%22Admin%22,%22key%22:%22User%22}]&term=e&from=0&dates=[{%22key%22:%22Last+Week%22,%22from%22:2459229,%22to%22:2459236},{%22key%22:%22Last+month%22,%22from%22:2459205,%22to%22:2459236},{%22key%22:%22Last+Quarter%22,%22from%22:2459144,%22to%22:2459236},{%22key%22:%222021%22,%22from%22:2459209,%22to%22:2459574},{%22key%22:%222020%22,%22from%22:2458844,%22to%22:2459209},{%22key%22:%222019%22,%22from%22:2458479,%22to%22:2458844},{%22key%22:%222018%22,%22from%22:2458114,%22to%22:2458479},{%22key%22:%222017%22,%22from%22:2457749,%22to%22:2458114},{%22key%22:%222016%22,%22from%22:2457384,%22to%22:2457749},{%22key%22:%222015%22,%22from%22:2457019,%22to%22:2457384},{%22key%22:%222014%22,%22from%22:2456654,%22to%22:2457019},{%22key%22:%222013%22,%22from%22:2456289,%22to%22:2456654},{%22key%22:%222012%22,%22from%22:2455924,%22to%22:2456289},{%22key%22:%222011%22,%22from%22:2455559,%22to%22:2455924},{%22key%22:%222010%22,%22from%22:2455194,%22to%22:2455559},{%22key%22:%222009%22,%22from%22:2454829,%22to%22:2455194},{%22key%22:%222008%22,%22from%22:2454464,%22to%22:2454829},{%22key%22:%222007%22,%22from%22:2454099,%22to%22:2454464},{%22key%22:%222006%22,%22from%22:2453734,%22to%22:2454099},{%22key%22:%222005%22,%22from%22:2453369,%22to%22:2453734},{%22key%22:%222004%22,%22from%22:2453004,%22to%22:2453369},{%22key%22:%222003%22,%22from%22:2452639,%22to%22:2453004},{%22key%22:%222002%22,%22from%22:2452274,%22to%22:2452639},{%22key%22:%222001%22,%22from%22:2451909,%22to%22:2452274}]`

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
      },
      "Date": {
        "buckets": [
          {
            "key": "2001",
            "from": 2451909,
            "to": 2452274,
            "doc_count": 0
          },
          {
            "key": "2002",
            "from": 2452274,
            "to": 2452639,
            "doc_count": 0
          },
          {
            "key": "2003",
            "from": 2452639,
            "to": 2453004,
            "doc_count": 0
          },
          {
            "key": "2004",
            "from": 2453004,
            "to": 2453369,
            "doc_count": 0
          },
          {
            "key": "2005",
            "from": 2453369,
            "to": 2453734,
            "doc_count": 0
          },
          {
            "key": "2006",
            "from": 2453734,
            "to": 2454099,
            "doc_count": 0
          },
          {
            "key": "2007",
            "from": 2454099,
            "to": 2454464,
            "doc_count": 0
          },
          {
            "key": "2008",
            "from": 2454464,
            "to": 2454829,
            "doc_count": 0
          },
          {
            "key": "2009",
            "from": 2454829,
            "to": 2455194,
            "doc_count": 0
          },
          {
            "key": "2010",
            "from": 2455194,
            "to": 2455559,
            "doc_count": 0
          },
          {
            "key": "2011",
            "from": 2455559,
            "to": 2455924,
            "doc_count": 0
          },
          {
            "key": "2012",
            "from": 2455924,
            "to": 2456289,
            "doc_count": 0
          },
          {
            "key": "2013",
            "from": 2456289,
            "to": 2456654,
            "doc_count": 0
          },
          {
            "key": "2014",
            "from": 2456654,
            "to": 2457019,
            "doc_count": 0
          },
          {
            "key": "2015",
            "from": 2457019,
            "to": 2457384,
            "doc_count": 0
          },
          {
            "key": "2016",
            "from": 2457384,
            "to": 2457749,
            "doc_count": 0
          },
          {
            "key": "2017",
            "from": 2457749,
            "to": 2458114,
            "doc_count": 0
          },
          {
            "key": "2018",
            "from": 2458114,
            "to": 2458479,
            "doc_count": 0
          },
          {
            "key": "2019",
            "from": 2458479,
            "to": 2458844,
            "doc_count": 0
          },
          {
            "key": "2020",
            "from": 2458844,
            "to": 2459209,
            "doc_count": 0
          },
          {
            "key": "Last Quarter",
            "from": 2459144,
            "to": 2459236,
            "doc_count": 0
          },
          {
            "key": "Last month",
            "from": 2459205,
            "to": 2459236,
            "doc_count": 0
          },
          {
            "key": "2021",
            "from": 2459209,
            "to": 2459574,
            "doc_count": 0
          },
          {
            "key": "Last Week",
            "from": 2459229,
            "to": 2459236,
            "doc_count": 0
          }
        ]
      }
    }
  }
}
```