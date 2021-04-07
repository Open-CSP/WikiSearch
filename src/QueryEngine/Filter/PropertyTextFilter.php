<?php

namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use WSSearch\SearchEngine;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyTextFilter
 *
 * Filters pages based on the value the specified property has. Unlike PropertyValueFilter, which requires a
 * full match of the given property value, this filter loosely matches based on the provided query string.
 *
 * @see ChainedPropertyFilter for a filter that takes property chains into account
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-simple-query-string-query.html
 */
class PropertyTextFilter extends AbstractFilter {
    /**
     * @var PropertyFieldMapper The property to filter on
     */
    private $property;

    /**
     * @var string The query string used to match the property value
     */
    private $property_value_query;

    /**
     * @var string The default operator to use
     */
    private $default_operator;

    /**
     * PropertyFilter constructor.
     *
     * @param PropertyFieldMapper|string $property The name or object of the property to filter on
     * @param string $property_value_query The query string used to match the property value
     */
    public function __construct( $property, string $property_value_query ) {
        if ( is_string( $property ) ) {
            $property = new PropertyFieldMapper( $property );
        }

        if ( !($property instanceof PropertyFieldMapper)) {
            throw new \InvalidArgumentException();
        }

        $this->property = $property;
        $this->property_value_query = $property_value_query;
        $this->default_operator = SearchEngine::$config->getSearchParameter("default operator") === "and" ? "and" : "or";
    }

    /**
     * Sets the property this filter will filter on.
     *
     * @param PropertyFieldMapper $property
     */
    public function setPropertyName( PropertyFieldMapper $property ) {
        $this->property = $property;
    }

    /**
     * Sets the query string used to match the property value.
     *
     * @param string $property_value_query
     */
    public function setPropertyValueQuery( string $property_value_query ) {
        $this->property_value_query = $property_value_query;
    }

    /**
     * Converts the object to a BuilderInterface for use in the QueryEngine.
     *
     * @return BoolQuery
     */
    public function toQuery(): BoolQuery {
		$search_term = $this->prepareSearchTerm( $this->property_value_query );

        $query_string_query = new QueryStringQuery( $search_term );
        $query_string_query->setParameters( [
            "fields" => [$this->property->getPropertyField()],
            "default_operator" => $this->default_operator
        ] );

        $bool_query = new BoolQuery();
        $bool_query->add( $query_string_query, BoolQuery::FILTER );

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