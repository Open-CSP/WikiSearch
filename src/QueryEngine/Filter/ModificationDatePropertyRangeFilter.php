<?php


namespace WSSearch\QueryEngine\Filter;

use WSSearch\QueryEngine\Property;

/**
 * Class ModificationDatePropertyRangeFilter
 *
 * Filter pages based on their modification date.
 *
 * @package WSSearch\QueryEngine\Filter
 */
class ModificationDatePropertyRangeFilter extends PropertyRangeFilter {
    /**
     * ModificationDatePropertyRangeFilter constructor.
     *
     * @param int $gte The minimum modification date of pages to include in the results
     * @param int $lte The maximum modification date of pages to include in the results
     */
    public function __construct( int $gte, int $lte ) {
        parent::__construct( new Property( "Modification date" ), $gte, $lte );
    }
}