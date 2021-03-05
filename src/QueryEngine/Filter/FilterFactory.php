<?php

namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;

class FilterFactory {
    /**
     * Constructs a new Filter class from the given array. The given array directly corresponds to the array given by
     * the user through the API. Returns "null" on failure.
     *
     * @param array $array
     * @return Filter|null
     */
    public static function fromArray( array $array ) {
        if ( !isset( $array["key"] ) || !isset( $array["value"] ) ) {
            return null;
        }

        // TODO: Make this more general

        if ( isset( $array["range"] ) ) {
            $range = $array["range"];

            $options = [];
            $options[RangeQuery::LTE] = isset( $range["lte"] ) ? $range["lte"] : PHP_INT_MAX;
            $options[RangeQuery::GTE] = isset( $range["gte"] ) ? $range["gte"] : PHP_INT_MIN;

            return new PropertyRangeFilter(
                $array["key"],
                $options
            );
        } else {
            // This is a "regular" property
            return new PropertyFilter(
                $array["key"],
                $array["value"]
            );
        }
    }
}