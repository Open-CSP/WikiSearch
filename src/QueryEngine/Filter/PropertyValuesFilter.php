<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyValuesFilter
 *
 * Filters pages based on the values of their properties. Adds a page to the result if one or
 * more of the specified terms was matched. This filter does not take property chains into account.
 *
 * @see ChainedPropertyValuesFilter for a filter that takes property chains into account
 *
 * @package WSSearch\QueryEngine\Filter
 */
class PropertyValuesFilter implements Filter {
    /**
     * @var PropertyFieldMapper
     */
    private $property;

    /**
     * @var array
     */
    private $property_values;

    /**
     * PropertyTermsFilter constructor.
     *
     * @param PropertyFieldMapper $property
     * @param array $terms
     */
    public function __construct(PropertyFieldMapper $property, array $terms ) {
        $this->property = $property;
        $this->property_values = $terms;
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
     * Sets the allowed values of the property this filter will filter on.
     *
     * @param array $property_values
     */
    public function setPropertyValues( array $property_values ) {
        $this->property_values = $property_values;
    }

    /**
     * Converts the object to a BuilderInterface for use in the QueryEngine.
     *
     * @return BoolQuery
     */
    public function toQuery(): BoolQuery {
        $terms_query = new TermsQuery( $this->property->getPropertyField( true ), $this->property_values );

        $bool_query = new BoolQuery();
        $bool_query->add( $terms_query, BoolQuery::FILTER );

        return $bool_query;
    }
}