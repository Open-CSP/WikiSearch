<?php

/**
 * WikiSearch MediaWiki extension
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

namespace WikiSearch\MediaWiki\API;

use ApiBase;
use ApiUsageException;
use MediaWiki\MediaWikiServices;
use MWException;
use Title;
use WikiSearch\Exception\ParsingException;
use WikiSearch\Exception\SearchEngineException;
use WikiSearch\QueryEngine\Aggregation\AbstractAggregation;
use WikiSearch\SearchEngine;
use WikiSearch\SearchEngineConfig;
use WikiSearch\WikiSearchServices;

/**
 * Class ApiQueryWikiSearch
 *
 * @package WikiSearch
 */
class ApiQueryWikiSearch extends ApiQueryWikiSearchBase {
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
				$in_debug_mode = $config->get( "WikiSearchEnableDebugMode" );
			} catch ( \ConfigException $exception ) {
				$in_debug_mode = false;
			}

			if ( $in_debug_mode === true ) {
				$this->getResult()->addValue( null, 'query', $engine->getQueryEngine()->toQuery() );
			}

			$this->getResult()->addValue( null, 'result', $result );
		} catch ( \Exception $e ) {
			\WikiSearch\WikiSearchServices::getLogger()->getLogger()->critical( 'Caught exception while executing search query: {e}', [
				'e' => $e
			] );

			$this->dieWithError( $this->msg( "wikisearch-api-invalid-query", $e->getMessage() ) );
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
	 * Returns the Title object associated with this request if it is available.
	 *
	 * @return Title
	 * @throws ApiUsageException
	 */
	private function getTitleFromRequest(): Title {
		$page_id = $this->getParameter( "pageid" );
		$title = Title::newFromID( $page_id );

		if ( !$title || !$title instanceof Title ) {
			$this->dieWithError( $this->msg( "wikisearch-api-invalid-pageid" ) );
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
			$this->dieWithError( $this->msg( "wikisearch-api-invalid-pageid" ) );
		}

		return $engine_config;
	}

	/**
	 * Creates the SearchEngine from the current request.
	 *
	 * @param SearchEngineConfig $engineConfig
	 * @return SearchEngine
	 * @throws ApiUsageException
	 * @throws SearchEngineException
	 * @throws ParsingException
	 */
	private function getEngine( SearchEngineConfig $engineConfig ): SearchEngine {
		return WikiSearchServices::getSearchEngineFactory()->newSearchEngine(
			$engineConfig,
			term: $this->getParameter( 'term' ),
			from: $this->getParameter( 'from' ),
			limit: $this->getParameter( 'limit' ),
			filters: $this->getFilters(),
			aggregations: $this->getAggregations(),
			sorts: $this->getSorts()
		);
	}

	/**
	 * @return array
	 */
	private function getFilters(): array {
		return []; // TODO
	}

	/**
	 * @throws ApiUsageException
	 * @throws SearchEngineException
	 * @throws ParsingException
	 */
	private function getAggregations(): array {
		$aggregationSpecs = $this->getParameter( 'aggregations' ) ?? "[]";
		$aggregationSpecs = json_decode( $aggregationSpecs, true );

		if ( !is_array( $aggregationSpecs ) ) {
			// TODO: Improve this error
			$message = wfMessage( "wikisearch-api-invalid-json", "aggregations", json_last_error_msg() );
			throw new SearchEngineException( $message );
		}

		return array_map( static function ( $spec ): AbstractAggregation {
			if ( !is_array( $spec ) ) {
				// TODO: Improve this error
				throw new SearchEngineException( "Invalid aggregation." );
			}

			return WikiSearchServices::getAggregationFactory()->newAggregation( $spec );
		}, $aggregationSpecs );
	}

	private function getSorts(): array {
		return []; // TODO
	}
}
