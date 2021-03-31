<?php

namespace WSSearch\QueryEngine\Factory;

use MediaWiki\MediaWikiServices;
use WSSearch\QueryEngine\Aggregation\PropertyAggregation;
use WSSearch\QueryEngine\Highlighter\DefaultHighlighter;
use WSSearch\QueryEngine\QueryEngine;
use WSSearch\SearchEngineConfig;
use WSSearch\SearchEngineException;
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
     * @throws SearchEngineException
     */
    public static function fromSearchEngineConfig( SearchEngineConfig $config = null ): QueryEngine {
        $mw_config = MediaWikiServices::getInstance()->getMainConfig();
        $index = $mw_config->get( "WSSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );
        $query_engine = new QueryEngine( $index, self::getElasticSearchHosts() );

        foreach ( $config->getFacetProperties() as $facet_property ) {
            $query_engine->addAggregation( new PropertyAggregation( explode( "=", $facet_property )[0] ) );
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
}