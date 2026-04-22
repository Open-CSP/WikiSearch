<?php

namespace WikiSearch\QueryEngine\Filter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\BoostingQuery;
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
	private const DEFAULT_K = 50;

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
        $queries = [
            $this->searchTermFilter->filterToQuery()->toArray(),
            $this->generateEmbeddedPropertiesQuery()->toArray(),
        ];

        $query = [
            "hybrid" => [
                "queries" => $queries
            ]
        ];

        return new RawQuery( $query );
	}

    /**
     * @return BoolQuery
     */
    private function generateEmbeddedPropertiesQuery(): BoolQuery {
        $query = new BoolQuery();

        foreach ( $this->embeddedProperties as $embeddedProperty ) {
            $neuralQuery = new RawQuery( $this->generateNeuralQuery( $embeddedProperty ));
            $query->add( $neuralQuery, BoolQuery::SHOULD );
        }

        return $query;
    }

    /**
     * @param string $property
     * @return \array[][]
     */
    private function generateNeuralQuery( string $property ): array {
        $propertyFieldMapper = new PropertyFieldMapper( $property );
        $boost = $propertyFieldMapper->getPropertyWeight();

        return [
            'neural' => [
                $propertyFieldMapper->getEmbeddingField() => [
                    'query_text' => $this->queryText,
                    'model_id'   => $this->embeddingModelId,
                    'k'          => self::DEFAULT_K,
                    'boost'      => $boost,
                ],
            ]
        ];
    }
}
