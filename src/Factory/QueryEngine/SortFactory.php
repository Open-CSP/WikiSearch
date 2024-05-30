<?php

namespace WikiSearch\Factory\QueryEngine;

use WikiSearch\Exception\ParsingException;
use WikiSearch\MediaWiki\Logger;
use WikiSearch\QueryEngine\Sort\PropertySort;
use WikiSearch\QueryEngine\Sort\Sort;
use WikiSearch\SMW\PropertyFieldMapper;

class SortFactory {
	/**
	 * Constructs a new sort object from the given spec.
	 *
	 * @param array $spec
	 * @return Sort
	 */
	public function newSort( array $spec ): Sort {
		$path = [];

		return match ( $this->parseType( $spec, $path ) ) {
			"property" => $this->parseSpecForProperty( $spec, $path )
		};
	}

	/**
	 * @throws ParsingException
	 */
	private function parseType( array $spec, array $path ): string {
		$path[] = 'type';

		if ( !isset( $spec['type'] ) ) {
			throw new AggregationParsingException( 'a type is required', $path );
		}

		if ( !in_array( $spec['type'], [ 'range', 'value', 'property' ], true ) ) {
			throw new AggregationParsingException( 'invalid type, must be either "range", "value" or "property"', $path );
		}

		return $spec['type'];
	}

	/**
	 * Constructs a new Sort class from the given array. The given array directly corresponds to the array given by
	 * the user through the API. Returns "null" on failure.
	 *
	 * @param array $array
	 * @return Sort|null
	 */
	public static function fromArray( array $array ): ?Sort {
		\WikiSearch\WikiSearchServices::getLogger()->getLogger()->debug( 'Constructing Sort from array' );

		if ( !isset( $array["type"] ) ) {
			\WikiSearch\WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Sort from array: missing "type"' );
			return null;
		}

		switch ( $array["type"] ) {
			case "property":
				return self::propertySortFromArray( $array );
			default:
				\WikiSearch\WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Sort from array: invalid "type"' );
				return null;
		}
	}

	/**
	 * Constructs a new PropertySort from the given array. Returns null on failure.
	 *
	 * @param array $array
	 * @return PropertySort|null
	 */
	private static function propertySortFromArray( array $array ): ?PropertySort {
		if ( !isset( $array["property"] ) ) {
			\WikiSearch\WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct PropertySort from array: missing "property"' );
			return null;
		}

		$property = $array["property"];

		if ( !is_string( $property ) && !( $property instanceof PropertyFieldMapper ) ) {
			\WikiSearch\WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct PropertySort from array: invalid "property"' );
			return null;
		}

		return new PropertySort( $property, $array["order"] ?? null );
	}
}
