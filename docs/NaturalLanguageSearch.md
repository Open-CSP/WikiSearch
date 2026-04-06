# Natural Language Search

WikiSearch supports natural language (semantic) search powered by OpenSearch's Neural Search plugin and ML Commons. 
Instead of matching keywords, it converts queries and document content into vector embeddings and finds semantically
similar results.

## Requirements

- **OpenSearch 2.4+** (not plain Elasticsearch — the `neural` query type and ML Commons are OpenSearch-specific)
- The **ML Commons** and **Neural Search** plugins, which are bundled with OpenSearch by default

---

## Step 1 - Run the initialization script

Run the initialization script:

```bash
php maintenance/run.php ./extensions/WikiSearch/maintenance/setupNeuralSearch
```

The initialization script will return the ID of the model to use for embedding, if no model has yet been configured.
Write down the returned model ID. You may reuse this ID for multiple wikis that connect to the same ElasticSearch
instance.

## Step 2 - Configure the data standard

Copy the data standard template `smw-wikisearch-data-vector-embeddings-template.json` from the `data_templates` folder
to somewhere else, and add the following to your `LocalSettings.php`:

```php
$smwgElasticsearchConfig['index_def']['data'] = '/path/to/smw-wikisearch-data-vector-embeddings.json';
```

Feel free to modify the data standard template to better suit your needs.

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

## Step 5 - Run maintenance scripts

Run the `extensions/SemanticMediaWiki/maintenance/rebuildElasticIndex.php` maintenance script.
