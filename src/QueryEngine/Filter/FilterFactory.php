<?php

namespace WSSearch\QueryEngine\Filter;

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

            if ( !isset( $range["lte"] ) ) {
                return null;
            }

            if ( !isset( $range["gte"] ) ) {
                return null;
            }

            return new PropertyRangeFilter(
                $array["key"],
                $range["gte"],
                $range["lte"]
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