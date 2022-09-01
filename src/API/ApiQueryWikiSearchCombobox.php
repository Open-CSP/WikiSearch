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
use Elasticsearch\ClientBuilder;
use MWException;
use WikiSearch\QueryEngine\Aggregation\FilterAggregation;
use WikiSearch\QueryEngine\Aggregation\PropertyValueAggregation;
use WikiSearch\QueryEngine\Factory\QueryEngineFactory;
use WikiSearch\QueryEngine\Filter\SearchTermFilter;
use WikiSearch\QueryEngine\QueryEngine;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class ApiQueryWikiSearchCombobox
 *
 * @package WikiSearch
 */
class ApiQueryWikiSearchCombobox extends ApiQueryWikiSearchBase {
	private const AGGREGATION_NAME = 'combobox_values';

	/**
	 * @inheritDoc
	 *
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		$this->checkUserRights();

		$property = $this->getParameter( "property" );
		$term = $this->getParameter( "term" );
		$size = $this->getParameter( "limit" );

		$value_filter = new SearchTermFilter( $term, [ new PropertyFieldMapper( $property ) ] );
		$filter_aggregation = new FilterAggregation( $value_filter, [
			new PropertyValueAggregation( $property, null, $size )
		], self::AGGREGATION_NAME );

		$query_engine = $this->getEngine();
		$query_engine->addAggregation( $filter_aggregation );

		$results = ClientBuilder::create()
			->setHosts( QueryEngineFactory::fromNull()->getElasticHosts() )
			->build()
			->search( $query_engine->toArray() );

		$this->getResult()->addValue(
			null,
			'result',
			$this->getAggregationsFromResult( $results, $property )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
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
	 * @return QueryEngine
	 */
	private function getEngine(): QueryEngine {
		return QueryEngineFactory::fromNull();
	}

	/**
	 * Extracts the aggregations from the ElasticSearch result.
	 *
	 * @param array $result
	 * @param string $property The property for which to get the aggregations
	 * @return array
	 */
	private function getAggregationsFromResult( array $result, string $property ): array {
		return $result['aggregations'][self::AGGREGATION_NAME][self::AGGREGATION_NAME][$property]['buckets'] ?? [];
	}
}
