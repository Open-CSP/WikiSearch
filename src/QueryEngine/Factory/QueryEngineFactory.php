<?php

namespace WSSearch\QueryEngine\Factory;

use MediaWiki\MediaWikiServices;
use WSSearch\QueryEngine\Aggregation\PropertyAggregation;
use WSSearch\QueryEngine\Filter\SearchTermFilter;
use WSSearch\QueryEngine\Highlighter\FieldHighlighter;
use WSSearch\QueryEngine\QueryEngine;
use WSSearch\SearchEngineConfig;
use WSSearch\SMW\PropertyFieldMapper;

class QueryEngineFactory {
    /**
     * Constructs a new QueryEngine out of thin air.
     *
     * @return QueryEngine
     */
    public static function fromNull(): QueryEngine {
        $mw_config = MediaWikiServices::getInstance()->getMainConfig();
        $index = $mw_config->get( "WSSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );

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
        $index = $mw_config->get( "WSSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );

        $query_engine = new QueryEngine( $index, self::getElasticSearchHosts() );

        foreach ( $config->getFacetProperties() as $facet_property ) {
            $query_engine->addAggregation( new PropertyAggregation( explode( "=", $facet_property )[0] ) );
        }

        // Configure the search term properties
        if ( $config->getSearchParameter( "search term properties" ) !== false ) {
            SearchTermFilter::$fields = self::toPropertyList( $config->getSearchParameter( "search term properties" ) );
        }

        // Configure the base query
        if ( $config->getSearchParameter( "base query" ) !== false ) {
            $query_engine->setBaseQuery( $config->getSearchParameter( "base query" ) );
        }

        // Configure the highlighter
        if ( $config->getSearchParameter( "highlighted properties" ) !== false ) {
            // Specific properties need to be highlighted
            $fields = self::toPropertyList( $config->getSearchParameter( "highlighted properties" ) );
            $highlighter = new FieldHighlighter( $fields );
        } else if ( $config->getSearchParameter( "search term properties" ) !== false ) {
            // The given search term fields need to be highlighted
            $highlighter = new FieldHighlighter( SearchTermFilter::$fields );
        } else {
            // Highlight the default search term fields
            $highlighter = new FieldHighlighter();
        }

        $query_engine->addHighlighter( $highlighter );

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
            $hosts = $config->get( "WSSearchElasticSearchHosts" );
        } catch ( \ConfigException $e ) {
            $hosts = [];
        }

        if ( $hosts !== [] ) {
            return $hosts;
        }

        global $smwgElasticsearchEndpoints;

        if ( !isset( $smwgElasticsearchEndpoints ) || $smwgElasticsearchEndpoints === [] ) {
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

    /**
     * Takes a string of properties and a separator and returns an array of the property field names.
     *
     * @param string $parameter
     * @param string $separator
     * @return string[]
     */
    private static function toPropertyList( string $parameter, string $separator = "," ): array {
        $fields = explode( $separator, $parameter ); // Split the string on the given separator
        $fields = array_map( "trim", $fields ); // Remove any excess whitespace
        return array_map( function( $property ): string {
            return ( new PropertyFieldMapper( $property ) )->getPropertyField(); // Map the property name to its field
        }, $fields );
    }
}