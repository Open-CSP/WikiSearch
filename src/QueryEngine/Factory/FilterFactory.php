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
        $filter = self::filterFromArray( $array, $property_field_mapper );

        if ( $filter !== null && $property_field_mapper->isChained() ) {
            // This is a chained filter property
            return new ChainedPropertyFilter( $filter, $property_field_mapper->getChainedPropertyFieldMapper() );
        }

        // This is not a chained filter property, so simply return the constructed filter (or null on failure)
        return $filter;
    }

	/**
	 * Constructs a new filter from the given array.
	 *
	 * @param array $array
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return HasPropertyFilter|PropertyRangeFilter|PropertyTextFilter|PropertyValueFilter|null
	 */
    private static function filterFromArray( array $array, PropertyFieldMapper $property_field_mapper ) {
    	if ( isset( $array["range"] ) ) {
			if ( !is_array( $array["range"] ) ) {
				return null;
			}

    		return self::rangeFilterFromRange( $array["range"], $property_field_mapper );
		}

    	if ( isset( $array["value"] ) ) {
			if ( !is_string( $array["value"] ) && !is_bool( $array["value"] ) ) {
				return null;
			}

			return self::valueFilterFromValue( $array["value"], $property_field_mapper );
		}

		if ( isset( $array["type"] ) ) {
			if ( !is_string( $array["type"] ) ) {
				return null;
			}

			return self::typeFilterFromArray( $array["type"], $array, $property_field_mapper );
		}

    	return null;
	}

    /**
     * Constructs a new value filter from the given array. Returns null on failure.
     *
     * @param string|bool $value
     * @param PropertyFieldMapper $property_field_mapper
     * @return HasPropertyFilter|PropertyTextFilter|PropertyValueFilter|null
     */
    private static function valueFilterFromValue( $value, PropertyFieldMapper $property_field_mapper ) {
        if ( $value === "+" ) {
            return self::hasPropertyFilterFromProperty( $property_field_mapper );
        }

        return self::propertyValueFilterFromValue( $value, $property_field_mapper );
    }

	/**
	 * @param string $type
	 * @param array $array
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return PropertyTextFilter|null
	 */
	private static function typeFilterFromArray( string $type, array $array, PropertyFieldMapper $property_field_mapper ) {
		switch ( $type ) {
			case "query":
				return self::propertyTextFilterFromArray( $array, $property_field_mapper );
			default:
				return null;
		}
	}

	/**
	 * Constructs a new range filter from the given range.
	 *
	 * @param array $range
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return PropertyRangeFilter
	 */
	private static function rangeFilterFromRange( array $range, PropertyFieldMapper $property_field_mapper ) {
		return new PropertyRangeFilter( $property_field_mapper, $range );
	}

	/**
	 * @param string|bool $value
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return PropertyValueFilter
	 */
	private static function propertyValueFilterFromValue( $value, PropertyFieldMapper $property_field_mapper ) {
		return new PropertyValueFilter( $property_field_mapper, $value );
	}

	/**
	 * @param array $array
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return PropertyTextFilter
	 */
	private static function propertyTextFilterFromArray( array $array, PropertyFieldMapper $property_field_mapper ) {
		return new PropertyTextFilter( $property_field_mapper, $array["value"] );
	}

	/**
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return HasPropertyFilter
	 */
	private static function hasPropertyFilterFromProperty( PropertyFieldMapper $property_field_mapper ) {
		return new HasPropertyFilter( $property_field_mapper );
	}
}