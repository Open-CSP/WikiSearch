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
use WSSearch\Logger;
use WSSearch\SearchEngine;
use WSSearch\SearchEngineConfig;
use WSSearch\SearchEngineException;
use WSSearch\SearchEngineFactory;

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
			} catch ( \ConfigException $exception ) {
				$in_debug_mode = false;
			}

			if ( $in_debug_mode === true ) {
				$this->getResult()->addValue( null, 'query', $engine->getQueryEngine()->toArray() );
			}

			$this->getResult()->addValue( null, 'result', $result );
		} catch ( \Exception $e ) {
			Logger::getLogger()->critical( 'Caught exception while executing search query: {e}', [
				'e' => $e
			] );

			$this->dieWithError( $this->msg( "wssearch-api-invalid-query", $e->getMessage() ) );
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
			Logger::getLogger()->critical( 'Caught exception while trying to get required rights for WSSearch API: {e}', [
				'e' => $e
			] );

			// Something went wrong; to be safe we block the access
			$this->dieWithError( [ 'apierror-permissiondenied', $this->msg( "action-read" ) ] );
		}
	}

	/**
	 * Returns the Title object associated with this request if it is available.
	 *
	 * @return Title
	 * @throws ApiUsageException
	 */
	private function getTitleFromRequest(): Title {
		$page_id = $this->getParameter( "pageid" );
		$title = Title::newFromID( $page_id );

		if ( !$title || !$title instanceof Title ) {
			$this->dieWithError( $this->msg( "wssearch-api-invalid-pageid" ) );
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
			$this->dieWithError( $this->msg( "wssearch-api-invalid-pageid" ) );
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
		$search_engine_factory = new SearchEngineFactory( $engine_config );

		try {
			$search_engine = $search_engine_factory->fromParameters(
				$this->getParameter( "term" ),
				$this->getParameter( "from" ),
				$this->getParameter( "limit" ),
				$this->getParameter( "filter" ),
				$this->getParameter( "aggregations" ),
				$this->getParameter( "sortings" )
			);
		} catch ( SearchEngineException $exception ) {
			Logger::getLogger()->critical( 'Caught exception while trying to construct a SearchEngine: {e}', [
				'e' => $exception
			] );

			$this->dieWithError( $exception->getMessage() );
		}

		return $search_engine;
	}
}
