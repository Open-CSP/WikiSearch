<?php

namespace WikiSearch\QueryEngine\Filter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WikibaseSolutions\CypherDSL\Expressions\Procedures\Raw;
use WikiSearch\QueryEngine\Query\RawQuery;
use WikiSearch\SMW\PropertyFieldMapper;

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
     * @var array
     */
    private array $embeddedProperties;

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
        $this->embeddedProperties = MediaWikiServices::getInstance()
            ->getMainConfig()
            ->get( 'WikiSearchNeuralEmbeddedProperties' ) ?? [];
        $this->searchTermFilter = $searchTermFilter;
	}

	/**
	 * @inheritDoc
	 */
	public function filterToQuery(): BuilderInterface {
        $neuralBoolQuery = new BoolQuery();

        foreach ( $this->embeddedProperties as $embeddedProperty ) {
            $propertyFieldMapper = new PropertyFieldMapper( $embeddedProperty );
            $neuralQuery = new RawQuery( [
                'neural' => [
                    $propertyFieldMapper->getEmbeddingField() => [
                        'query_text' => $this->queryText,
                        'model_id'   => $this->embeddingModelId,
                        'k'          => self::DEFAULT_K,
                        'boost'      => $propertyFieldMapper->getPropertyWeight()
                    ],
                ]
            ] );
            $neuralBoolQuery->add( $neuralQuery, BoolQuery::SHOULD );
        }

        return new RawQuery( [
            "hybrid" => [
                "queries" => [
                    $this->searchTermFilter->filterToQuery()->toArray(),
                    $neuralBoolQuery->toArray(),
                ]
            ]
        ] );
	}
}
