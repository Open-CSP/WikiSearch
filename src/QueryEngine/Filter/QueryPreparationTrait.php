<?php

namespace WikiSearch\QueryEngine\Filter;

/**
 * This trait contains a method that can be used to prepare bare queries for use with ElasticSearch.
 */
trait QueryPreparationTrait {
	/**
	 * Prepares the query for use with ElasticSearch.
	 *
	 * @param string $term
	 * @return string
	 */
	private static function prepareQuery( string $term ): string {
		$term = trim( $term );

		if ( strlen( $term ) === 0 ) {
			return "*";
		}

        $term = preg_replace( '/(:|\+|=|\/)/', '\\\\$1', $term );
        $term = preg_replace( '/(\.)/', '*', $term );
		$advancedQuery = array_reduce(
            [ "\"", "'", "AND", "NOT", "OR", "~", "(", ")", "?", "*", " -" ],
            function ( bool $carry, $char ) use ( $term ) {
                return $carry ?: str_contains( $term, $char );
            },
            false
        );

        return $advancedQuery ? $term : self::insertWildcards( $term );
	}

    /**
     * Inserts wild cards around each term in the provided search string.
     *
     * @param string $term
     * @return string
     */
	public static function insertWildcards( string $term ): string {
        if ( !$term ) {
            return '*';
        }

        $wordChars = 'a-zA-Z_\.\-0-9:\/\\\\';
        $terms = preg_split(
            '/((?<=[' . $wordChars . '])(?=$|[^' . $wordChars . '])\s*)/',
            $term, -1, PREG_SPLIT_DELIM_CAPTURE
        );

        $numTerms = count( $terms );
        for ( $idx = 0; $idx < $numTerms; $idx++ ) {
            if ( $idx % 2 !== 0 || empty( $terms[$idx] ) ) {
                continue;
            }

            $terms[$idx] = "*{$terms[$idx]}*";
        }

        return preg_replace('/\*+/', '*', '*' . implode( '', $terms ) . '*' );
	}
}
