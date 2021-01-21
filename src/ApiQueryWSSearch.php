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

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiUsageException;
use Title;

class ApiQueryWSSearch extends ApiQueryBase {
	/**
	 * ApiQueryWSSearch constructor.
	 *
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, string $moduleName ) {
		parent::__construct( $query, $moduleName, 'sm' );
	}

	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 */
	public function execute() {
		#$this->checkUserRightsAny( "wssearch-execute-api" );

		/*
		$search_params = [
			 "term"     => $this->getParameter( "term" ), // The search "term" from the search field
			 "from"     => $this->getParameter( "from" ), // Offset
			 "dates"    => json_decode( $this->getParameter( "dates" ) ), // Date filters
			 "filters"  => json_decode( $this->getParameter( "filter" ), true ), // Active facet filters (empty = everything)
			 "page"     => $this->getParameter( "page" ) // The page to get the info
		];
		*/

		$page_id = $this->getParameter( "pageid" );

		if ( !$page_id ) {
            $this->dieWithError( wfMessage( "wssearch-api-missing-pageid" ) );
        }

		$title = Title::newFromID( $page_id );

		if ( !$title || !$title instanceof Title ) {
		    $this->dieWithError( wfMessage( "wssearch-api-invalid-pageid" ) );
        }

		$engine_config = SearchEngineConfig::newFromDatabase( $title );

		if ( $engine_config === null ) {
		    $this->dieWithError( wfMessage( "wssearch-api-invalid-pageid" ) );
        }

        $search = new Search( $engine_config );

		if ( $this->getParameter( "term" ) ) {
            $search->setSearchTerm( $this->getParameter( "term" ) );
        }

		if ( $this->getParameter( "filter" ) ) {
            $search->setActiveFilters( json_decode( $this->getParameter( "filter" ) ) );
        }

		if ( $this->getParameter( "from" ) ) {
            $search->setOffset( $this->getParameter( "from" ) );
        }

		if ( $this->getParameter( "limit" ) ) {
            $search->setLimit( $this->getParameter( "limit" ) );
        }

		// $search->setDateRange( $this->getParameter( "dates" ) );

        $output = $search->doSearch();

		$this->getResult()->addValue( 'result', 'output', $output );

		/*
		$this->getResult()->addValue( 'result', 'total', $output['total'] );
		$this->getResult()->addValue( 'result', 'hits', json_encode( $output['hits'] ) );
		$this->getResult()->addValue( 'result', 'aggs', json_encode( $output['aggs'] ) );
		*/
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'pageid' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'filter' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'dates' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'term' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'from' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
            'limit' => [
                ApiBase::PARAM_TYPE => 'integer'
            ]
		];
	}
}
