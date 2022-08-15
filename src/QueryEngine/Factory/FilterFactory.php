<?php

namespace WikiSearch\QueryEngine\Factory;

use WikiSearch\Logger;
use WikiSearch\QueryEngine\Filter\AbstractFilter;
use WikiSearch\QueryEngine\Filter\ChainedPropertyFilter;
use WikiSearch\QueryEngine\Filter\HasPropertyFilter;
use WikiSearch\QueryEngine\Filter\PropertyFilter;
use WikiSearch\QueryEngine\Filter\PropertyRangeFilter;
use WikiSearch\QueryEngine\Filter\PropertyTextFilter;
use WikiSearch\QueryEngine\Filter\PropertyValueFilter;
use WikiSearch\QueryEngine\Filter\PropertyValuesFilter;
use WikiSearch\SearchEngineConfig;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class FilterFactory
 *
 * @package WikiSearch\QueryEngine\Factory
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
	public static function fromArray( array $array, SearchEngineConfig $config ): ?AbstractFilter {
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
			$array["key"] :
			new PropertyFieldMapper( $array["key"] );

		$filter = self::filterFromArray( $array, $property_field_mapper, $config );

		if ( in_array( $array["key"], $config->getSearchParameter( "post filter properties" ), true ) ) {
			$filter->setPostFilter();
		}

        if ( isset( $array["negate"] ) && $array["negate"] === true ) {
            $filter->setNegated();
        }

		if ( $filter !== null && $property_field_mapper->isChained() ) {
			$filter = new ChainedPropertyFilter( $filter );
		}

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
	): ?PropertyFilter {
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
	 * @return PropertyValuesFilter|PropertyValueFilter|null
	 */
	private static function valueFilterFromValue(
		$value,
		PropertyFieldMapper $property_field_mapper
	): ?PropertyFilter {
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
	): ?PropertyTextFilter {
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
	private static function rangeFilterFromRange(
		array $range,
		PropertyFieldMapper $property_field_mapper
	): PropertyRangeFilter {
		return new PropertyRangeFilter( $property_field_mapper, $range );
	}

	/**
	 * @param string|bool $value
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return PropertyValueFilter
	 */
	private static function propertyValueFilterFromValue(
		$value,
		PropertyFieldMapper $property_field_mapper
	): PropertyValueFilter {
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
	): PropertyTextFilter {
		return new PropertyTextFilter( $property_field_mapper, $text, $default_operator );
	}

	/**
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return HasPropertyFilter
	 */
	private static function hasPropertyFilterFromProperty(
		PropertyFieldMapper $property_field_mapper
	): HasPropertyFilter {
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
	): PropertyValuesFilter {
		return new PropertyValuesFilter( $property_field_mapper, $values );
	}
}
