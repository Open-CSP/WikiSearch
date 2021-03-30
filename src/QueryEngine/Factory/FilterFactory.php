<?php

namespace WSSearch\QueryEngine\Factory;

use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use WSSearch\QueryEngine\Filter\ChainedPropertyValuesFilter;
use WSSearch\QueryEngine\Filter\Filter;
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
            if ( !is_array( $array["range"] ) ) {
                return null;
            }

            $filter = new PropertyRangeFilter(
                $property_field_mapper,
                $array["range"]
            );
        } else if ( isset( $array["value"] ) ) {
            if ( !is_string( $array["value"] ) ) {
                return null;
            }

            // This is a "regular" property
            $filter = new PropertyValueFilter(
                $property_field_mapper,
                $array["value"]
            );
        } else {
            return null;
        }

        if ( $property_field_mapper->getChainedPropertyFieldMapper() === null ) {
            // This is not a chained filter property, so simply return the constructed filter
            return $filter;
        }

        // This is a chained filter property
        return new ChainedPropertyValuesFilter( $filter, $property_field_mapper->getChainedPropertyFieldMapper() );
    }
}