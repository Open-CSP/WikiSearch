<?php

use MediaWiki\MediaWikiServices;
use WikiSearch\Factory\ElasticsearchClientFactory;
use WikiSearch\Factory\QueryCombinatorFactory;
use WikiSearch\Factory\QueryEngine\AggregationFactory;
use WikiSearch\Factory\QueryEngineFactory;
use WikiSearch\MediaWiki\HookRunner;
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
	"WikiSearch.MediaWiki.HookRunner" => static function ( MediaWikiServices $services ): HookRunner {
		return new HookRunner( $services->getHookContainer() );
	},
	"WikiSearch.Factory.QueryCombinatorFactory" => static function (): QueryCombinatorFactory {
		return new QueryCombinatorFactory();
	},
	"WikiSearch.Factory.QueryEngine.AggregationFactory" => static function (): AggregationFactory {
		return new AggregationFactory();
	},
	"WikiSearch.Factory.QueryEngineFactory" => static function ( MediaWikiServices $services ): QueryEngineFactory {
		return new QueryEngineFactory( $services->getMainConfig() );
	},
	"WikiSearch.Factory.SearchEngineFactory" =>
		static function ( MediaWikiServices $services ): \WikiSearch\Factory\SearchEngineFactory {
			return new \WikiSearch\Factory\SearchEngineFactory(
				WikiSearchServices::getQueryEngineFactory( $services )
			);
		}
];
