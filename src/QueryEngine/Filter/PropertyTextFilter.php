<?php

namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
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
     * @param string $default_operator
     */
    public function __construct( $property, string $property_value_query, string $default_operator = "or" ) {
        if ( is_string( $property ) ) {
            $property = new PropertyFieldMapper( $property );
        }

        if ( !($property instanceof PropertyFieldMapper)) {
            throw new \InvalidArgumentException();
        }

        $this->property = $property;
        $this->property_value_query = $property_value_query;
        $this->default_operator = $default_operator;
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
		$search_term = trim( $search_term );
		$term_length = strlen( $search_term );

		if ( $term_length === 0 ) {
			return "*";
		}

		// Disable regex searches by replacing each "/" with "\/"
		$search_term = str_replace( "/", "\\/", $search_term );

		return $search_term;
	}
}