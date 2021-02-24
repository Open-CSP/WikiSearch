<?php

namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use WSSearch\QueryEngine\Property;

/**
 * Class PropertyFilter
 *
 * Filters pages based on the values of their properties.
 *
 * @package WSSearch\QueryEngine\Filter
 */
class PropertyFilter extends Filter {
    /**
     * @var string The name of the property to filter on
     */
    private $property_name;

    /**
     * @var string The value the property to filter on
     */
    private $property_value;

    /**
     * PropertyFilter constructor.
     *
     * @param string $property_name The name of the property to filter on
     * @param string $property_value The value the property to filter on
     */
    public function __construct( string $property_name, string $property_value ) {
        $this->property_name = $property_name;
        $this->property_value = $property_value;
    }

    /**
     * Sets the name of the property this filter will filter on.
     *
     * @param string $property_name
     */
    public function setPropertyName( string $property_name ) {
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
     * @return BuilderInterface
     */
    public function toQuery(): BuilderInterface {
        $property = new Property( $this->property_name );

        return new TermQuery(
            "{$property->getPropertyField()}.keyword",
            $this->property_value
        );
    }
}