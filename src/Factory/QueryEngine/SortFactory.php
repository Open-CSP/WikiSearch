<?php

namespace WikiSearch\Factory\QueryEngine;

use WikiSearch\Exception\ParsingException;
use WikiSearch\QueryEngine\Sort\PropertySort;
use WikiSearch\QueryEngine\Sort\Sort;

class SortFactory {
    /**
     * Constructs a new sort object from the given spec.
     *
     * @param array $spec
     * @return Sort
     * @throws ParsingException
     */
	public function newSort( array $spec ): Sort {
		$path = [];

		return match ( $this->parseType( $spec, $path ) ) {
			"property" => $this->parseSpecForProperty( $spec, $path )
		};
	}

    /**
     * Parses the given spec as a property sort.
     *
     * @param array $spec The spec to parse.
     * @param array $path The current path.
     *
     * @return PropertySort
     *
     * @throws ParsingException
     */
    private function parseSpecForProperty( array $spec, array $path ): PropertySort {
        $property = $this->parsePropertyForProperty( $spec, $path );
        $order = $this->parseOrderForProperty( $spec, $path );

        return new PropertySort( $property, $order );
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

        if ( !in_array( $spec['type'], [ 'property' ], true ) ) {
            throw new ParsingException( 'invalid type, must be "property"', $path );
        }

        return $spec['type'];
    }

    /**
     * Parses the "property" value of the given spec for a property.
     *
     * @param array $spec The spec to parse.
     * @param array $path The current path.
     *
     * @return string The "property" of the spec.
     *
     * @throws ParsingException
     */
    private function parsePropertyForProperty( array $spec, array $path ): string {
        $path[] = 'property';

        if ( empty( $spec['property'] ) ) {
            throw new ParsingException( 'a property is required for type "property"', $path );
        }

        if ( !is_string( $spec['property'] ) ) {
            throw new ParsingException( 'a property must be a string', $path );
        }

        return $spec['property'];
    }

    /**
     * Parses the "order" value of the given spec for a property.
     *
     * @param array $spec The spec to parse.
     * @param array $path The current path.
     *
     * @return string|null The "order" of the spec, or NULL if no order is specified.
     *
     * @throws ParsingException
     */
    private function parseOrderForProperty( array $spec, array $path ): ?string {
        $path[] = 'order';

        if ( empty( $spec['order'] ) ) {
            return null;
        }

        if ( !in_array( $spec['order'], [ 'asc', 'ascending', 'up', 'desc', 'dsc', 'descending', 'down' ], true ) ) {
            throw new ParsingException( 'invalid type, must be either "asc", "ascending", "up", "desc", "dsc", "descending" or "down"', $path );
        }

        return $spec['order'];
    }
}
