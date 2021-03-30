<?php

namespace WSSearch\QueryEngine\Factory;

use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
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

        if ( isset( $array["range"] ) ) {
            if ( !is_array( $array["range"] ) ) {
                return null;
            }

            return new PropertyRangeFilter(
                $array["key"],
                $array["range"]
            );
        } else if ( isset( $array["value"] ) ) {
            if ( !is_string( $array["value"] ) ) {
                return null;
            }

            // This is a "regular" property
            return new PropertyValueFilter(
                $array["key"],
                $array["value"]
            );
        } else {
            return null;
        }
    }
}