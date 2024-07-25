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

namespace WikiSearch;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use Hooks;
use MediaWiki\MediaWikiServices;
use WikiSearch\QueryEngine\Filter\QueryPreparationTrait;
use WikiSearch\QueryEngine\Filter\SearchTermFilter;
use WikiSearch\QueryEngine\QueryEngine;

/**
 * Class SearchEngine
 *
 * @package WikiSearch
 */
class SearchEngine {
	use QueryPreparationTrait;

	/**
	 * @var SearchEngineConfig
	 */
	private SearchEngineConfig $config;

	/**
	 * @var QueryEngine
	 */
	private QueryEngine $queryEngine;

	/**
	 * Search constructor.
	 *
	 * @param SearchEngineConfig $config
	 */
	public function __construct( SearchEngineConfig $config, QueryEngine $queryEngine ) {
		$this->config = $config;
		$this->queryEngine = $queryEngine;
	}

	/**
	 * Returns the current search engine configuration.
	 *
	 * @return SearchEngineConfig
	 */
	public function getConfig(): SearchEngineConfig {
		return $this->config;
	}

	/**
	 * Returns teh QueryEngine for this search engine.
	 *
	 * @return QueryEngine
	 */
	public function getQueryEngine(): QueryEngine {
		return $this->queryEngine;
	}

	/**
	 * Executes the given ElasticSearch query and returns the result.
	 *
	 * @param array $query
	 * @return array
	 */
	public function doQuery( array $query ): array {
		// Allow other extensions to modify the query
		Hooks::run( "WikiSearchBeforeElasticQuery", [ &$query ] );

		\WikiSearch\WikiSearchServices::getLogger()->getLogger()->debug( 'Executing ElasticSearch query: {query}', [
			'query' => $query
		] );

		try {
			$result = WikiSearchServices::getElasticsearchClientFactory()
				->newElasticsearchClient()
				->search( $query );
		} catch ( ClientResponseException | ServerResponseException ) {
			$result = [];
		}

		if ( !is_array( $result ) ) {
			// Elasticsearch >= 8.x
			$result = $result->asArray();
		}

		return $result;
	}

	/**
	 * Adds the given search term.
	 *
	 * @param string $search_term
	 */
	public function addSearchTerm( string $search_term ) {
		$search_term_filter = new SearchTermFilter(
			$this->prepareQuery( $search_term ),
			$this->config->getSearchParameter( "search term properties" ),
			$this->config->getSearchParameter( "default operator" ) ?? "or"
		);

		$this->queryEngine->addFunctionScoreFilter( $search_term_filter );
	}

	/**
	 * Performs an ElasticSearch query.
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function doSearch(): array {
		$elastic_query = $this->queryEngine->toQuery();

		$results = $this->doQuery( $elastic_query );
		$results = $this->applyResultTranslations( $results );

		return [
			"hits"  => json_encode( $results["hits"]["hits"] ?? [] ),
			"total" => $results["hits"]["total"] ?? 0,
			"aggs"  => $results["aggregations"] ?? []
		];
	}

	/**
	 * Applies necessary translations to the ElasticSearch query result.
	 *
	 * @param array $results
	 * @return array
	 * @throws Exception
	 */
	private function applyResultTranslations( array $results ): array {
		$results = $this->doFacetTranslations( $results );
		$results = $this->doNamespaceTranslations( $results );
		$template = $this->config->getSearchParameter( "result template" );
		$properties = $this->config->getResultProperties();

		// Allow other extensions to modify the result
		Hooks::run( "WikiSearchApplyResultTranslations", [ &$results, $template, $properties ] );

		return $results;
	}

	/**
	 * Does facet translations.
	 *
	 * @param array $results
	 * @return array
	 */
	private function doFacetTranslations( array $results ): array {
		if ( !isset( $results["aggregations"] ) ) {
			return $results;
		}

		$aggregations = $results["aggregations"];

		foreach ( $aggregations as $property_name => $aggregate_data ) {
			$translations = $this->config->getPropertyTranslations();

			if ( !isset( $translations[$property_name] ) ) {
				// No translation available
				continue;
			}

			$parts = explode( ":", $translations[$property_name] );

			if ( $parts[0] === "namespace" ) {
				foreach ( $results['aggregations'][$property_name]['buckets'] as $bucket_key => $bucket_value ) {
					$namespace = MediaWikiServices::getInstance()
						->getNamespaceInfo()
						->getCanonicalName( $bucket_value['key'] );
					$results['aggregations'][$property_name]['buckets'][$bucket_key]['name'] = $namespace;
				}
			}
		}

		return $results;
	}

	/**
	 * Translates namespace IDs to their canonical name.
	 *
	 * @param array $results
	 * @return array
	 */
	private function doNamespaceTranslations( array $results ): array {
		// Translate namespace IDs to their canonical name
		foreach ( $results['hits']['hits'] as $key => $value ) {
			$namespace = MediaWikiServices::getInstance()
				->getNamespaceInfo()
				->getCanonicalName( $value['_source']['subject']['namespace'] );
			$results['hits']['hits'][$key]['_source']['subject']['namespacename'] = $namespace;
		}

		return $results;
	}
}
