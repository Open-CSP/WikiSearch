<?php

namespace WikiSearch\QueryEngine\Factory;

use MediaWiki\MediaWikiServices;
// Note: MW 1.40+ will have MediaWiki\WikiMap\WikiMap instead
use WikiMap;
use WikiSearch\QueryEngine\Aggregation\PropertyValueAggregation;
use WikiSearch\QueryEngine\Highlighter\DefaultHighlighter;
use WikiSearch\QueryEngine\QueryEngine;
use WikiSearch\SearchEngineConfig;

class QueryEngineFactory {
    /**
     * Constructs a new QueryEngine.
     *
     * @param SearchEngineConfig|null $config
     * @return QueryEngine
     */
	public static function newQueryEngine( ?SearchEngineConfig $config = null ): QueryEngine {
        $index = MediaWikiServices::getInstance()
            ->getMainConfig()
            ->get( "WikiSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( WikiMap::getCurrentWikiId() );

        $queryEngine = new QueryEngine( $index );

        if ( $config === null ) {
            return $queryEngine;
        }

        $aggregation_size = $config->getSearchParameter( "aggregation size" ) !== false ?
            $config->getSearchParameter( "aggregation size" ) : null;

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
        if ( $config->getSearchParameter( "base query" ) !== false ) {
            $queryEngine->setBaseQuery( $config->getSearchParameter( "base query" ) );
        }

        // Configure the highlighter
        $queryEngine->addHighlighter( new DefaultHighlighter( $config ) );

        return $queryEngine;
	}
}
