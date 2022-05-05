<?php

namespace WikiSearch\QueryEngine\Filter;

/**
 * Trait QueryPreparationTrait
 *
 * This trait contains a method that can be used to prepare bare queries for use with ElasticSearch.
 *
 * @package WikiSearch\QueryEngine\Filter
 */
trait QueryPreparationTrait {
    private array $disabled_search_operators = [ '/', ':', '+', '=' ];

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

        foreach ( $this->disabled_search_operators as $operator ) {
            $search_term = str_replace( $operator, '\\' . $operator, $search_term );
        }

		// Don't insert wildcard around terms when the user is performing an "advanced query"
        return $this->isAdvancedQuery( $search_term ) ? $search_term : $this->insertWildcards( $search_term );
	}

	/**
	 * Inserts wild cards around each term in the provided search string.
	 *
	 * @param string $search_string
	 * @return string
	 */
	public function insertWildcards( string $search_string ): string {
		$word_character_set = "a-zA-Z_\-0-9";
		$terms = preg_split(
			"/((?<=[$word_character_set])(?=$|[^$word_character_set])\s*)/",
			$search_string,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		// $terms is now an array where every even element is a term (0 is a term, 2 is a term, etc.), and
		// every odd element the delimiter between that term and the next term. Calling implode() on
		// $terms gives back the original search string

		$num_terms = count( $terms );

		// Insert quotes around each term
		for ( $idx = 0; $idx < $num_terms; $idx++ ) {
			$is_term = ( $idx % 2 ) === 0 && !empty( $terms[$idx] );

			if ( $is_term ) {
				$terms[$idx] = "*{$terms[$idx]}*";
			}
		}

		// Join everything together again to get the search string
		return implode( "", $terms );
	}

    /**
     * Returns true if and only if this is an advanced search query.
     *
     * @param string $search_term
     * @return bool
     */
    private function isAdvancedQuery( string $search_term ): bool {
        $advanced_search_chars = [ "\"", "'", "AND", "NOT", "OR", "~", "(", ")", "?", "*", " -" ];

        foreach ( $advanced_search_chars as $advanced_search_char ) {
            if ( mb_strpos( $search_term, $advanced_search_char ) ) {
                return true;
            }
        }

        return false;
    }
}
