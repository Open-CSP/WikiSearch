<?php

namespace WikiSearch\SMW;

use SMW\Elastic\ElasticFactory;

class SMWQueryProcessor {
	/**
	 * @var string
	 */
	private string $query;

	/**
	 * SMWQueryProcessor constructor.
	 *
	 * @param string $query The Semantic MediaWiki query to process
	 */
	public function __construct( string $query ) {
		$this->query = $query;
	}

	/**
	 * Sets the SemanticMediaWiki query to process.
	 *
	 * @param string $query
	 */
	public function setQuery( string $query ): void {
		$this->query = $query;
	}

	/**
	 * Converts the SMW query to an ElasticSearch query.
	 *
	 * @return array
	 * @throws \MWException When the query is invalid
	 */
	public function toElasticSearchQuery(): array {
		\WikiSearch\WikiSearchServices::getLogger()->getLogger()->debug( 'Converting base Semantic MediaWiki query to ElasticSearch query: {query}', [
			'query' => $this->query
		] );

		[ $query_string, $parameters, $printouts ] = \SMWQueryProcessor::getComponentsFromFunctionParams(
			[ $this->query ],
			false
		);

		$query = \SMWQueryProcessor::createQuery(
			$query_string,
			\SMWQueryProcessor::getProcessedParams( $parameters, $printouts ),
			\SMWQueryProcessor::SPECIAL_PAGE,
			'',
			$printouts
		);

		$query->setOption( \SMWQuery::MODE_DEBUG, 'API' );

		\WikiSearch\WikiSearchServices::getLogger()->getLogger()->debug( 'Constructing QueryEngine from ElasticFactory' );

		if ( class_exists( '\SMW\StoreFactory' ) ) {
			$store = \SMW\StoreFactory::getStore();
		} else {
			$store = \SMW\ApplicationFactory::getInstance()->getStore();
		}

		$elastic_factory = new ElasticFactory();
		$query_engine = $elastic_factory->newQueryEngine( $store );

		\WikiSearch\WikiSearchServices::getLogger()->getLogger()->debug( 'Finished constructing QueryEngine from ElasticFactory' );

		$query_engine->getQueryResult( $query );
		$query_info = $query_engine->getQueryInfo();

		if ( !isset( $query_info["elastic"] ) ) {
			\WikiSearch\WikiSearchServices::getLogger()->getLogger()->critical( 'Base query {query} resulted in invalid query information: {queryInfo}', [
				'query' => $this->query,
				'queryInfo' => $query_info
			] );

			throw new \MWException( "Invalid query" );
		}

		return $query_info["elastic"];
	}
}
