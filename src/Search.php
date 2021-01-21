<?php

/**
 * Search MediaWiki extension
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
use FatalError;
use Hooks;
use MediaWiki\MediaWikiServices;
use MWException;
use MWNamespace;

class Search {
    /**
     * @var SearchEngineConfig
     */
    private $config;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var array
     */
    private $date_ranges;

    /**
     * @var array
     */
    private $translations = [];

    /**
     * @var int
     */
    private $offset = 0;

    /**
     * @var array
     */
    private $active_filters = [];

    /**
     * @var string
     */
    private $search_term = "";

    /**
     * @var array
     */
    private $facet_property_ids = [];

    /**
     * Search constructor.
     *
     * @param SearchEngineConfig $config
     */
    public function __construct( SearchEngineConfig $config ) {
        $this->config = $config;
    }

    /**
     * Sets the offset for the query. An offset of 10 means the first 10 results will not
     * be returned. Useful for paged searches.
     *
     * @param int $offset
     */
    public function setOffset( int $offset ) {
        $this->offset = $offset;
    }

    /**
     * Sets the currently active filters.
     *
     * @param array $active_filters
     */
    public function setActiveFilters( array $active_filters ) {
        $this->active_filters = $active_filters;
    }

    /**
     * Sets the available date ranges.
     *
     * @param array $range
     */
    public function setAggregateDateRanges(array $range ) {
        $this->date_ranges = $range;
    }

    /**
     * Sets the search term.
     *
     * @param string $search_term
     */
    public function setSearchTerm( string $search_term ) {
        $this->search_term = $search_term;
    }

    /**
     * Limit the number of results returned.
     *
     * @param int $limit
     */
    public function setLimit( int $limit ) {
        $this->limit = $limit;
    }

    /**
     * @return array
     * @throws FatalError
     * @throws MWException
     */
    public function doSearch() {
        $elastic_query = $this->buildElasticQuery();

        // Allow other extensions to modify the query
        Hooks::run("WSSearchBeforeElasticQuery", [ &$elastic_query ] );

        $results = $this->doQuery( $elastic_query );

        // Allow other extensions to modify the result
        Hooks::run("WSSearchAfterElasticQueryComplete", [ &$results ] );

        $results = $this->doFacetTranslations( $results );

        // Translate namespace IDs to their canonical name
        foreach ( $results['hits']['hits'] as $key => $value ) {
            $results['hits']['hits'][$key]['_source']['subject']['namespacename'] = MWNamespace::getCanonicalName( $value['_source']['subject']['namespace'] );
        }

        return [
            "total" => $results["hits"]["total"],
            "hits"  => $results["hits"]["hits"],
            "aggs"  => $results["aggregations"],
            "facetPropertyIDS" => $this->facet_property_ids
        ];
    }

    /**
     * Executes the given ElasticSearch query and returns the result.
     *
     * @param array $query
     * @return array
     */
    private function doQuery( array $query ) {
        $config = MediaWikiServices::getInstance()->getMainConfig();

        try {
            $hosts = $config->get( "WSSearchElasticSearchHosts" );
        } catch ( \ConfigException $e ) {
            $hosts = [ "localhost:9200" ];
        }

        return ClientBuilder::create()->setHosts( $hosts )->build()->search( $query );
    }

    /**
     * Builds the main ElasticSearch query.
     *
     * @return array
     */
    private function buildElasticQuery() {
        $query_builder = SearchQueryBuilder::newCanonical();

        $query_builder->setOffset( $this->offset );
        $query_builder->setMainCondition( $this->config->getConditionProperty(), $this->config->getConditionValue() );
        $query_builder->setAggregateFilters( $this->buildAggregateFilters() );
        $query_builder->setActiveFilters( $this->active_filters );

        if ( $this->search_term !== "" ) {
            $query_builder->setSearchTerm( $this->search_term );
        }

        if ( isset( $this->limit ) ) {
            $query_builder->setLimit( $this->limit );
        }

        if ( isset( $this->date_ranges ) ) {
            $query_builder->setAggregateDateRanges( $this->date_ranges );
        }

        return $query_builder->buildQuery();
    }

    /**
     * Helper function to build the aggregate filters from the current config.
     *
     * @return array
     */
    private function buildAggregateFilters() {
        $filters = [];

        foreach ( $this->config->getFacetProperties() as $facet ) {
            $translation_pair = explode( "=", $facet );
            $property_name = $translation_pair[0];

            if ( isset( $translation_pair[1] ) ) {
                $this->translations[$property_name] = $translation_pair[1];
            }

            $facet_property = new PropertyInfo( $property_name );
            $filters[$property_name] = [ "terms" => [ "field" => "P:" . $facet_property->getPropertyID() . "." . $facet_property->getPropertyType() . ".keyword" ] ];

            $this->facet_property_ids[] = $facet_property->getPropertyID();
        }

        return $filters;
    }

    /**
     * Does facet translations.
     *
     * @param array $results
     * @return array
     */
    private function doFacetTranslations( array $results ) {
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
}
