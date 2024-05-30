<?php

use MediaWiki\MediaWikiServices;
use WikiSearch\Factory\ElasticsearchClientFactory;
use WikiSearch\Factory\QueryCombinatorFactory;
use WikiSearch\Factory\QueryEngine\AggregationFactory;
use WikiSearch\Factory\QueryEngine\FilterFactory;
use WikiSearch\Factory\QueryEngine\SortFactory;
use WikiSearch\Factory\QueryEngineFactory;
use WikiSearch\MediaWiki\HookRunner;
use WikiSearch\MediaWiki\Logger;
use WikiSearch\WikiSearchServices;

/**
 * This file is loaded by MediaWiki\MediaWikiServices::getInstance() during the
 * bootstrapping of the dependency injection framework.
 *
 * @file
 */

return [
	"WikiSearch.Factory.ElasticsearchClientFactory" =>
		static function ( MediaWikiServices $services ): ElasticsearchClientFactory {
			return new ElasticsearchClientFactory( $services->getMainConfig() );
		},
	"WikiSearch.Factory.QueryCombinatorFactory" => static function (): QueryCombinatorFactory {
		return new QueryCombinatorFactory();
	},
	"WikiSearch.Factory.QueryEngine.AggregationFactory" => static function (): AggregationFactory {
		return new AggregationFactory();
	},
	"WikiSearch.Factory.QueryEngine.FilterFactory" => static function (): FilterFactory {
		return new FilterFactory();
	},
	"WikiSearch.Factory.QueryEngine.SortFactory" => static function (): SortFactory {
		return new SortFactory();
	},
	"WikiSearch.Factory.QueryEngineFactory" => static function ( MediaWikiServices $services ): QueryEngineFactory {
		return new QueryEngineFactory( $services->getMainConfig() );
	},
	"WikiSearch.Factory.SearchEngineFactory" =>
		static function ( MediaWikiServices $services ): \WikiSearch\Factory\SearchEngineFactory {
			return new \WikiSearch\Factory\SearchEngineFactory(
				WikiSearchServices::getQueryEngineFactory( $services )
			);
		},
	"WikiSearch.MediaWiki.HookRunner" => static function ( MediaWikiServices $services ): HookRunner {
		return new HookRunner( $services->getHookContainer() );
	},
	"WikiSearch.MediaWiki.Logger" => static function (): Logger {
		return new Logger();
	}
];
