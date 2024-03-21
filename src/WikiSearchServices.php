<?php

namespace WikiSearch;

use MediaWiki\MediaWikiServices;
use Wikimedia\Services\ServiceContainer;
use WikiSearch\Factory\ElasticsearchClientFactory;
use WikiSearch\Factory\QueryCombinatorFactory;
use WikiSearch\Factory\QueryEngineFactory;

/**
 * Getter for all WikiSearch services. This class reduces the risk of mistyping
 * a service name and serves as the interface for retrieving services for WikiSearch.
 *
 * @note Program logic should use dependency injection instead of this class wherever
 * possible.
 *
 * @note This function should only contain static methods.
 */

final class WikiSearchServices {
    /**
     * Disable the construction of this class by making the constructor private.
     */
    private function __construct() {
    }

    public static function getElasticsearchClientFactory( ?ServiceContainer $services = null ): ElasticsearchClientFactory {
        return self::getService( "Factory.ElasticsearchClientFactory", $services );
    }

    public static function getQueryCombinatorFactory( ?ServiceContainer $services = null ): QueryCombinatorFactory {
        return self::getService( "Factory.QueryCombinatorFactory", $services );
    }

    public static function getQueryEngineFactory( ?ServiceContainer $services = null ): QueryEngineFactory {
        return self::getService( "Factory.QueryEngineFactory", $services );
    }

    private static function getService( string $name, ?ServiceContainer $services ): object {
        return ( $services ?? MediaWikiServices::getInstance() )->getService( "WikiSearch." . $name );
    }
}