<?php

namespace WikiSearch\QueryEngine;

use InvalidArgumentException;
use MWException;
use WikiSearch\Logger;

/**
 * Class QueryCombinator
 *
 * Allows combining ElasticSearch queries. For instance, to combine three queries:
 *
 * $query_combinator = new QueryCombinator( $query1 );
 * $combined_query = $query_combinator->add( $query2 )->add( $query3 )->getQuery();
 *
 * @package WikiSearch\QueryEngine
 */
class QueryCombinator {
	/**
	 * @var array
	 */
	private array $query;

	/**
	 * QueryCombinator constructor.
	 *
	 * @param array $initial_query
	 */
	public function __construct( array $initial_query ) {
		if ( !isset( $initial_query["body"]["query"] ) ) {
			Logger::getLogger()->error( 'Tried to create combinator for invalid query' );

			throw new InvalidArgumentException( 'Trie dto create a combinator for invalid query' );
		}

		$this->query = $initial_query;
	}

	/**
	 * Returns the combined query.
	 *
	 * @return array
	 */
	public function getQuery(): array {
		return $this->query;
	}

	/**
	 * Alias of $this->add().
	 *
	 * @param array $query
	 * @return QueryCombinator
	 *
	 * @throws MWException
	 */
	public function combine( array $query ): QueryCombinator {
		return $this->add( $query );
	}

	/**
	 * Adds the given query to the current query. If the queries could not be combined, an MWException is
	 * thrown.
	 *
	 * @param array $query
	 * @return QueryCombinator
	 *
	 * @throws MWException If the queries cannot be combined
	 */
	public function add( array $query ): QueryCombinator {
		if ( !isset( $query["body"]["query"] ) ) {
			Logger::getLogger()->error( 'Tried to combine invalid queries' );

			throw new MWException( "Invalid query returned by Semantic MediaWiki" );
		}

		$add_query_body = $query["body"]["query"];
		$current_query_body = $this->query["body"]["query"];

		$this->query["body"]["query"] = [
			"bool" => [
				"must" => [
					$add_query_body,
					$current_query_body
				]
			]
		];

		return $this;
	}
}
