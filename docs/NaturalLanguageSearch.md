# Natural Language Search

WikiSearch supports natural language (semantic) search powered by OpenSearch's Neural Search plugin and ML Commons. Instead of matching keywords, it converts queries and document content into vector embeddings and finds semantically similar results.

## Requirements

- **OpenSearch 2.4+** (not plain Elasticsearch — the `neural` query type and ML Commons are OpenSearch-specific)
- The **ML Commons** and **Neural Search** plugins, which are bundled with OpenSearch by default
- **Semantic MediaWiki** with the ElasticStore backend already working

---

## Step 1 — Enable raw text indexing in SMW

SMW does not index the full page text by default. Add the following to `LocalSettings.php`:

```php
$smwgElasticsearchConfig['indexer']['raw.text'] = true;
```

This causes SMW to populate the `text_raw` field, which is the field WikiSearch embeds for semantic search.

---

## Step 2 — Register an embedding model

WikiSearch's default configuration expects **384-dimensional embeddings**, which matches the `all-MiniLM-L6-v2` model. Register it from OpenSearch's model registry:

```bash
POST /_plugins/_ml/models/_register
{
  "name": "huggingface/sentence-transformers/all-MiniLM-L6-v2",
  "version": "1.0.1",
  "model_format": "TORCH_SCRIPT"
}
```

This returns a task ID:

```json
{ "task_id": "abc123", "status": "CREATED" }
```

> **If this fails with an error about ML nodes**, your cluster has ML Commons installed but is configured to only run ML tasks on dedicated ML nodes. Try setting:
>
> ```bash
> PUT /_cluster/settings
> {
>   "persistent": {
>     "plugins.ml_commons.only_run_on_ml_nodes": "false"
>   }
> }
> ```
>
> If OpenSearch reports that setting as "not recognised", ML Commons is not installed or your distribution does not support it — neural search is not available on your cluster.

---

## Step 3 — Wait for registration to complete

Poll until `state` is `COMPLETED`:

```bash
GET /_plugins/_ml/tasks/abc123
```

When done, note the `model_id`:

```json
{ "state": "COMPLETED", "model_id": "xyz789" }
```

---

## Step 4 — Deploy the model

```bash
POST /_plugins/_ml/models/xyz789/_deploy
```

Poll the returned task ID until `state` is `COMPLETED` before continuing.

---

## Step 5 — Configure WikiSearch

Add the following to `LocalSettings.php`:

```php
$wgWikiSearchNaturalLanguageSearch = true;
$wgWikiSearchNeuralSearchModelId   = 'xyz789'; // model_id from Step 3
```

If you are using a model with a different embedding dimension than 384, also set:

```php
$wgWikiSearchNeuralSearchDimension = 768; // adjust to match your model
```

---

## Step 6 — Rebuild the index

Delete the existing SMW index and rebuild it. WikiSearch automatically creates the required OpenSearch index template (which sets `index.knn` and registers the embedding field) during the MediaWiki bootstrap that runs at the start of the rebuild script.

First find the exact index name:

```bash
GET /_cat/indices?v
```

Then delete and rebuild:

```bash
curl -X DELETE http://localhost:9200/smw-data-<wikiid>-v1

php maintenance/run.php SMW\Maintenance\RebuildElasticIndex
```

Documents are automatically embedded via the ingest pipeline as they are indexed during the rebuild. No separate backfill step is needed for a clean rebuild.

---

## Verification

After the rebuild, confirm that documents have embeddings:

```bash
GET /smw-data-<wikiid>-v2/_search
{
  "size": 1,
  "_source": ["text_raw", "wikisearch_embedding"],
  "query": { "exists": { "field": "wikisearch_embedding" } }
}
```

Both `text_raw` (the source text) and `wikisearch_embedding` (a 384-element float array) should be present in the result.

---

## Troubleshooting

### Zero search results

Run the verification query above. If `wikisearch_embedding` is missing from documents, the ingest pipeline was not active during indexing. Backfill by running the pipeline over existing documents:

```bash
POST /smw-data-<wikiid>-v2/_update_by_query?pipeline=wikisearch-neural-ingest&wait_for_completion=false
```

Poll the returned task ID with `GET /_tasks/<task_id>` until complete.

### `text_raw` field is empty / missing

Ensure `$smwgElasticsearchConfig['indexer']['raw.text'] = true` is set in `LocalSettings.php` and that you have fully rebuilt the index after adding it.

### `index.knn` setting error during rebuild

WikiSearch installs an OpenSearch index template (`wikisearch-neural-knn`) at bootstrap time that applies `index.knn: true` to new indices. If the template is missing, force it to be recreated by deleting it and triggering a fresh bootstrap:

```bash
curl -X DELETE http://localhost:9200/_template/wikisearch-neural-knn
php maintenance/run.php WikiSearch:SetupNeuralSearch
```

Then delete and rebuild the index.

### Changing the model

If you change `$wgWikiSearchNeuralSearchModelId` or `$wgWikiSearchNeuralSearchDimension`, you must:

1. Delete the old index template and pipeline so they are recreated with the new model:
   ```bash
   curl -X DELETE http://localhost:9200/_template/wikisearch-neural-knn
   curl -X DELETE http://localhost:9200/_ingest/pipeline/wikisearch-neural-ingest
   ```
2. Delete and rebuild the index (Step 6).
