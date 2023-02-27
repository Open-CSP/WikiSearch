<?php

namespace WikiSearch\QueryEngine\Factory;

use MediaWiki\MediaWikiServices;
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
		$index = $mw_config->get( "WikiSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );

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
		$index = $mw_config->get( "WikiSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );
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
		$config = MediaWikiServices::getInstance()->getMainConfig();

		try {
			$hosts = $config->get( "WikiSearchElasticSearchHosts" );
		} catch ( \ConfigException $e ) {
			$hosts = [];
		}

		if ( $hosts !== [] ) {
			return $hosts;
		}

		// phpcs:ignore
		global $smwgElasticsearchEndpoints;

		if ( !isset( $smwgElasticsearchEndpoints ) || $smwgElasticsearchEndpoints === [] ) {
			Logger::getLogger()->alert( 'Missing or empty $smwgElasticsearchEndpoints, fallback to "localhost:9200"' );

			return [ "localhost:9200" ];
		}

		// @see https://doc.semantic-mediawiki.org/md_content_extensions_SemanticMediaWiki_src_Elastic_docs_config.html
		foreach ( $smwgElasticsearchEndpoints as $endpoint ) {
			if ( is_string( $endpoint ) ) {
				$hosts[] = $endpoint;
				continue;
			}

			$scheme = $endpoint["scheme"];
			$host = $endpoint["host"];
			$port = $endpoint["port"];

			$hosts[] = implode( ":", [ $scheme, "//$host", $port ] );
		}

		return $hosts;
	}
}
