<?php

/**
 * WSSearch MediaWiki extension
 * Copyright (C) 2021  Wikibase Solutions
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace WSSearch;

use Elasticsearch\ClientBuilder;
use Hooks;
use MediaWiki\MediaWikiServices;
use MWException;
use MWNamespace;
use WSSearch\QueryEngine\Aggregation\Aggregation;
use WSSearch\QueryEngine\Aggregation\ModificationDatePropertyDateRangeAggregation;
use WSSearch\QueryEngine\Aggregation\PropertyAggregation;
use WSSearch\QueryEngine\Filter\Filter;
use WSSearch\QueryEngine\Filter\PropertyFilter;
use WSSearch\QueryEngine\Filter\SearchTermFilter;
use WSSearch\QueryEngine\QueryEngine;

/**
 * Class Search
 *
 * @package WSSearch
 */
class SearchEngine {
    /**
     * @var array
     */
    private $translations = [];

    /**
     * @var QueryEngine
     */
    private $query_engine;

    /**
     * Search constructor.
     *
     * @param SearchEngineConfig|null $config
     */
    public function __construct( SearchEngineConfig $config = null ) {
        $this->translations = $config->getPropertyTranslations();

        if ( $config !== null ) {
            $this->query_engine = QueryEngine::newFromConfig( $config );
        } else {
            $mw_config = MediaWikiServices::getInstance()->getMainConfig();
            $index = $mw_config->get( "WSSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );

            $this->query_engine = new QueryEngine( $index );
        }
    }

    /**
     * Executes the given ElasticSearch query and returns the result.
     *
     * @param array $query
     * @return array
     * @throws \Exception
     */
    public function doQuery( array $query ): array {
        $hosts = SearchEngine::getElasticSearchHosts();

        // Allow other extensions to modify the query
        Hooks::run( "WSSearchBeforeElasticQuery", [ &$query, &$hosts ] );

        return ClientBuilder::create()->setHosts( $hosts )->build()->search( $query );
    }

    /**
     * Sets the offset for the query. An offset of 10 means the first 10 results will not
     * be returned. Useful for paged searches.
     *
     * @param int $offset
     */
    public function setOffset( int $offset ) {
        $this->query_engine->setOffset( $offset );
    }

    /**
     * Adds the given filters to the query.
     *
     * @param Filter[] $filters
     */
    public function addFilters( array $filters ) {
        $this->query_engine->addFilters( $filters );
    }

    /**
     * Allows the user to add additional aggregate filters on top of those provided by the
     * facet properties from the config.
     *
     * @param Aggregation[] $aggregations
     */
    public function addAggregations( array $aggregations ) {
        $this->query_engine->addAggregations( $aggregations );
    }

    /**
     * Sets the search term.
     *
     * @param string $search_term
     */
    public function setSearchTerm( string $search_term ) {
        $search_term_filter = new SearchTermFilter( $search_term );
        $this->query_engine->addFilter( $search_term_filter );
    }

    /**
     * Limit the number of results returned.
     *
     * @param int $limit
     */
    public function setLimit( int $limit ) {
        $this->query_engine->setLimit( $limit );
    }

    /**
     * Performs an ElasticSearch query.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function doSearch(): array {
        $elastic_query = $this->query_engine->toArray();

        $results = $this->doQuery( $elastic_query );
        $results = $this->applyResultTranslations( $results );

        return [
            "hits"  => json_encode( $results["hits"]["hits"] ), // TODO: Do not encode this (but not encoding breaks it for some reason)
            "total" => $results["hits"]["total"],
            "aggs"  => $results["aggregations"]
        ];
    }

    /**
     * Applies necessary translations to the ElasticSearch query result.
     *
     * @param array $results
     * @return array
     * @throws \Exception
     */
    private function applyResultTranslations( array $results ): array {
        $results = $this->doFacetTranslations( $results );
        $results = $this->doNamespaceTranslations( $results );

        // Allow other extensions to modify the result
        Hooks::run( "WSSearchApplyResultTranslations", [ &$results ] );

        return $results;
    }

    /**
     * Does facet translations.
     *
     * @param array $results
     * @return array
     */
    private function doFacetTranslations( array $results ): array {
        if ( !isset( $results["aggregations"] ) ) {
            return $results;
        }

        $aggregations = $results["aggregations"];

        foreach ( $aggregations as $property_name => $aggregate_data ) {
            if ( !isset( $this->translations[$property_name] ) ) {
                // No translation available
                continue;
            }

            $parts = explode( ":", $this->translations[$property_name] );

            if ( $parts[0] = "namespace" ) {
                foreach ( $results['aggregations'][$property_name]['buckets'] as $bucket_key => $bucket_value ) {
                    $namespace = MWNamespace::getCanonicalName( $bucket_value['key'] );
                    $results['aggregations'][$property_name]['buckets'][$bucket_key]['name'] = $namespace;
                }
            }
        }

        return $results;
    }

    /**
     * Translates namespace IDs to their canonical name.
     *
     * @param array $results
     * @return array
     */
    private function doNamespaceTranslations( array $results ): array {
        // Translate namespace IDs to their canonical name
        foreach ( $results['hits']['hits'] as $key => $value ) {
            $results['hits']['hits'][$key]['_source']['subject']['namespacename'] = MWNamespace::getCanonicalName( $value['_source']['subject']['namespace'] );
        }

        return $results;
    }

    /**
     * Returns the configured ElasticSearch hosts.
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
