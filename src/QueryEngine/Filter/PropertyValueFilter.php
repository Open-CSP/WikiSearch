<?php

namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyFilter
 *
 * Filters pages based on the values of their properties.
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-term-query.html
 */
class PropertyValueFilter implements Filter {
    /**
     * @var \WSSearch\SMW\PropertyFieldMapper The property to filter on
     */
    private $property;

    /**
     * @var string The value the property to filter on
     */
    private $property_value;

    /**
     * PropertyFilter constructor.
     *
     * @param PropertyFieldMapper|string $property The name or object of the property to filter on
     * @param string $property_value The value the property to filter on
     */
    public function __construct( $property, string $property_value ) {
        if ( is_string( $property ) ) {
            $property = new PropertyFieldMapper( $property );
        }

        if ( !($property instanceof PropertyFieldMapper)) {
            throw new \InvalidArgumentException();
        }

        $this->property = $property;
        $this->property_value = $property_value;
    }

    /**
     * Sets the property this filter will filter on.
     *
     * @param \WSSearch\SMW\PropertyFieldMapper $property_name
     */
    public function setPropertyName(PropertyFieldMapper $property_name ) {
        $this->property_name = $property_name;
    }

    /**
     * Sets the value of the property this filter will filter on.
     *
     * @param string $property_value
     */
    public function setPropertyValue( string $property_value ) {
        $this->property_value = $property_value;
    }

    /**
     * Converts the object to a BuilderInterface for use in the QueryEngine.
     *
     * @return BoolQuery
     */
    public function toQuery(): BoolQuery {
        $term_query = new TermQuery(
            $this->property->getPropertyField( true ),
            $this->property_value
        );

        $bool_query = new BoolQuery();
        $bool_query->add( $term_query, BoolQuery::FILTER );

        /*
         * Example of such a query:
         *
         *  "bool": {
         *      "filter": {
         *          "term": {
         *              "P:0.wpgID": 0
         *          }
         *      }
         *  }
         */

        return $bool_query;
    }
}