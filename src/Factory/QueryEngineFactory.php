<?php

namespace WikiSearch\Factory;

use WikiSearch\QueryEngine\Aggregation\PropertyValueAggregation;
use WikiSearch\QueryEngine\Highlighter\DefaultHighlighter;
use WikiSearch\QueryEngine\QueryEngine;
use WikiSearch\SearchEngineConfig;

class QueryEngineFactory {
    public function __construct( private \Config $config ) {}

    /**
     * Constructs a new QueryEngine.
     *
     * @param SearchEngineConfig|null $config
     * @return QueryEngine
     */
	public function newQueryEngine( ?SearchEngineConfig $config = null ): QueryEngine {
        $queryEngine = new QueryEngine( $this->getIndex() );

        if ( $config === null ) {
            return $queryEngine;
        }

        $aggregation_size = $config->getSearchParameter( "aggregation size" );

        foreach ( $config->getFacetProperties() as $facet_property ) {
            $aggregation = new PropertyValueAggregation(
                $facet_property,
                null,
                $aggregation_size
            );

            $queryEngine->addAggregation( $aggregation );
        }

        foreach ( $config->getResultProperties() as $result_property ) {
            // Include this property and any sub-properties in the result
            $source = $result_property->getPID() . ".*";

            $queryEngine->addSource( $source );
        }

        // Configure the base query
        if ( $config->getSearchParameter( "base query" ) !== null ) {
            $queryEngine->setBaseQuery( $config->getSearchParameter( "base query" ) );
        }

        // Configure the highlighter
        $queryEngine->addHighlighter( new DefaultHighlighter( $config ) );

        return $queryEngine;
	}

    private function getIndex(): string {
        if ( class_exists( '\MediaWiki\WikiMap\WikiMap' ) ) {
            // MW 1.40+
            $wikiMap = \MediaWiki\WikiMap\WikiMap::getCurrentWikiId();
        } else {
            $wikiMap = \WikiMap::getCurrentWikiId();
        }

        return $this->config->get( "WikiSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( $wikiMap );
    }
}
