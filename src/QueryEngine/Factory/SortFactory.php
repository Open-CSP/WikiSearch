<?php

namespace WSSearch\QueryEngine\Factory;

use WSSearch\QueryEngine\Sort\PropertySort;
use WSSearch\QueryEngine\Sort\Sort;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class SortFactory
 *
 * @package WSSearch\QueryEngine\Factory
 */
class SortFactory {
	/**
	 * Constructs a new Sort class from the given array. The given array directly corresponds to the array given by
	 * the user through the API. Returns "null" on failure.
	 *
	 * @param array $array
	 * @return Sort|null
	 */
	public static function fromArray( array $array ) {
		if ( !isset( $array["type"] ) ) {
			return null;
		}

		switch ( $array["type"] ) {
			case "property":
				return self::propertySortFromArray( $array );
			default:
				return null;
		}
	}

	/**
	 * Constructs a new PropertySort from the given array. Returns null on failure.
	 *
	 * @param array $array
	 * @return PropertySort|null
	 */
	private static function propertySortFromArray( array $array ) {
		if ( !isset( $array["property"] ) ) { return null;
		}
		$property = $array["property"];

		if ( !is_string( $property ) && !( $property instanceof PropertyFieldMapper ) ) {
			return null;
		}

		$order = isset( $array["order"] ) ? $array["order"] : null;

		return new PropertySort( $property, $order );
	}
}
