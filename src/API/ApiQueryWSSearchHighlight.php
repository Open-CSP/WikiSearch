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
use Elasticsearch\ClientBuilder;
use MediaWiki\MediaWikiServices;
use MWException;
use Title;
use WSSearch\QueryEngine\Factory\QueryEngineFactory;
use WSSearch\QueryEngine\Filter\PageFilter;
use WSSearch\QueryEngine\Filter\SearchTermFilter;
use WSSearch\QueryEngine\Highlighter\DefaultHighlighter;
use WSSearch\QueryEngine\Highlighter\IndividualWordHighlighter;
use WSSearch\QueryEngine\QueryEngine;
use WSSearch\SearchEngine;
use WSSearch\SearchEngineConfig;
use WSSearch\SearchEngineException;
use WSSearch\SearchEngineFactory;

/**
 * Class ApiQueryWSSearchHighlight
 *
 * @package WSSearch
 */
class ApiQueryWSSearchHighlight extends ApiQueryBase {
	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 * @throws MWException
	 * @throws SearchEngineException
	 */
	public function execute() {
		$this->checkUserRights();

		$query = $this->getParameter( "query" );
		$properties = $this->getParameter( "properties" );
		$limit = $this->getParameter( "limit" );
		$page_id = $this->getParameter( "page_id" );

		$properties = explode( ",", $properties );

		$title = Title::newFromID( $page_id );

		if ( !( $title instanceof Title ) || !$title->exists() ) {
			$this->dieWithError( wfMessage( "wssearch-api-invalid-pageid" ) );
		}

		$highlighter = new IndividualWordHighlighter( $properties, $limit );
		$search_term_filter = new SearchTermFilter( $query );
		$page_filter = new PageFilter( $title );

		$query_engine = $this->getEngine();

		$query_engine->addHighlighter( $highlighter );
		$query_engine->addConstantScoreFilter( $page_filter );
		$query_engine->addConstantScoreFilter( $search_term_filter );

		$results = ClientBuilder::create()
			->setHosts( QueryEngineFactory::fromNull()->getElasticHosts() )
			->build()
			->search( $query_engine->toArray() );

		$this->getResult()->addValue(null, "value", $results);
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'query' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'properties' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'page_id' => [
				ApiBase::PARAM_TYPE => 'int',
				ApiBase::PARAM_REQUIRED => true
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
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
	 * Creates the QueryEngine from the current request.
	 *
	 * @return QueryEngine
	 */
	private function getEngine(): QueryEngine {
		return QueryEngineFactory::fromNull();
	}
}
