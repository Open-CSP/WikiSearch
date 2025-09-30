# WikiSearch

This document describes how to use the WikiSearch API. For a more beginner-friendly introduction to WikiSearch, you should read the documentation on the [MediaWiki extension page](https://www.mediawiki.org/wiki/Extension:WikiSearch).

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

### `#wikisearchconfig`

The `#wikisearchconfig` parser function is used to set several configuration variables that cannot be passed to the API for security
reasons. It sets the search condition for that page, the list of facet properties, and the list of result properties.

```
{{#wikisearchconfig:
  |<facet property>
  |?<result property>
}}
```

```
{{#wikisearchconfig:
  |Version
  |Tag
  |Space
  |?Title
  |?Version
}}
```

> [!CAUTION]
> Only one call to `#wikisearchconfig` is allowed per page. Multiple calls will result in unexpected behaviour.

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

### `#wikisearchfrontend` (case-sensitive)

The `#wikisearchfrontend` parser function is used to load the frontend. The parameters and return value of this parser function
depend completely on the frontend.

## Installation

* Download and place the file(s) in a directory called `WikiSearch` in your `extensions/` folder.
* Add the following code at the bottom of your `LocalSettings.php`:
  * `wfLoadExtension( 'WikiSearch' );`
* Run the update script which will automatically create the necessary database tables that this extension needs.
* Add the following dependencies to your `composer.local.json`:
  * [`elasticsearch/elasticsearch`](https://packagist.org/packages/elasticsearch/elasticsearch), with a version constraint matching your ElasticSearch version.
  * [`handcraftedinthealps/elasticsearch-dsl`](https://packagist.org/packages/handcraftedinthealps/elasticsearch-dsl), with a version constraint matching your ElasticSearch version
* Run `composer update --no-dev` in the root of your MediaWiki installation.
* Navigate to `Special:Version` on your wiki to verify that the extension is successfully installed.
