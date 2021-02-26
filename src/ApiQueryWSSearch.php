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

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiUsageException;
use MediaWiki\MediaWikiServices;
use MWException;
use Title;

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

		if ( !$page_id ) {
			$this->dieWithError( wfMessage( "wssearch-api-missing-pageid" ) );
		}

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

		$term = $this->getParameter( "term" );
		$from = $this->getParameter( "from" );
		$limit = $this->getParameter( "limit" );
		$filter = $this->getParameter( "filter" );
		$dates = $this->getParameter( "dates" );

		if ( $term !== null ) {
			$engine->setSearchTerm( $term );
		}

		if ( $from !== null ) {
			$engine->setOffset( $from );
		}

		if ( $limit !== null ) {
			$engine->setLimit( $limit );
		}

		if ( $filter !== null ) {
			$filters = json_decode( $filter, true );

			if ( is_array( $filters ) ) {
                // TODO: Convert to query engine
				$engine->addFilters( $filters );
			} else {
				$this->dieWithError( wfMessage( "wssearch-api-invalid-json", "filter", json_last_error_msg() ) );
			}
		}

		if ( $dates !== null ) {
			$data = json_decode( $dates, true );

			if ( is_array( $data ) ) {
				$engine->setModificationDateRangeAggregationRanges( $data );
			} else {
				$this->dieWithError( wfMessage( "wssearch-api-invalid-json", "dates", json_last_error_msg() ) );
			}
		}

		return $engine;
	}
}
