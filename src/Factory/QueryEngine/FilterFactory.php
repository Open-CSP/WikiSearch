<?php

namespace WikiSearch\Factory\QueryEngine;

use WikiSearch\Exception\ParsingException;
use WikiSearch\QueryEngine\Filter\ChainedPropertyFilter;
use WikiSearch\QueryEngine\Filter\Filter;
use WikiSearch\QueryEngine\Filter\HasPropertyFilter;
use WikiSearch\QueryEngine\Filter\PropertyFilter;
use WikiSearch\QueryEngine\Filter\PropertyFuzzyValueFilter;
use WikiSearch\QueryEngine\Filter\PropertyRangeFilter;
use WikiSearch\QueryEngine\Filter\PropertyTextFilter;
use WikiSearch\QueryEngine\Filter\PropertyValueFilter;
use WikiSearch\QueryEngine\Filter\PropertyValuesFilter;
use WikiSearch\SearchEngineConfig;
use WikiSearch\SMW\PropertyFieldMapper;
use WikiSearch\WikiSearchServices;

class FilterFactory {
    /**
     * Constructs a new filter object from the given spec.
     *
     * @param array $spec
     * @return Filter
     *
     * @throws ParsingException
     */
    public function newFilter( array $spec ): Filter {
        $path = [];

        if ( isset( $spec['value'] ) ) {
            $filter = $this->parseSpecForValue( $spec, $path );
        } else if ( isset( $spec['range'] ) ) {
            $filter = $this->parseSpecForRange( $spec, $path );
        } else {
            throw new ParsingException( 'either a value or a range is required', $path );
        }

        if ( !empty( $spec["negate"] ) ) {
            $filter->setNegated();
        }

        return $filter;
    }

    /**
     * Parses the given spec as a range property filter.
     *
     * @param array $spec The spec to parse.
     * @param array $path The current path.
     *
     * @return PropertyRangeFilter
     *
     * @throws ParsingException
     */
    private function parseSpecForRange( array $spec, array $path ): PropertyRangeFilter {
        $key = $this->parseKeyForRange( $spec, $path );
        $range = $this->parseRangeForRange( $spec, $path );

        return new PropertyRangeFilter( $key, $range['from'], $range['to'] );
    }

    /**
     * Parses the "key" value of the given spec for a range.
     *
     * @param array $spec The spec to parse.
     * @param array $path The current path.
     *
     * @return string The "key" of the spec.
     *
     * @throws ParsingException
     */
    private function parseKeyForRange( array $spec, array $path ): string {
        $path[] = 'key';

        if ( empty( $spec['key'] ) ) {
            throw new ParsingException( 'a key is required for a range filter', $path );
        }

        if ( !is_string( $spec['key'] ) ) {
            throw new ParsingException( 'a key must be a string', $path );
        }

        return $spec['key'];
    }

    /**
     * Parses the "range" value of the given spec for a range.
     *
     * @param array $spec The spec to parse.
     * @param array $path The current path.
     *
     * @return array{from: int, to: int} The "range" of the spec.
     *
     * @throws ParsingException
     */
    private function parseRangeForRange( array $spec, array $path ): array {
        $path[] = 'range';

        if ( empty( $spec['range'] ) ) {
            throw new ParsingException( 'a range is required for a range filter', $path );
        }

        if ( !is_array( $spec['range'] ) ) {
            throw new ParsingException( 'a range must be an array', $path );
        }

        $lower = $this->parseLowerRangeForRange( $spec['range'], $path );
        $upper = $this->parseUpperRangeForRange( $spec['range'], $path );

        return [
            'from' => $lower,
            'to' => $upper,
        ];
    }

    /**
     * Parses the "from" value of the given range.
     *
     * @param array $range The range to parse.
     * @param array $path The current path.
     *
     * @return int The "from" of the range.
     *
     * @throws ParsingException
     */
    private function parseLowerRangeForRange( array $range, array $path ): int {
        $path[] = 'from';

        if ( empty( $range['from'] ) ) {
            throw new ParsingException( 'a lower bound (from) is required for a range', $path );
        }

        if ( !is_int( $range['from'] ) ) {
            throw new ParsingException( 'a lower bound (from) must be an integer', $path );
        }

        return $range['from'];
    }

    /**
     * Parses the "to" value of the given range.
     *
     * @param array $range The range to parse.
     * @param array $path The current path.
     *
     * @return int The "to" of the range.
     *
     * @throws ParsingException
     */
    private function parseUpperRangeForRange( array $range, array $path ): int {
        $path[] = 'to';

        if ( empty( $range['to'] ) ) {
            throw new ParsingException( 'an upper bound (to) is required for a range', $path );
        }

        if ( !is_int( $range['to'] ) ) {
            throw new ParsingException( 'an upper bound (to) must be an integer', $path );
        }

        return $range['to'];
    }

    /**
     * Parses the given spec as a value property filter.
     *
     * @param array $spec The spec to parse.
     * @param array $path The current path.
     *
     * @return Filter
     *
     * @throws ParsingException
     */
    private function parseSpecForValue( array $spec, array $path ): Filter {
        return match ( $this->parseTypeForValue( $spec, $path ) ) {
            null => $this->parseSpecForValueWithoutType( $spec, $path ),
            "query" => $this->parseSpecForQueryValue( $spec, $path ),
            "fuzzy" => $this->parseSpecForFuzzyValue( $spec, $path )
        };
    }

    /**
     * Parses the "type" value of the given spec for a value property filter.
     *
     * @param array $spec The spec to parse.
     * @param array $path The current path.
     *
     * @return string|null The "type" of the value property filter, or NULL if it has no type.
     *
     * @throws ParsingException
     */
    private function parseTypeForValue( array $spec, array $path ): ?string {
        $path[] = 'type';

        if ( empty( $spec['type'] ) ) {
            return null;
        }

        if ( !in_array( $spec['type'], [ 'query', 'fuzzy' ] ) ) {
            throw new ParsingException( 'invalid type, must be either "query" or "fuzzy"', $path );
        }

        return $spec['type'];
    }

    private function parseSpecForValueWithoutType( array $spec, array $path ): PropertyValuesFilter|PropertyValueFilter {

    }

    private function parseSpecForQueryValue( array $spec, array $path ): PropertyTextFilter {

    }

    private function parseSpecForFuzzyValue( array $spec, array $path ): PropertyFuzzyValueFilter {

    }

	/**
	 * Constructs a new Filter class from the given array. The given array directly corresponds to the array given by
	 * the user through the API. Returns "null" on failure.
	 *
	 * @param array $array
	 * @param SearchEngineConfig $config
	 * @return Filter|null
	 */
	public static function fromArray( array $array, SearchEngineConfig $config ): ?Filter {
		WikiSearchServices::getLogger()->getLogger()->debug( 'Constructing Filter from array' );

		if ( !isset( $array["key"] ) ) {
			WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Filter from array: missing "key"' );

			return null;
		}

		if ( !is_string( $array["key"] ) && !( $array["key"] instanceof PropertyFieldMapper ) ) {
			WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Filter from array: invalid "key"' );

			return null;
		}

		$property_field_mapper = $array["key"] instanceof PropertyFieldMapper ?
			$array["key"] :
			new PropertyFieldMapper( $array["key"] );

		$filter = self::filterFromArray( $array, $property_field_mapper, $config );

		if ( $filter === null ) {
			return null;
		}

		$post_filter_properties = $config->getSearchParameter( "post filter properties" );
		if ( $post_filter_properties && in_array( $array["key"], $post_filter_properties, true ) ) {
			$filter->setPostFilter();
		}

		if ( isset( $array["negate"] ) && $array["negate"] === true ) {
			$filter->setNegated();
		}

		if ( $property_field_mapper->isChained() ) {
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
	 * @return Filter|null
	 */
	private static function filterFromArray(
		array $array,
		PropertyFieldMapper $property_field_mapper,
		SearchEngineConfig $config
	): ?PropertyFilter {
		if ( isset( $array["range"]["from"] ) && isset( $array["range"]["to"] ) ) {
			return self::rangeFilterFromRange( $array["range"]["from"], $array["range"]["to"], $property_field_mapper );
		}

		if ( isset( $array["type"] ) ) {
			if ( !is_string( $array["type"] ) ) {
				WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Filter from array: invalid "type"' );

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
					WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Filter from array: invalid "value"' );

					return null;
				}
			}

			return self::propertyValuesFilterFromValues( $value, $property_field_mapper );
		}

		if ( !in_array( gettype( $value ), [ "boolean", "string", "integer", "double", "float" ] ) ) {
			WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Filter from array: invalid "value"' );

			return null;
		}

		return self::propertyValueFilterFromValue( $value, $property_field_mapper );
	}

	/**
	 * @param string $type
	 * @param array $array
	 * @param PropertyFieldMapper $property_field_mapper
	 * @param SearchEngineConfig $config
	 * @return PropertyTextFilter|PropertyFuzzyValueFilter|null
	 */
	private static function typeFilterFromArray(
		string $type,
		array $array,
		PropertyFieldMapper $property_field_mapper,
		SearchEngineConfig $config
	): ?PropertyFilter {
		switch ( $type ) {
			case "query":
				if ( !isset( $array["value"] ) || !is_string( $array["value"] ) ) {
					WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Filter from array: missing/invalid "value"' );

					return null;
				}

				$default_operator = $config->getSearchParameter( "default operator" ) === "and" ?
					"and" : "or";
				return self::propertyTextFilterFromText( $array["value"], $default_operator, $property_field_mapper );
			case "fuzzy":
				if ( !isset( $array["value"] ) || !is_string( $array["value"] ) ) {
					WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Filter from array: missing/invalid "value"' );

					return null;
				}

				$fuzziness = $array["fuzziness"] ?? "AUTO";

				if ( $fuzziness !== "AUTO" && ( !is_int( $fuzziness ) || $fuzziness < 0 ) ) {
					WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Filter from array: invalid "fuzziness"' );

					return null;
				}

				return self::propertyFuzzyValueFilterFromText( $array["value"], $fuzziness, $property_field_mapper );
			default:
				WikiSearchServices::getLogger()->getLogger()->debug( 'Failed to construct Filter from array: invalid "type"' );

				return null;
		}
	}

	/**
	 * Constructs a new range filter from the given range.
	 *
	 * @param int $from
	 * @param int $to
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return PropertyRangeFilter
	 */
	private static function rangeFilterFromRange(
		int $from,
		int $to,
		PropertyFieldMapper $property_field_mapper
	): PropertyRangeFilter {
		return new PropertyRangeFilter( $property_field_mapper, from: $from, to: $to );
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
		return new PropertyTextFilter( $property_field_mapper, self::prepareQuery( $text ), $default_operator );
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

	/**
	 * @param string $value
	 * @param string|int $fuzziness
	 * @param PropertyFieldMapper $property_field_mapper
	 * @return PropertyFuzzyValueFilter
	 */
	private static function propertyFuzzyValueFilterFromText(
		string $value,
		$fuzziness,
		PropertyFieldMapper $property_field_mapper
	): PropertyFuzzyValueFilter {
		return new PropertyFuzzyValueFilter( $property_field_mapper, $value, $fuzziness );
	}
}
