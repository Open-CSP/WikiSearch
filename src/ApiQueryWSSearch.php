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
     * @throws \FatalError
     * @throws \MWException
     */
	public function execute() {
		#$this->checkUserRightsAny( "wssearch-execute-api" );

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

		if ( $this->getParameter( "from" ) ) {
            $search->setOffset( $this->getParameter( "from" ) );
        }

		if ( $this->getParameter( "limit" ) ) {
            $search->setLimit( $this->getParameter( "limit" ) );
        }

        if ( $this->getParameter( "filter" ) ) {
            $filters = json_decode( $this->getParameter( "filter" ), true );

            if ( !is_array( $filters ) ) {
                $this->dieWithError( wfMessage( "wssearch-api-invalid-json", "filter", json_last_error_msg() ) );
            }

            $search->setActiveFilters( $filters );
        }

		if ( $this->getParameter( "dates" ) ) {
            $dates = json_decode( $this->getParameter( "dates" ), true );

            if ( !is_array( $dates ) ) {
                $this->dieWithError( wfMessage( "wssearch-api-invalid-json", "dates", json_last_error_msg() ) );
            }

            $search->setAggregateDateRanges( $dates );
        }

        try {
		    $result = $search->doSearch();
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
