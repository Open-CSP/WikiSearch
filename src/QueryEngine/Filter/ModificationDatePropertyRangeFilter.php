<?php


namespace WSSearch\QueryEngine\Filter;


use ONGR\ElasticsearchDSL\BuilderInterface;
use WSSearch\QueryEngine\Property;

class ModificationDatePropertyDateRangeFilter extends PropertyDateRangeFilter {
    /**
     * Returns the property to which the filter applies.
     *
     * @return Property
     */
    protected function getProperty(): Property {
        return new Property( "Modification date" );
    }
}