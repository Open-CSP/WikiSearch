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
 * performs an approximate kNN lookup against the knn_vector field.
 */
class NeuralSearchTermFilter extends AbstractFilter {
	private const DEFAULT_K = 25;

    /**
     * @var string
     */
    private $queryText;

    /**
     * @var string|null
     */
    private $embeddingModelId;

    /**
     * @var SearchTermFilter
     */
    private $searchTermFilter;

    /**
	 * @param string $queryText The raw natural language query text
     * @param SearchTermFilter $searchTermFilter The search term filter to use for hybrid search
	 */
	public function __construct( string $queryText, SearchTermFilter $searchTermFilter ) {
        $this->queryText = $queryText;
        $this->embeddingModelId = MediaWikiServices::getInstance()
            ->getMainConfig()
            ->get( 'WikiSearchNeuralModels' )['embedding'] ?? null;
        $this->searchTermFilter = $searchTermFilter;
	}

	/**
	 * @inheritDoc
	 */
	protected function filterToQuery(): BoolQuery {
        $hybridQuery = new RawQuery( [
            "hybrid" => [
                "queries" => [
                    $this->searchTermFilter->filterToQuery()->toArray(),
                    [
                        'neural' => [
                            'text_raw_embedding' => [
                                'query_text' => $this->queryText,
                                'model_id'   => $this->embeddingModelId,
                                'k'          => self::DEFAULT_K,
                            ],
                        ]
                    ]
                ]
            ]
        ] );

		$boolQuery = new BoolQuery();
		$boolQuery->add( $hybridQuery );

		return $boolQuery;
	}
}
