<?php

namespace WikiSearch\QueryEngine\Factory;

use MediaWiki\MediaWikiServices;
// Note: MW 1.40+ will have MediaWiki\WikiMap\WikiMap instead
use WikiMap;
use WikiSearch\Logger;
use WikiSearch\QueryEngine\Aggregation\PropertyValueAggregation;
use WikiSearch\QueryEngine\Highlighter\DefaultHighlighter;
use WikiSearch\QueryEngine\QueryEngine;
use WikiSearch\SearchEngineConfig;

class QueryEngineFactory {
	/**
	 * Constructs a new QueryEngine out of thin air.
	 *
	 * @return QueryEngine
	 */
	public static function fromNull(): QueryEngine {
		$mw_config = MediaWikiServices::getInstance()->getMainConfig();
		$index = $mw_config->get( "WikiSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( WikiMap::getCurrentWikiId() );

		return new QueryEngine( $index, self::getElasticSearchHosts() );
	}

	/**
	 * Constructs a new QueryEngine from the given SearchEngineConfig.
	 *
	 * @param SearchEngineConfig|null $config
	 * @return QueryEngine
	 */
	public static function fromSearchEngineConfig( SearchEngineConfig $config = null ): QueryEngine {
		$mw_config = MediaWikiServices::getInstance()->getMainConfig();
		$index = $mw_config->get( "WikiSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( WikiMap::getCurrentWikiId() );
		$query_engine = new QueryEngine( $index, self::getElasticSearchHosts() );

		$aggregation_size = $config->getSearchParameter( "aggregation size" ) !== false ?
			$config->getSearchParameter( "aggregation size" ) : null;

		foreach ( $config->getFacetProperties() as $facet_property ) {
			$aggregation = new PropertyValueAggregation(
				$facet_property,
				null,
				$aggregation_size
			);

			$query_engine->addAggregation( $aggregation );
		}

		foreach ( $config->getResultProperties() as $result_property ) {
			// Include this property and any sub-properties in the result
			$source = $result_property->getPID() . ".*";

			$query_engine->addSource( $source );
		}

		// Configure the base query
		if ( $config->getSearchParameter( "base query" ) !== false ) {
			$query_engine->setBaseQuery( $config->getSearchParameter( "base query" ) );
		}

		// Configure the highlighter
		$query_engine->addHighlighter( new DefaultHighlighter( $config ) );

		return $query_engine;
	}

	/**
	 * Returns the ElasticSearch hosts configured by the wiki administrator.
	 *
	 * @return array
	 */
    private static function getElasticSearchHosts(): array {
        global $wgWikiSearchElasticSearchHosts, $wgWikiSearchElasticSearchCredentials;

        $transformedHosts = [];

        foreach ( $wgWikiSearchElasticSearchHosts as $hostEntry ) {
            if ( is_string( $hostEntry ) ) {
                // Parse an entry like "es01.juggel.dev:9200"
                list($host, $port) = array_pad( explode( ":", $hostEntry, 2 ), 2, 9200 );
                $transformedHosts[] = [
                    'host'   => $host,
                    'port'   => (int)$port,
                    'scheme' => 'http',
                    'user'   => $wgWikiSearchElasticSearchCredentials['user'] ?? '',
                    'pass'   => $wgWikiSearchElasticSearchCredentials['pass'] ?? '',
                ];
            } elseif ( is_array( $hostEntry ) ) {
                // Merge defaults and credentials into the host array.
                $transformedHosts[] = array_merge(
                    [
                        'scheme' => 'http',
                        'port'   => 9200,
                    ],
                    $hostEntry,
                    isset( $wgWikiSearchElasticSearchCredentials )
                        ? [
                        'user' => $wgWikiSearchElasticSearchCredentials['user'],
                        'pass' => $wgWikiSearchElasticSearchCredentials['pass'],
                    ]
                        : []
                );
            }
        }

        return $transformedHosts;
    }

}
