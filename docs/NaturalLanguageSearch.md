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

## Step 2 — Run the initialization script

Run the initialization script:

```bash
php maintenance/run.php ./extensions/WikiSearch/maintenance/setupNeuralSearch
```

## Step 3 — Configure WikiSearch

Add the following to `LocalSettings.php`:

```php
$wgWikiSearchNaturalLanguageSearch = true;
```
