<?php

use MediaWiki\MediaWikiServices;
use WikiSearch\Factory\ElasticsearchClientFactory;
use WikiSearch\Factory\QueryCombinatorFactory;

/**
 * This file is loaded by MediaWiki\MediaWikiServices::getInstance() during the
 * bootstrapping of the dependency injection framework.
 *
 * @file
 */

return [
    "WikiSearch.Factory.ElasticsearchClientFactory" => static function ( MediaWikiServices $services ): ElasticsearchClientFactory {
        return new ElasticsearchClientFactory( $services->getMainConfig() );
    },
    "WikiSearch.Factory.QueryCombinatorFactory" => static function (): QueryCombinatorFactory {
        return new QueryCombinatorFactory();
    }
];