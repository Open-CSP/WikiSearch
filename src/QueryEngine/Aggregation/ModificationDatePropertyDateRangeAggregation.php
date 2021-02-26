<?php


namespace WSSearch\QueryEngine\Aggregation;

use WSSearch\QueryEngine\Property;

/**
 * Class ModificationDatePropertyDateRangeAggregation
 *
 * @package WSSearch\QueryEngine\Aggregation
 * @note These long class names are kinda ugly
 */
class ModificationDatePropertyDateRangeAggregation extends PropertyDateRangeAggregation {
    /**
     * ModificationDatePropertyDateRangeAggregation constructor.
     *
     * @param array $ranges The date ranges to aggregate
     */
    public function __construct( array $ranges ) {
        parent::__construct(
            "Modification date",
            new Property( "Modification date" ),
            $ranges
        );
    }
}