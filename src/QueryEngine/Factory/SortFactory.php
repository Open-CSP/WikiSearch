<?php

namespace WikiSearch\QueryEngine\Factory;

use WikiSearch\Logger;
use WikiSearch\QueryEngine\Sort\PropertySort;
use WikiSearch\QueryEngine\Sort\Sort;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class SortFactory
 *
 * @package WikiSearch\QueryEngine\Factory
 */
class SortFactory {
	/**
	 * Constructs a new Sort class from the given array. The given array directly corresponds to the array given by
	 * the user through the API. Returns "null" on failure.
	 *
	 * @param array $array
	 * @return Sort|null
	 */
	public static function fromArray( array $array ): Sort {
		Logger::getLogger()->debug( 'Constructing Sort from array' );

		if ( !isset( $array["type"] ) ) {
			Logger::getLogger()->debug( 'Failed to construct Sort from array: missing "type"' );

			return null;
		}

		switch ( $array["type"] ) {
			case "property":
				return self::propertySortFromArray( $array );
			default:
				Logger::getLogger()->debug( 'Failed to construct Sort from array: invalid "type"' );

				return null;
		}
	}

	/**
	 * Constructs a new PropertySort from the given array. Returns null on failure.
	 *
	 * @param array $array
	 * @return PropertySort|null
	 */
	private static function propertySortFromArray( array $array ): PropertySort {
		if ( !isset( $array["property"] ) ) {
			Logger::getLogger()->debug( 'Failed to construct PropertySort from array: missing "property"' );

			return null;
		}

		$property = $array["property"];

		if ( !is_string( $property ) && !( $property instanceof PropertyFieldMapper ) ) {
			Logger::getLogger()->debug( 'Failed to construct PropertySort from array: invalid "property"' );

			return null;
		}

		$order = isset( $array["order"] ) ? $array["order"] : null;

		return new PropertySort( $property, $order );
	}
}
