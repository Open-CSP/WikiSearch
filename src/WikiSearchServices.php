<?php

namespace WikiSearch;

use MediaWiki\MediaWikiServices;
use Wikimedia\Services\ServiceContainer;
use WikiSearch\Factory\QueryCombinatorFactory;

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

    public static function getQueryCombinatorFactory( ?ServiceContainer $services = null ): QueryCombinatorFactory {
        return self::getService( "Factory.QueryCombinatorFactory", $services );
    }

    private static function getService( string $name, ?ServiceContainer $services ): object {
        return ( $services ?? MediaWikiServices::getInstance() )->getService( "WikiSearch." . $name );
    }
}