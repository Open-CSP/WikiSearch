<?php


namespace WSSearch\QueryEngine\Factory;

use WSSearch\QueryEngine\Aggregation\Aggregation;
use WSSearch\QueryEngine\Aggregation\PropertyAggregation;
use WSSearch\QueryEngine\Aggregation\PropertyRangeAggregation;

/**
 * Class AggregationFactory
 *
 * @package WSSearch\QueryEngine\Factory
 */
class AggregationFactory {
    /**
     * Constructs a new Aggregation class from the given array. The given array directly corresponds to the array given by
     * the user through the API. Returns null on failure.
     *
     * @param array $array
     * @return Aggregation|null
     */
    public static function fromArray( array $array ) {
        if ( !isset( $array["type"] ) ) {
            return null;
        }

        switch ( $array["type"] ) {
            case "range":
                return self::propertyRangeAggregationFromArray( $array );
            case "property":
                return self::propertyAggregationFromArray( $array );
            default:
                return null;
        }
    }

    /**
     * Constructs a new PropertyRangeAggregation class from the given array. Returns null
     * on failure.
     *
     * @param array $array
     * @return PropertyRangeAggregation|null
     */
    private static function propertyRangeAggregationFromArray( array $array ) {
        $aggregation_name = isset( $array["name"] ) ? $array["name"] : null;

        if ( !isset( $array["ranges"] ) ) return null;
        $ranges = $array["ranges"];

        if ( !isset( $array["property"] ) ) return null;
        $property = $array["property"];

        return new PropertyRangeAggregation( $property, $ranges, $aggregation_name );
    }

    /**
     * Constructs a new PropertyRangeAggregation class from the given array. Returns null
     * on failure.
     *
     * @param array $array
     * @return PropertyAggregation|null
     */
    private static function propertyAggregationFromArray( array $array ) {
        $aggregation_name = isset( $array["name"] ) ? $array["name"] : null;

        if ( !isset( $array["property"] ) ) return null;
        $property = $array["property"];

        return new PropertyAggregation( $property, $aggregation_name );
    }
}