<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WikiSearch\QueryEngine\Query\RawQuery;

/**
 * Replaces SearchTermFilter when natural language search is active.
 *
 * Instead of a QueryStringQuery, this filter emits an OpenSearch "neural" query
 * that generates a vector embedding for the query text at search time and
 * performs an approximate kNN lookup against the knn_vector field that was
 * populated by the text_embedding ingest pipeline.
 */
class NeuralSearchTermFilter extends AbstractFilter {
	/**
	 * Number of nearest neighbours to retrieve before other filters reduce the
	 * result set. Should be at least as large as the largest configured result
	 * page size to avoid prematurely cutting off relevant results.
	 */
	private const DEFAULT_K = 100;

	/**
	 * @param string $queryText  The raw natural language query text.
	 * @param string $modelId    The ML Commons model ID of the deployed embedding model.
	 * @param string $field      The knn_vector field to search (e.g. 'wikisearch_embedding').
	 */
	public function __construct(
		private string $queryText,
		private string $modelId,
		private string $field
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function filterToQuery(): BoolQuery {
		$neuralQuery = new RawQuery( [
			'neural' => [
				$this->field => [
					'query_text' => $this->queryText,
					'model_id'   => $this->modelId,
					'k'          => self::DEFAULT_K,
				],
			],
		] );

		$boolQuery = new BoolQuery();
		$boolQuery->add( $neuralQuery, BoolQuery::SHOULD );

		return $boolQuery;
	}
}
