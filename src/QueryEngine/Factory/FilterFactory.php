<?php

namespace WSSearch\QueryEngine\Factory;

use WSSearch\Logger;
use WSSearch\QueryEngine\Filter\AbstractFilter;
use WSSearch\QueryEngine\Filter\ChainedPropertyFilter;
use WSSearch\QueryEngine\Filter\HasPropertyFilter;
use WSSearch\QueryEngine\Filter\PropertyRangeFilter;
use WSSearch\QueryEngine\Filter\PropertyTextFilter;
use WSSearch\QueryEngine\Filter\PropertyValueFilter;
use WSSearch\QueryEngine\Filter\PropertyValuesFilter;
use WSSearch\SearchEngineConfig;
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
	 * @param SearchEngineConfig $config
	 * @return AbstractFilter|null
	 */
	public static function fromArray( array $array, SearchEngineConfig $config ) {
		Logger::getLogger()->debug( 'Constructing Filter from array' );

		if ( !isset( $array["key"] ) ) {
			Logger::getLogger()->debug( 'Failed to construct Filter from array: missing "key"' );

			return null;
		}

		if ( !is_string( $array["key"] ) && !( $array["key"] instanceof PropertyFieldMapper ) ) {
			Logger::getLogger()->debug( 'Failed to construct Filter from array: invalid "key"' );

			return null;
		}

		$property_field_mapper = $array["key"] instanceof PropertyFieldMapper ?
			$array["key"] : new PropertyFieldMapper( $array["key"] );
		$filter = self::filterFromArray( $array, $property_field_mapper, $config );

		$post_filter_properties = $config->getSearchParameter( "post filter properties" );

		if ( in_array( $array["key"], $post_filter_properties, true ) ) {
			$filter->setPostFilter();
		}

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
	 * @param SearchEngineConfig $config
	 * @return AbstractFilter|null
	 */
	private static function filterFromArray(
		array $array,
		PropertyFieldMapper $property_field_mapper,
		SearchEngineConfig $config
	) {
		if ( isset( $array["range"] ) ) {
			if ( !is_array( $array["range"] ) ) {
				Logger::getLogger()->debug( 'Failed to construct Filter from array: invalid "range"' );

				return null;
			}

			return self::rangeFilterFromRange( $array["range"], $property_field_mapper );
		}

		if ( isset( $array["type"] ) ) {
			if ( !is_string( $array["type"] ) ) {
				Logger::getLogger()->debug( 'Failed to construct Filter from array: invalid "type"' );

				return null;
			}

			return self::typeFilterFromArray( $array["type"], $array, $property_field_mapper, $config );
		}

		if ( isset( $array["value"] ) ) {
			return self::valueFilterFromValue( $array["value"], $property_field_mapper );
		}

		return null;
	}

	/**
	 * Constructs a new value filter from the given array. Returns null on failure.
	 *
	 * @param mixed $value
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return AbstractFilter|null
	 */
	private static function valueFilterFromValue( $value, PropertyFieldMapper $property_field_mapper ) {
		if ( $value === "+" ) {
			return self::hasPropertyFilterFromProperty( $property_field_mapper );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				if ( !in_array( gettype( $v ), [ "boolean", "string", "integer", "double", "float" ] ) ) {
					Logger::getLogger()->debug( 'Failed to construct Filter from array: invalid "value"' );

					return null;
				}
			}

			return self::propertyValuesFilterFromValues( $value, $property_field_mapper );
		}

		if ( !in_array( gettype( $value ), [ "boolean", "string", "integer", "double", "float" ] ) ) {
			Logger::getLogger()->debug( 'Failed to construct Filter from array: invalid "value"' );

			return null;
		}

		return self::propertyValueFilterFromValue( $value, $property_field_mapper );
	}

	/**
	 * @param string $type
	 * @param array $array
	 * @param PropertyFieldMapper $property_field_mapper
	 * @param SearchEngineConfig $config
	 * @return PropertyTextFilter|null
	 */
	private static function typeFilterFromArray(
		string $type,
		array $array,
		PropertyFieldMapper $property_field_mapper,
		SearchEngineConfig $config
	) {
		switch ( $type ) {
			case "query":
				if ( !isset( $array["value"] ) || !is_string( $array["value"] ) ) {
					Logger::getLogger()->debug( 'Failed to construct Filter from array: missing/invalid "value"' );

					return null;
				}

				$default_operator = $config->getSearchParameter( "default operator" ) === "and" ?
					"and" : "or";
				return self::propertyTextFilterFromText( $array["value"], $default_operator, $property_field_mapper );
			default:
				Logger::getLogger()->debug( 'Failed to construct Filter from array: invalid "type"' );

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
	 * @param string $text
	 * @param string $default_operator
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return PropertyTextFilter
	 */
	private static function propertyTextFilterFromText(
		string $text,
		string $default_operator,
		PropertyFieldMapper $property_field_mapper
	) {
		return new PropertyTextFilter( $property_field_mapper, $text, $default_operator );
	}

	/**
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return HasPropertyFilter
	 */
	private static function hasPropertyFilterFromProperty( PropertyFieldMapper $property_field_mapper ) {
		return new HasPropertyFilter( $property_field_mapper );
	}

	/**
	 * @param array $values
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return PropertyValuesFilter
	 */
	private static function propertyValuesFilterFromValues(
		array $values,
		PropertyFieldMapper $property_field_mapper
	) {
		return new PropertyValuesFilter( $property_field_mapper, $values );
	}
}
