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

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Exception;
use MediaWiki\MediaWikiServices;
use RequestContext;
use WikiMap;
use WikiSearch\NeuralSearch\NeuralSearchBackend;
use WikiSearch\NeuralSearch\OpenSearchNeuralSearchBackend;
use WikiSearch\QueryEngine\Factory\QueryEngineFactory;
use WikiSearch\QueryEngine\Filter\NeuralSearchTermFilter;
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

    private const WIKISEARCH_LAST_SEARCH_TERMS_SESSION_KEY = 'wikisearch_last_search_term';

	/**
	 * @var SearchEngineConfig
	 */
	private SearchEngineConfig $config;

	/**
	 * @var QueryEngine
	 */
	private QueryEngine $query_engine;

    /**
     * @var string[]
     */
    private array $search_terms = [];

    /**
     * Whether the neural search ingest pipeline has been confirmed ready
     * during this request. Set after the first successful ensureIngestPipeline()
     * call so we do not repeat it for subsequent addSearchTerm() calls.
     *
     * @var bool
     */
    private bool $neuralPipelineReady = false;

	/**
	 * Search constructor.
	 *
	 * @param SearchEngineConfig $config
	 */
	public function __construct( SearchEngineConfig $config ) {
		$this->config = $config;
		$this->query_engine = QueryEngineFactory::newQueryEngine( $config );
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
		return $this->query_engine;
	}

	/**
	 * Executes the given ElasticSearch query and returns the result.
	 *
	 * @param array $query
	 * @return array
	 * @throws Exception
	 */
	public function doQuery( array $query ): array {
		// Allow other extensions to modify the query
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookContainer->run( "WikiSearchBeforeElasticQuery", [ &$query ] );

		Logger::getLogger()->debug( 'Executing ElasticSearch query: {query}', [
			'query' => $query
		] );

        $result = WikiSearchServices::getElasticsearchClientFactory()
            ->newElasticsearchClient()
            ->search( $query );

        if ( is_array( $result ) ) {
            return $result;
        } else {
            return $result->asArray();
        }
	}

	/**
	 * Adds the given search term.
	 *
	 * @param string $search_term
	 */
	public function addSearchTerm( string $search_term ) {
        $this->search_terms[] = $search_term;

        if ( $this->isNaturalLanguageSearchEnabled() ) {
            // Ensure the ingest pipeline is in place so newly indexed documents
            // receive embeddings. Falls back to keyword search on failure.
            if ( !$this->neuralPipelineReady ) {
                $backend = $this->createNeuralSearchBackend();
                $this->neuralPipelineReady = $backend->ensureIngestPipeline( $this->getIndex() );
            }

            if ( $this->neuralPipelineReady ) {
                $backend = $this->createNeuralSearchBackend();
                $filter = new NeuralSearchTermFilter(
                    $search_term,
                    $backend->getModelId(),
                    $backend->getEmbeddingField()
                );
                $this->query_engine->addFunctionScoreFilter( $filter );
                return;
            }
        }

        // Keyword search (default or NL fallback).
		$search_term_filter = new SearchTermFilter(
			$this->prepareQuery( $search_term ),
			$this->config->getSearchParameter( "search term properties" ) ?: null,
			$this->config->getSearchParameter( "default operator" ) ?: "or",
            $this->config->getSearchParameter( "include default search term properties" ) ?: false
		);

		$this->query_engine->addFunctionScoreFilter( $search_term_filter );
	}

	/**
	 * Performs an ElasticSearch query.
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function doSearch(): array {
		$elastic_query = $this->query_engine->toQuery();

		$results = $this->doQuery( $elastic_query );
		$results = $this->applyResultTranslations( $results );

        $enableSearchHistory = MediaWikiServices::getInstance()->getMainConfig()->get( 'WikiSearchEnableSearchHistory' );

        if ( $enableSearchHistory ) {
            $session = RequestContext::getMain()->getRequest()->getSession();
            $lastSearchTerms = $session->get( self::WIKISEARCH_LAST_SEARCH_TERMS_SESSION_KEY, [] );

            if ( !is_array( $lastSearchTerms ) ) {
                $lastSearchTerms = [$lastSearchTerms];
            }

            $newSearchTerms = array_diff( $this->search_terms , $lastSearchTerms );

            foreach ( $newSearchTerms as $search_term ) {
                WikiSearchServices::getSearchHistoryStore()->pushHistory( $search_term );
            }

            $session->set( self::WIKISEARCH_LAST_SEARCH_TERMS_SESSION_KEY, $this->search_terms );
            $session->persist();
        }

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
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookContainer->run( "WikiSearchApplyResultTranslations", [ &$results, $template ,$properties ] );

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
	 * Returns whether natural language search is enabled for this request.
	 *
	 * Evaluation order:
	 *  1. If $wgWikiSearchNaturalLanguageSearch is false → disabled globally.
	 *  2. If $wgWikiSearchNeuralSearchModelId is not set → disabled (logs a warning).
	 *  3. The per-page "natural language search" parameter overrides the global
	 *     default when explicitly set to true or false.
	 *
	 * @return bool
	 */
	private function isNaturalLanguageSearchEnabled(): bool {
		$mainConfig = MediaWikiServices::getInstance()->getMainConfig();

		if ( !$mainConfig->get( 'WikiSearchNaturalLanguageSearch' ) ) {
			return false;
		}

		$modelId = $mainConfig->get( 'WikiSearchNeuralSearchModelId' );

		if ( empty( $modelId ) ) {
			Logger::getLogger()->warning(
				'WikiSearch: WikiSearchNaturalLanguageSearch is enabled but WikiSearchNeuralSearchModelId is not set.'
			);
			return false;
		}

		$perPage = $this->config->getSearchParameter( 'natural language search' );

		if ( $perPage !== false ) {
			return (bool)$perPage;
		}

		return true;
	}

	/**
	 * Returns the OpenSearch index name, using the same logic as QueryEngineFactory.
	 *
	 * @return string
	 */
	private function getIndex(): string {
		$mainConfig = MediaWikiServices::getInstance()->getMainConfig();
		return $mainConfig->get( 'WikiSearchElasticStoreIndex' )
			?: 'smw-data-' . strtolower( WikiMap::getCurrentWikiId() );
	}

	/**
	 * Creates and returns the NeuralSearchBackend for this configuration.
	 *
	 * @return NeuralSearchBackend
	 */
	private function createNeuralSearchBackend(): NeuralSearchBackend {
		$mainConfig = MediaWikiServices::getInstance()->getMainConfig();
		$modelId = (string)$mainConfig->get( 'WikiSearchNeuralSearchModelId' );

		$clientFactory = WikiSearchServices::getElasticsearchClientFactory();
		$hosts = $clientFactory->getHosts();
		$username = $mainConfig->get( 'WikiSearchBasicAuthenticationUsername' );
		$password = $mainConfig->get( 'WikiSearchBasicAuthenticationPassword' );

		return new OpenSearchNeuralSearchBackend( $modelId, $hosts, $username, $password );
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
