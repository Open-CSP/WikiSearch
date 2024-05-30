<?php

namespace WikiSearch;

use MediaWiki\MediaWikiServices;
use Wikimedia\Services\ServiceContainer;
use WikiSearch\Factory\ElasticsearchClientFactory;
use WikiSearch\Factory\QueryCombinatorFactory;
use WikiSearch\Factory\QueryEngine\AggregationFactory;
use WikiSearch\Factory\QueryEngineFactory;
use WikiSearch\Factory\SearchEngineFactory;

/**
 * Getters for all WikiSearch services. This class reduces the risk of mistyping
 * a service name and serves as the interface for retrieving services for WikiSearch.
 *
 * @note Program logic should use dependency injection instead of this class wherever
 * possible.
 *
 * @note This class should only contain static methods.
 */
final class WikiSearchServices {
	/**
	 * Disable the construction of this class by making the constructor private.
	 */
	private function __construct() {
	}

	/**
	 * Get the AggregationFactory singleton.
	 *
	 * @param ServiceContainer|null $services
	 * @return AggregationFactory
	 */
	public static function getAggregationFactory( ?ServiceContainer $services = null ): AggregationFactory {
		return self::getService( "Factory.QueryEngine.AggregationFactory", $services );
	}

	/**
	 * Get the ElasticsearchClientFactory singleton.
	 *
	 * @param ServiceContainer|null $services
	 * @return ElasticsearchClientFactory
	 */
	public static function getElasticsearchClientFactory(
		?ServiceContainer $services = null
	): ElasticsearchClientFactory {
		return self::getService( "Factory.ElasticsearchClientFactory", $services );
	}

	/**
	 * Get the QueryCombinatorFactory singleton.
	 *
	 * @param ServiceContainer|null $services
	 * @return QueryCombinatorFactory
	 */
	public static function getQueryCombinatorFactory( ?ServiceContainer $services = null ): QueryCombinatorFactory {
		return self::getService( "Factory.QueryCombinatorFactory", $services );
	}

	/**
	 * Get the QueryEngineFactory singleton.
	 *
	 * @param ServiceContainer|null $services
	 * @return QueryEngineFactory
	 */
	public static function getQueryEngineFactory( ?ServiceContainer $services = null ): QueryEngineFactory {
		return self::getService( "Factory.QueryEngineFactory", $services );
	}

	/**
	 * Get the SearchEngineFactory singleton.
	 *
	 * @param ServiceContainer|null $services
	 * @return SearchEngineFactory
	 */
	public static function getSearchEngineFactory( ?ServiceContainer $services = null ): SearchEngineFactory {
		return self::getService( "Factory.SearchEngineFactory", $services );
	}

	/**
	 * Get a service in the "WikiSearch" namespace.
	 *
	 * @param string $name The name of the service, without the "WikiSearch." prefix.
	 * @param ServiceContainer|null $services The service container, if it is available. If NULL is passed,
	 *                                        MediaWikiServices is used.
	 * @return mixed
	 */
	private static function getService( string $name, ?ServiceContainer $services ): object {
		return ( $services ?? MediaWikiServices::getInstance() )->getService( "WikiSearch." . $name );
	}
}
