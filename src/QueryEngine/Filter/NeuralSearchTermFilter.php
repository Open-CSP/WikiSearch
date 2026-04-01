<?php

namespace WikiSearch\QueryEngine\Filter;

use MediaWiki\MediaWikiServices;
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
	private const DEFAULT_K = 20;

    /**
     * @var string
     */
    private $queryText;

    /**
     * @var string
     */
    private $modelId;

    /**
	 * @param string $queryText The raw natural language query text
	 */
	public function __construct( string $queryText ) {
        $this->queryText = $queryText;
        $this->modelId = MediaWikiServices::getInstance()
            ->getMainConfig()
            ->get( 'WikiSearchNeuralSearchModelId' );
	}

	/**
	 * @inheritDoc
	 */
	protected function filterToQuery(): BoolQuery {
		$neuralQuery = new RawQuery( [
			'neural' => [
				'text_raw_embedding' => [
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
