<?php

namespace WSSearch\QueryEngine\Factory;

use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use WSSearch\QueryEngine\Filter\ChainedPropertyFilter;
use WSSearch\QueryEngine\Filter\Filter;
use WSSearch\QueryEngine\Filter\HasPropertyFilter;
use WSSearch\QueryEngine\Filter\PropertyTextFilter;
use WSSearch\QueryEngine\Filter\PropertyValueFilter;
use WSSearch\QueryEngine\Filter\PropertyRangeFilter;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class FilterFactory
 *
 * @package WSSearch\QueryEngine\Factory
 */
class FilterFactory {
    /**
     * Constructs a new Filter class from the given array. The given array directly corresponds to the array given by
     * the user through the API. Returns "null" on failure.
     *
     * @param array $array
     * @return Filter|null
     */
    public static function fromArray( array $array ) {
        if ( !isset( $array["key"] ) ) {
            return null;
        }

        if ( !is_string( $array["key"] ) && !($array["key"] instanceof PropertyFieldMapper) ) {
            return null;
        }

        $property_field_mapper = new PropertyFieldMapper( $array["key"] );

        if ( isset( $array["range"] ) ) {
            $filter = self::rangeFilterFromArray( $array, $property_field_mapper );
        } else if ( isset( $array[ "value" ] ) ) {
            $filter = self::valueFilterFromArray( $array, $property_field_mapper );
        } else {
            return null;
        }

        if ( $filter !== null && $property_field_mapper->getChainedPropertyFieldMapper() !== null ) {
            // This is a chained filter property
            return new ChainedPropertyFilter( $filter, $property_field_mapper->getChainedPropertyFieldMapper() );
        }

        // This is not a chained filter property, so simply return the constructed filter (or null on failure)
        return $filter;
    }

    /**
     * Constructs a new range filter from the given array. Returns null on failure.
     *
     * @param array $array
     * @param PropertyFieldMapper $property_field_mapper
     * @return PropertyRangeFilter|null
     */
    private static function rangeFilterFromArray( array $array, PropertyFieldMapper $property_field_mapper ) {
        if ( !is_array( $array["range"] ) ) {
            return null;
        }

        return new PropertyRangeFilter(
            $property_field_mapper,
            $array["range"]
        );
    }

    /**
     * Constructs a new value filter from the given array. Returns null on failure.
     *
     * @param array $array
     * @param PropertyFieldMapper $property_field_mapper
     * @return HasPropertyFilter|PropertyTextFilter|PropertyValueFilter|null
     */
    private static function valueFilterFromArray( array $array, PropertyFieldMapper $property_field_mapper ) {
        if ( !is_string( $array["value"] ) ) {
            return null;
        }

        if ( isset( $array["type"] ) ) {
            if ( $array["type"] === "query") {
                return new PropertyTextFilter( $property_field_mapper, $array["value"] );
            }

            return null;
        }

        if ( $array["value"] === "+" ) {
            // This should match any page that has the property
            return new HasPropertyFilter( $property_field_mapper );
        }

        // This is a "regular" property
        return new PropertyValueFilter(
            $property_field_mapper,
            $array["value"]
        );
    }
}