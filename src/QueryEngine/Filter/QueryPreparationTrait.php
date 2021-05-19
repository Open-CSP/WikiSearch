<?php


namespace WSSearch\QueryEngine\Filter;

/**
 * Trait QueryPreparationTrait
 *
 * This trait contains a method that can be used to prepare bare queries for use with ElasticSearch.
 *
 * @package WSSearch\QueryEngine\Filter
 */
trait QueryPreparationTrait {
	/**
	 * Prepares the query for use with ElasticSearch.
	 *
	 * @param string $search_term
	 * @return string
	 */
	public function prepareQuery( string $search_term ): string {
		$search_term = trim( $search_term );
		$term_length = strlen( $search_term );

		if ( $term_length === 0 ) {
			return "*";
		}

		// Disable regex searches by replacing each "/" with " "
		$search_term = str_replace( "/", ' ', $search_term );

		// Disable certain search operators by escaping them
		$search_term = str_replace( ":", '\:', $search_term );
		$search_term = str_replace( "+", '\+', $search_term );
		$search_term = str_replace( "=", '\=', $search_term );

		// Don't insert wildcard around terms when the user is performing an "advanced query"
		$advanced_search_chars = ["\"", "'", "AND", "NOT", "OR", "~", "(", ")", "?", "*", " -"];
		$is_advanced_query = array_reduce( $advanced_search_chars, function( bool $carry, $char ) use ( $search_term ) {
			return $carry ?: strpos( $search_term, $char ) !== false;
		}, false );

		if ( !$is_advanced_query ) {
			$search_term = $this->insertWildcards( $search_term );
		}

		return $search_term;
	}

	/**
	 * Inserts wild cards around each term in the provided search string.
	 *
	 * @param string $search_string
	 * @return string
	 */
	public function insertWildcards( string $search_string ): string {
		$terms = preg_split( "/((?<=[a-zA-Z_-])(?=$|[^a-zA-Z_-])\s*)/", $search_string, -1, PREG_SPLIT_DELIM_CAPTURE );

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
