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

namespace WikiSearch\API;

use ApiBase;
use ApiUsageException;
use Elastic\Elasticsearch\ClientBuilder;
use MWException;
use Title;
use WikiSearch\QueryEngine\Aggregation\PropertyValueAggregation;
use WikiSearch\QueryEngine\Factory\QueryEngineFactory;
use WikiSearch\QueryEngine\Filter\QueryPreparationTrait;
use WikiSearch\QueryEngine\Filter\SearchTermFilter;
use WikiSearch\QueryEngine\QueryEngine;
use WikiSearch\SearchEngineConfig;
use WikiSearch\SearchEngineException;
use WikiSearch\SearchEngineFactory;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class ApiQueryWikiSearchCombobox
 *
 * @package WikiSearch
 */
class ApiQueryWikiSearchCombobox extends ApiQueryWikiSearchBase {
    use QueryPreparationTrait;

	private const AGGREGATION_NAME = 'combobox_values';

	/**
	 * @inheritDoc
	 *
	 * @throws ApiUsageException
	 * @throws MWException|SearchEngineException
	 */
	public function execute() {
		$this->checkUserRights();

		$title = $this->getTitleFromRequest();
		$engine_config = $this->getEngineConfigFromTitle( $title );
		$engine = $this->getQueryEngine( $engine_config );

		$results = ClientBuilder::create()
			->setHosts( QueryEngineFactory::fromNull()->getElasticHosts() )
			->build()
			->search( $engine->toArray() )
            ->asArray();

		$this->getResult()->addValue(
			null,
			'result',
			$this->getAggregationsFromResult( $results )
		);
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
			'search_term' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DFLT => ''
			],
			'filter' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DFLT => '[]'
			],
			'property' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'term' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DFLT => ''
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 25000,
				ApiBase::PARAM_DFLT => 50
			]
		];
	}

	/**
	 * Creates the QueryEngine from the current request.
	 *
	 * @param SearchEngineConfig $config
	 * @return QueryEngine
	 * @throws ApiUsageException
	 * @throws SearchEngineException
	 */
	private function getQueryEngine( SearchEngineConfig $config ): QueryEngine {
		// TODO: Refactor the creation of a QueryEngine from parameters out of the creation of a SearchEngine
		$engine = ( new SearchEngineFactory( $config ) )->fromAPIParameters(
			$this->getParameter( "search_term" ),
			null,
			null,
			$this->getParameter( "filter" ),
			null,
			null
		)->getQueryEngine();

		$engine->addConstantScoreFilter( new SearchTermFilter(
			$this->prepareQuery( $this->getParameter( "term" ) ),
			[ new PropertyFieldMapper( $this->getParameter( "property" ) ) ]
		) );
		$engine->addAggregation(
			new PropertyValueAggregation( $this->getParameter( "property" ),
				self::AGGREGATION_NAME,
				$this->getParameter( "limit" )
			)
		);

		return $engine;
	}

	/**
	 * Extracts the aggregations from the ElasticSearch result.
	 *
	 * @param array $result
	 * @return array
	 */
	private function getAggregationsFromResult( array $result ): array {
		return $result['aggregations'][self::AGGREGATION_NAME][self::AGGREGATION_NAME]['buckets'] ?? [];
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
	 * Returns the Title object associated with this request if it is available.
	 *
	 * @return Title
	 * @throws ApiUsageException
	 */
	private function getTitleFromRequest(): Title {
		$page_id = $this->getParameter( "pageid" );
		$title = Title::newFromID( $page_id );

		if ( !$title instanceof Title ) {
			$this->dieWithError( $this->msg( "wikisearch-api-invalid-pageid" ) );
		}

		return $title;
	}
}
