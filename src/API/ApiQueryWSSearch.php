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

namespace WSSearch\API;

use ApiBase;
use ApiQueryBase;
use ApiUsageException;
use MediaWiki\MediaWikiServices;
use MWException;
use Title;
use WSSearch\QueryEngine\Aggregation\Aggregation;
use WSSearch\QueryEngine\Factory\AggregationFactory;
use WSSearch\QueryEngine\Factory\FilterFactory;
use WSSearch\QueryEngine\Factory\SortFactory;
use WSSearch\QueryEngine\Filter\Filter;
use WSSearch\QueryEngine\Sort\Sort;
use WSSearch\SearchEngine;
use WSSearch\SearchEngineConfig;

/**
 * Class ApiQueryWSSearch
 *
 * @package WSSearch
 */
class ApiQueryWSSearch extends ApiQueryBase {
    /**
     * @inheritDoc
     * @throws ApiUsageException
     * @throws MWException
     */
    public function execute() {
        $this->checkUserRights();

        $title = $this->getTitleFromRequest();
        $engine_config = $this->getEngineConfigFromTitle( $title );
        $engine = $this->getEngine( $engine_config );

        try {
            $result = $engine->doSearch();

            $config = MediaWikiServices::getInstance()->getMainConfig();

            try {
                $in_debug_mode = $config->get( "WSSearchEnableDebugMode" );
            } catch( \ConfigException $exception ) {
                $in_debug_mode = false;
            }

            if ( $in_debug_mode === true ) {
                $this->getResult()->addValue( null, 'query', $engine->getQuery());
            }

            $this->getResult()->addValue( null, 'result', $result );
        } catch ( \Exception $e ) {
            $this->dieWithError( wfMessage( "wssearch-api-invalid-query", $e->getMessage() ) );
        }
    }

    /**
     * @inheritDoc
     */
    public function getAllowedParams() {
        return [
            'pageid' => [
                ApiBase::PARAM_TYPE => 'integer',
                ApiBase::PARAM_REQUIRED => true
            ],
            'term' => [
                ApiBase::PARAM_TYPE => 'string'
            ],
            'filter' => [
                ApiBase::PARAM_TYPE => 'string'
            ],
            'aggregations' => [
                ApiBase::PARAM_TYPE => 'string'
            ],
            'sortings' => [
                ApiBase::PARAM_TYPE => 'string'
            ],
            'from' => [
                ApiBase::PARAM_TYPE => 'integer'
            ],
            'limit' => [
                ApiBase::PARAM_TYPE => 'integer'
            ]
        ];
    }

    /**
     * Checks applicable user rights.
     *
     * @throws ApiUsageException
     */
    private function checkUserRights() {
        $config = MediaWikiServices::getInstance()->getMainConfig();

        try {
            $required_rights = $config->get( "WSSearchAPIRequiredRights" );
            $this->checkUserRightsAny( $required_rights );
        } catch ( \ConfigException $e ) {
            // Something went wrong; to be safe we block the access
            $this->dieWithError( [ 'apierror-permissiondenied', $this->msg( "action-read" ) ] );
        }
    }

    /**
     * Returns the Title object associated with this request if it is available.
     *
     * @throws ApiUsageException
     */
    private function getTitleFromRequest(): Title {
        $page_id = $this->getParameter( "pageid" );
        $title = Title::newFromID( $page_id );

        if ( !$title || !$title instanceof Title ) {
            $this->dieWithError( wfMessage( "wssearch-api-invalid-pageid" ) );
        }

        return $title;
    }

    /**
     * Returns the EngineConfig associated with the given Title if possible.
     *
     * @param Title $title
     * @return SearchEngineConfig
     * @throws ApiUsageException
     */
    private function getEngineConfigFromTitle( Title $title ): SearchEngineConfig {
        $engine_config = SearchEngineConfig::newFromDatabase( $title );

        if ( $engine_config === null ) {
            $this->dieWithError( wfMessage( "wssearch-api-invalid-pageid" ) );
        }

        return $engine_config;
    }

    /**
     * Creates the SearchEngine from the current request.
     *
     * @param SearchEngineConfig $engine_config
     * @return SearchEngine
     * @throws ApiUsageException
     */
    private function getEngine( SearchEngineConfig $engine_config ): SearchEngine {
        $engine = new SearchEngine( $engine_config );

        // Set the search term field
        $term = $this->getParameter( "term" );
        if ( $term !== null ) {
            $engine->addSearchTerm( $term );
        }

        // Set the offset from which to include results
        $from = $this->getParameter( "from" );
        if ( $from !== null ) {
            $engine->setOffset( $from );
        }

        // Set the limit for the number of results
        $limit = $this->getParameter( "limit" );
        if ( $limit !== null ) {
            $engine->setLimit( $limit );
        }

        // Set the applied filters
        $filter = $this->getParameter( "filter" );
        if ( $filter !== null ) {
            $filters = json_decode( $filter, true );

            if ( !is_array( $filters ) || json_last_error() !== JSON_ERROR_NONE ) {
                $this->dieWithError( wfMessage( "wssearch-api-invalid-json", "filter", json_last_error_msg() ) );
            }

            $filters = array_map( [ FilterFactory::class, "fromArray" ], $filters );

            foreach ( $filters as $filter ) {
                $is_filter = $filter instanceof Filter;

                if ( $is_filter === false ) {
                    $this->dieWithError( wfMessage( "wssearch-invalid-filter" ) );
                }
            }

            $engine->addFilters( $filters );
        }

        // Set the applied aggregations
        $aggregations = $this->getParameter( "aggregations" );
        if ( $aggregations !== null ) {
            $aggregations = json_decode( $aggregations, true );

            if ( !is_array( $aggregations ) || json_last_error() !== JSON_ERROR_NONE ) {
                $this->dieWithError( wfMessage( "wssearch-api-invalid-json", "aggregations", json_last_error_msg() ) );
            }

            $aggregations = array_map( [ AggregationFactory::class, "fromArray" ], $aggregations );

            foreach ( $aggregations as $aggregation ) {
                $is_aggregation = $aggregation instanceof Aggregation;

                if ( !$is_aggregation ) {
                    $this->dieWithError( wfMessage( "wssearch-invalid-aggregation" ) );
                }
            }

            $engine->addAggregations( $aggregations );
        }

        $sortings = $this->getParameter( "sortings" );
        if ( $sortings !== null ) {
            $sortings = json_decode( $sortings, true );

            if ( !is_array( $sortings ) || json_last_error() !== JSON_ERROR_NONE ) {
                $this->dieWithError( wfMessage( "wssearch-api-invalid-json", "sortings", json_last_error_msg() ) );
            }

            $sortings = array_map( [ SortFactory::class, "fromArray" ], $sortings );

            foreach ( $sortings as $sort ) {
                $is_sort = $sort instanceof Sort;

                if ( !$is_sort ) {
                    $this->dieWithError( wfMessage( "wssearch-invalid-sort" ) );
                }
            }

            $engine->addSortings( $sortings );
        }

        return $engine;
    }
}
