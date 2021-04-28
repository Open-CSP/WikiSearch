<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use WSSearch\SearchEngine;
use WSSearch\SearchEngineException;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class SimpleQueryFilter
 *
 * @package WSSearch\QueryEngine\Filter
 */
class SimpleQueryFilter extends AbstractFilter {
	/**
	 * @var string The query to filter on
	 */
	private $query;

	/**
	 * @var string[]
	 */
	private $fields;

	/**
	 * SearchTermFilter constructor.
	 *
	 * @param string $query
	 * @param string[] $properties
	 */
	public function __construct( string $query, array $properties ) {
		$this->query = $query;
		$this->fields = array_map(function( string $property_name ): string {
			return ( new PropertyFieldMapper( $property_name ) )->getPropertyField();
		}, $properties );
	}

	/**
	 * Sets the query to filter on.
	 *
	 * @param string $query
	 */
	public function setQuery( string $query ) {
		$this->query = $query;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): BoolQuery {
		$search_term = $this->prepareSearchTerm( $this->query );

		$bool_query = new BoolQuery();
		$query_string_query = new QueryStringQuery( $search_term );

		$query_string_query->setParameters( [
			"fields" => $this->fields
		] );

		$bool_query->add( $query_string_query, BoolQuery::SHOULD );

		return $bool_query;
	}

	/**
	 * Prepares the search term for use with ElasticSearch.
	 *
	 * @param string $search_term
	 * @return string
	 */
	private function prepareSearchTerm( string $search_term ): string {
		// TODO: Remove code duplication (this function is identical to the one in SearchTermFilter)
		$search_term = trim( $search_term );
		$term_length = strlen( $search_term );

		if ( $term_length === 0 ) {
			return "*";
		}

		// Disable regex searches by replacing each "/" with " "
		$search_term = str_replace( "/", ' ', $search_term );

		// Don't insert wildcard around terms when the user is performing an "advanced query"
		$advanced_search_chars = ["\"", "'", "AND", "NOT", "OR", "~", "(", ")", "?"];
		$is_advanced_query = array_reduce( $advanced_search_chars, function( bool $carry, $char ) use ( $search_term ) {
			return $carry ?: strpos( $search_term, $char ) !== false;
		}, false );

		if ( !$is_advanced_query ) {
			$search_term = $this->insertWildcards( $search_term );
		}

		// Disable certain search operators by escaping them
		$search_term = str_replace( ":", '\:', $search_term );
		$search_term = str_replace( "+", '\+', $search_term );
		$search_term = str_replace( "-", '\-', $search_term );
		$search_term = str_replace( "=", '\=', $search_term );

		return $search_term;
	}

	/**
	 * Inserts wild cards around each term in the provided search string.
	 *
	 * @param string $search_string
	 * @return string
	 */
	private function insertWildcards( string $search_string ): string {
		// TODO: Remove code duplication (this function is identical to the one in SearchTermFilter)
		$terms = preg_split( "/((?<=\w)\b\s*)/", $search_string, -1, PREG_SPLIT_DELIM_CAPTURE );

		// $terms is now an array where every even element is a term (0 is a term, 2 is a term, etc.), and
		// every odd element the delimiter between that term and the next term. Calling implode() on
		// $terms gives back the original search string

		$num_terms = count( $terms );

		// Insert quotes around each term
		for ( $idx = 0; $idx < $num_terms; $idx++ ) {
			$is_term = ($idx % 2) === 0 && !empty( $terms[$idx] );

			if ( $is_term ) {
				$terms[$idx] = "*{$terms[$idx]}*";
			}
		}

		// Join everything together again to get the search string
		return implode( "", $terms );
	}
}
