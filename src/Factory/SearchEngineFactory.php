<?php

namespace WikiSearch\Factory;

use WikiSearch\QueryEngine\Aggregation\AbstractAggregation;
use WikiSearch\QueryEngine\Filter\Filter;
use WikiSearch\QueryEngine\Sort\Sort;
use WikiSearch\SearchEngine;
use WikiSearch\SearchEngineConfig;

class SearchEngineFactory {
    private QueryEngineFactory $queryEngineFactory;

    public function __construct(QueryEngineFactory $queryEngineFactory ) {
        $this->queryEngineFactory = $queryEngineFactory;
    }

    /**
     * Constructs a new QueryEngine from the API endpoint parameters.
     *
     * @param SearchEngineConfig $searchEngineConfig
     * @param string|null $term
     * @param int|null $from
     * @param int|null $limit
     * @param Filter[] $filters
     * @param AbstractAggregation[] $aggregations
     * @param Sort[] $sorts
     *
     * @return SearchEngine
     */
	public function newSearchEngine(
        SearchEngineConfig $searchEngineConfig,
        ?string $term,
        ?int $from,
        ?int $limit,
        array $filters,
        array $aggregations,
        array $sorts
    ): SearchEngine {
        $queryEngine = $this->queryEngineFactory->newQueryEngine( $searchEngineConfig );

		if ( $from !== null ) {
			$queryEngine->setOffset( $from );
		}

		if ( $limit !== null ) {
			$queryEngine->setLimit( $limit );
		}

        foreach ( $filters as $filter ) {
            $queryEngine->addConstantScoreFilter( $filter );
        }

        foreach ( $aggregations as $aggregation ) {
            $queryEngine->addAggregation( $aggregation );
        }

        foreach( $sorts as $sort ) {
            $queryEngine->addSort( $sort );
        }

        $searchEngine = new SearchEngine( $searchEngineConfig, $queryEngine );

        if ( $term !== null ) {
            $searchEngine->addSearchTerm( $term );
        }

		return $searchEngine;
	}
}
