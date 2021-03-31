<?php


namespace WSSearch;

use WSSearch\QueryEngine\Factory\AggregationFactory;
use WSSearch\QueryEngine\Factory\FilterFactory;
use WSSearch\QueryEngine\Factory\SortFactory;

class SearchEngineFactory {
    /**
     * @var SearchEngine
     */
    private $engine;

    /**
     * SearchEngineFactory constructor.
     *
     * @param SearchEngineConfig $config The SearchEngineConfig to construct the engine from
     * @throws SearchEngineException
     */
    public function __construct( SearchEngineConfig $config ) {
        $this->engine = SearchEngine::instantiate( $config );
    }

    /**
     * Constructs a new QueryEngine from the API endpoint parameters.
     *
     * @param string|null $term
     * @param string|null $from
     * @param string|null $limit
     * @param string|null $filters
     * @param string|null $aggregations
     * @param string|null $sortings
     *
     * @return SearchEngine
     * @throws SearchEngineException
     */
    public function fromParameters( $term, $from, $limit, $filters, $aggregations, $sortings ): SearchEngine {
        if ( $term !== null ) {
            $this->setTerm( $term );
        }

        if ( $from !== null ) {
            $this->setFrom( $from );
        }

        if ( $limit !== null ) {
            $this->setLimit( $limit );
        }

        if ( $filters !== null ) {
            $this->setFilters( $filters );
        }

        if ( $aggregations !== null ) {
            $this->setAggregations( $aggregations );
        }

        if ( $sortings !== null ) {
            $this->setSortings( $sortings );
        }

        return $this->engine;
    }

    /**
     * Sets the search term field.
     *
     * @param string $term The search term to set
     */
    private function setTerm( string $term ) {
        $this->engine->addSearchTerm( $term );
    }

    /**
     * Sets the result offset.
     *
     * @param int $from
     */
    private function setFrom( int $from ) {
        $this->engine->getQueryEngine()->setOffset( $from );
    }

    /**
     * Sets the limit of the number of results to return.
     *
     * @param int $limit
     */
    private function setLimit( int $limit ) {
        $this->engine->getQueryEngine()->setLimit( $limit );
    }

    /**
     * Applies the given filters to the query.
     *
     * @param string $filters JSON-encoded string of the filter parameter
     * @throws SearchEngineException
     */
    private function setFilters( string $filters ) {
        $filters = json_decode( $filters, true );

        if ( !is_array( $filters ) || json_last_error() !== JSON_ERROR_NONE ) {
            throw new SearchEngineException( wfMessage( "wssearch-api-invalid-json", "filter", json_last_error_msg() ) );
        }

        $filters = array_map( [ FilterFactory::class, "fromArray" ], $filters );
        $all_filters_valid = count( array_filter( $filters, "is_null" ) ) === 0;

        if ( !$all_filters_valid ) {
            throw new SearchEngineException( wfMessage( "wssearch-invalid-filter" ) );
        }

        foreach ( $filters as $filter ) {
            $this->engine->getQueryEngine()->addConstantScoreFilter( $filter );
        }
    }

    /**
     * Applies the given aggregations to the query.
     *
     * @param string $aggregations
     * @throws SearchEngineException
     */
    private function setAggregations( string $aggregations ) {
        $aggregations = json_decode( $aggregations, true );

        if ( !is_array( $aggregations ) || json_last_error() !== JSON_ERROR_NONE ) {
            throw new SearchEngineException( wfMessage( "wssearch-api-invalid-json", "aggregations", json_last_error_msg() ) );
        }

        $aggregations = array_map( [ AggregationFactory::class, "fromArray" ], $aggregations );
        $all_aggregations_valid = count( array_filter( $aggregations, "is_null" ) ) === 0;

        if ( !$all_aggregations_valid ) {
            throw new SearchEngineException( wfMessage( "wssearch-invalid-aggregation" ) );
        }

        foreach ( $aggregations as $aggregation ) {
            $this->engine->getQueryEngine()->addAggregation( $aggregation );
        }
    }

    /**
     * Applies the given sortings to the query.
     *
     * @param string $sortings
     * @throws SearchEngineException
     */
    private function setSortings( string $sortings ) {
        $sortings = json_decode( $sortings, true );

        if ( !is_array( $sortings ) || json_last_error() !== JSON_ERROR_NONE ) {
            throw new SearchEngineException( wfMessage( "wssearch-api-invalid-json", "sortings", json_last_error_msg() ) );
        }

        $sortings = array_map( [ SortFactory::class, "fromArray" ], $sortings );
        $all_sortings_valid = count( array_filter( $sortings, "is_null" ) ) === 0;

        if ( !$all_sortings_valid ) {
            throw new SearchEngineException( wfMessage( "wssearch-invalid-sort" ) );
        }

        foreach ( $sortings as $sort ) {
            $this->engine->getQueryEngine()->addSort( $sort );
        }
    }
}