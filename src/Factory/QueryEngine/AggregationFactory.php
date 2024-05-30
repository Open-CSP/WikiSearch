<?php

namespace WikiSearch\Factory\QueryEngine;

use WikiSearch\Exception\ParsingException;
use WikiSearch\QueryEngine\Aggregation\AbstractAggregation;
use WikiSearch\QueryEngine\Aggregation\RangePropertyAggregation;
use WikiSearch\QueryEngine\Aggregation\ValuePropertyAggregation;

class AggregationFactory {
	/**
	 * Constructs a new aggregation object from the given spec.
	 *
	 * @param array $spec
	 * @return AbstractAggregation
	 * @throws ParsingException
	 */
	public function newAggregation( array $spec ): AbstractAggregation {
		$path = [];

		return match ( $this->parseType( $spec, $path ) ) {
			"range" => $this->parseSpecForRange( $spec, $path ),
			"value" | "property" => $this->parseSpecForValue( $spec, $path )
		};
	}

	/**
	 * Parses the given spec as a range property aggregation.
	 *
	 * @param array $spec The spec to parse.
	 * @param array $path The current path.
	 *
	 * @return RangePropertyAggregation
	 *
	 * @throws ParsingException
	 */
	private function parseSpecForRange( array $spec, array $path ): RangePropertyAggregation {
		$name = $this->parseNameForRange( $spec, $path );
		$property = $this->parsePropertyForRange( $spec, $path );
		$ranges = $this->parseRanges( $spec, $path );

		return new RangePropertyAggregation( $property, $ranges, $name );
	}

	/**
	 * Parses the given spec as a value property aggregation.
	 *
	 * @param array $spec The spec to parse.
	 * @param array $path The current path.
	 *
	 * @return ValuePropertyAggregation
	 *
	 * @throws ParsingException
	 */
	private function parseSpecForValue( array $spec, array $path ): ValuePropertyAggregation {
		$name = $this->parseNameForValue( $spec, $path );
		$property = $this->parsePropertyForValue( $spec, $path );

		return new ValuePropertyAggregation( $property, null, $name );
	}

	/**
	 * Parses the "type" value of the given spec.
	 *
	 * @param array $spec The spec to parse.
	 * @param array $path The current path.
	 *
	 * @return string The "type" of the spec.
	 *
	 * @throws ParsingException
	 */
	private function parseType( array $spec, array $path ): string {
		$path[] = 'type';

		if ( !isset( $spec['type'] ) ) {
			throw new ParsingException( 'a type is required', $path );
		}

		if ( !in_array( $spec['type'], [ 'range', 'value', 'property' ], true ) ) {
			throw new ParsingException( 'invalid type, must be either "range", "value" or "property"', $path );
		}

		return $spec['type'];
	}

	/**
	 * Parses the "name" value of the given spec for a range.
	 *
	 * @param array $spec The spec to parse.
	 * @param array $path The current path.
	 *
	 * @return string The "name" of the spec.
	 *
	 * @throws ParsingException
	 */
	private function parseNameForRange( array $spec, array $path ): string {
		$path[] = 'name';

		if ( empty( $spec['name'] ) ) {
			throw new ParsingException( 'a name is required for type "range"', $path );
		}

		if ( !is_string( $spec['name'] ) ) {
			throw new ParsingException( 'a name must be a string', $path );
		}

		return $spec['name'];
	}

	/**
	 * Parses the "name" value of the given spec for a value.
	 *
	 * @param array $spec The spec to parse.
	 * @param array $path The current path.
	 *
	 * @return string The "name" of the spec.
	 *
	 * @throws ParsingException
	 */
	private function parseNameForValue( array $spec, array $path ): string {
		$path[] = 'name';

		if ( empty( $spec['name'] ) ) {
			throw new ParsingException( 'a name is required for type "value"/"property"', $path );
		}

		if ( !is_string( $spec['name'] ) ) {
			throw new ParsingException( 'a name must be a string', $path );
		}

		return $spec['name'];
	}

	/**
	 * Parses the "property" value of the given spec for a range.
	 *
	 * @param array $spec The spec to parse.
	 * @param array $path The current path.
	 *
	 * @return string The "property" of the spec.
	 *
	 * @throws ParsingException
	 */
	private function parsePropertyForRange( array $spec, array $path ): string {
		$path[] = 'property';

		if ( empty( $spec['property'] ) ) {
			throw new ParsingException( 'a property is required for type "range"', $path );
		}

		if ( !is_string( $spec['property'] ) ) {
			throw new ParsingException( 'a property must be a string', $path );
		}

		return $spec['property'];
	}

	/**
	 * Parses the "property" value of the given spec for a value.
	 *
	 * @param array $spec The spec to parse.
	 * @param array $path The current path.
	 *
	 * @return string The "property" of the spec.
	 *
	 * @throws ParsingException
	 */
	private function parsePropertyForValue( array $spec, array $path ): string {
		$path[] = 'property';

		if ( empty( $spec['property'] ) ) {
			throw new ParsingException( 'a property is required for type "value"/"property"', $path );
		}

		if ( !is_string( $spec['property'] ) ) {
			throw new ParsingException( 'a property must be a string', $path );
		}

		return $spec['property'];
	}

	/**
	 * Parses the "ranges" value of the given spec.
	 *
	 * @param array $spec The spec to parse.
	 * @param array $path The current path.
	 *
	 * @return array The "ranges" of the spec.
	 *
	 * @throws ParsingException
	 */
	private function parseRanges( array $spec, array $path ): array {
		$path[] = 'ranges';

		if ( empty( $spec['ranges'] ) ) {
			throw new ParsingException( 'ranges are required for type "range"', $path );
		}

		if ( !is_array( $spec['ranges'] ) ) {
			throw new ParsingException( 'the ranges must a list', $path );
		}

		return $spec['ranges'];
	}
}
