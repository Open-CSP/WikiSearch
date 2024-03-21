<?php

use WikiSearch\Factory\QueryCombinatorFactory;

/**
 * This file is loaded by MediaWiki\MediaWikiServices::getInstance() during the
 * bootstrapping of the dependency injection framework.
 *
 * @file
 */

return [
    "WikiSearch.Factory.QueryCombinatorFactory" => static function (): QueryCombinatorFactory {
        return new QueryCombinatorFactory();
    }
];