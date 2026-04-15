# Natural Language Search

WikiSearch supports natural language (semantic) search powered by OpenSearch's Neural Search plugin and ML Commons. 
Instead of matching keywords, it converts queries and document content into vector embeddings and finds semantically
similar results.

WikiSearch uses a hybrid search approach for natural language search, using the
[`hybrid`](https://docs.opensearch.org/latest/vector-search/ai-search/hybrid-search/index/) search strategy from
OpenSearch. This means that it combines both the results of a regular search term query with one or more natural language
search queries.

For example, it may generate a query like so:

```json
{
  "hybrid": {
    "queries": [
      {
        "bool": {
          "should": [
            {
              "query_string": {
                "query": "*homebrew*",
                "fields": [
                  "subject.title.search^8",
                  "subject.title^8",
                  "text_copy.search^5",
                  "text_copy^5",
                  "text_raw.search",
                  "text_raw",
                  "attachment.title^3",
                  "attachment.content"
                ],
                "default_operator": "or",
                "analyze_wildcard": true,
                "tie_breaker": 1,
                "lenient": true
              }
            }
          ]
        }
      },
      {
        "bool": {
          "should": [
            {
              "neural": {
                "subject.title:embedding": {
                  "query_text": "homebrew",
                  "model_id": "cLpTT50BXjmMqUCTslRr",
                  "k": 50
                }
              }
            },
            {
              "neural": {
                "text_raw:embedding": {
                  "query_text": "homebrew",
                  "model_id": "cLpTT50BXjmMqUCTslRr",
                  "k": 50
                }
              }
            },
            {
              "neural": {
                "attachment.content:embedding": {
                  "query_text": "homebrew",
                  "model_id": "cLpTT50BXjmMqUCTslRr",
                  "k": 50
                }
              }
            }
          ]
        }
      }
    ]
  }
}
```

## Limitations

The neural search currently has a few limitations:

- When a document is only matched by the neural search query, no highlights will be generated.
- It is not possible to boost the relevance of certain fields.
- It is only possible to use fields that have an `<fieldname>:embedding` variant (as specified in the data standard).
- You can only use the neural search on certain properties (see step 2b).

## Requirements

- **OpenSearch 2.4 or higher** (not plain Elasticsearch — the `neural` query type and ML Commons are OpenSearch-specific)
- The **OpenSearch PHP Client** ([`opensearch-project/opensearch-php`](https://packagist.org/packages/opensearch-project/opensearch-php)), not the ElasticSearch client
- The **ML Commons** and **Neural Search** plugins, which are bundled with OpenSearch by default

---

## Step 0 - Configure the embedding properties

You must first configure which properties you want to use for embedding. These are the properties for which embeddings
will be generated, and which will be used by the neural search. By default, only `text_raw`, `subject.title` and
`attachment.content` are used.

```php
$wgWikiSearchNeuralEmbeddedProperties = ['Parsed text'];
```

In step 2b, you must also add these properties to the data standard. Each time you update these properties, you should
follow the steps in this guide again.

You may also specify the weight of the properties using this configuration option, for example:

```php
$wgWikiSearchNeuralEmbeddedProperties = ['subject-title^50', 'Parsed text'];
```

## Step 1 - Run the initialization script

Run the initialization script:

```bash
php maintenance/run.php ./extensions/WikiSearch/maintenance/setupNeuralSearch
```

This initialization script does the following:

- It ensures you are running a compatible version of OpenSearch.
- It configures OpenSearch to run machine learning algorithms on non-ML nodes.
- It creates and deploys a new embedding model, if none is already configured.
- It (re)creates an embedding pipeline for document ingestion.

The initialization script will return the ID of the model to use for embedding, if no model has yet been configured.
**Write down the returned model ID, you will need it in step 3.** You may reuse this ID for multiple wikis that connect
to the same OpenSearch instance.

## Step 2 - Configure the data standard and enable raw text

Copy the data standard template `smw-wikisearch-data-vector-embeddings-template.json` from the `data_templates` folder
to somewhere else, and add the following to your `LocalSettings.php`:

```php
$smwgElasticsearchConfig['index_def']['data'] = '/path/to/smw-wikisearch-data-vector-embeddings.json';
$smwgElasticsearchConfig['indexer']['raw.text'] = true;
```

Feel free to modify the data standard template to better suit your needs. This is necessary if you are using custom
embedding properties (see step 2b below).

## Step 2b - Tweak the data standard

The default embeddings data standard template of WikiSearch adds embedding fields for `text_raw`, `subject.title` and
`attachment.content` (see step 0). If you want to use other properties than those three in your neural search, you must
manually add them to the data standard.

For example, to generate embeddings for `Parsed text`, you must first look up the ID of the `Parsed text` property:

```bash
php maintenance/run.php ./extensions/WikiSearch/maintenance/propertyLookup --property="Parsed text"
```

Copy this ID, and add an entry under `mappings` then `properties` in the data standard:

```json
{
  "settings": "...",
  "mappings": {
    "...":  "...",
    "properties": {
      "...":  "...",
      // Add this to the data standard
      "P:<ID>:embedding": {
        "type": "knn_vector",
        "dimension": 384,
        "method": {
          "name": "hnsw",
          "space_type": "cosinesimil",
          "engine": "lucene"
        }
      }
    }
  }
}
```

## Step 3 - Configure WikiSearch

Add the following to `LocalSettings.php`:

```php
$wgWikiSearchEnableNeuralSearch = true;
$wgWikiSearchNeuralModels['embedding'] = '<ID of the embedding model from step 1>';
```

## Step 4 - Patch Semantic MediaWiki

Apply the `smw.patch` (located in this directory) to Semantic MediaWiki:

```bash
cd extensions/SemanticMediaWiki
git apply ../WikiSearch/docs/smw.patch
```

> Note: This requires Semantic MediaWiki to be installed using Git. Look at the source of the patch to do it manually.

## Step 5 - Run maintenance scripts

Run the `extensions/SemanticMediaWiki/maintenance/rebuildElasticIndex.php` maintenance script.
