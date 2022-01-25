<?php

namespace WSSearch\SMW;

use SMW\Elastic\ElasticFactory;
use WSSearch\Logger;

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
		Logger::getLogger()->debug( 'Converting base Semantic MediaWiki query to ElasticSearch query: {query}', [
			'query' => $this->query
		] );

		list( $query_string, $parameters, $printouts ) = \SMWQueryProcessor::getComponentsFromFunctionParams(
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

		Logger::getLogger()->debug( 'Constructing QueryEngine from ElasticFactory' );

		$elastic_factory = new ElasticFactory();
		$store = \SMW\ApplicationFactory::getInstance()->getStore();
		$query_engine = $elastic_factory->newQueryEngine( $store );

		Logger::getLogger()->debug( 'Finished constructing QueryEngine from ElasticFactory' );

		$query_engine->getQueryResult( $query );
		$query_info = $query_engine->getQueryInfo();

		if ( !isset( $query_info["elastic"] ) ) {
			Logger::getLogger()->critical( 'Base query {query} resulted in invalid query information: {queryInfo}', [
				'query' => $this->query,
				'queryInfo' => $query_info
			] );

			throw new \MWException( "Invalid query" );
		}

		return $query_info["elastic"];
	}
}
