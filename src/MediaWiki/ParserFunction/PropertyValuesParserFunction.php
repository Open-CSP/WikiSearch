<?php

namespace WikiSearch\MediaWiki\ParserFunction;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Parser;
use WikiSearch\QueryEngine\Aggregation\FilterAggregation;
use WikiSearch\QueryEngine\Aggregation\ValuePropertyAggregation;
use WikiSearch\QueryEngine\Filter\PropertyRangeFilter;
use WikiSearch\WikiSearchServices;

/**
 * @deprecated Use Lua function instead
 */
class PropertyValuesParserFunction {
	/**
	 * Callback for the parser function {{#prop_values}}.
	 *
	 * @deprecated Use the Lua function instead
	 *
	 * @param Parser $parser
	 * @param string ...$args
	 * @return string
	 */
	public function execute( Parser $parser, ...$args ): string {
		if ( !class_exists( "\WSArrays" ) ) {
			return "WSArrays must be installed.";
		}

		$options = $this->extractOptions( $args );

		$limit = $options["limit"] ?? "100";
		$property = $options["property"] ?? "";
		$arrayName = $options["array"] ?? "";
		$dateProperty = $options["date property"] ?? "Modification date";
		$from = $options["from"] ?? "1";
		$to = $options["to"] ?? "5000";
		$baseQuery = $options["query"] ?? null;

		if ( !$property || !$arrayName ) {
			return "Missing 'array' or 'property' parameter";
		}

		if ( !ctype_digit( $from ) || !ctype_digit( $to ) || !ctype_digit( $limit ) ) {
			return "Invalid 'from', 'limit' or 'to' parameter";
		}

		$from = intval( $from );
		$to = intval( $to );
		$limit = intval( $limit );

		if ( $from < 0 || $from > 9999 ) {
			return "The 'from' parameter must be a year between 0 and 10000";
		}

		if ( $to < 0 || $to > 9999 ) {
			return "The 'to' parameter must be a year between 0 and 10000";
		}

		if ( $to <= $from ) {
			return "The 'to' parameter must be greater than or equal to the 'from' parameter";
		}

		if ( $limit < 1 || $limit > 9999 ) {
			return "The 'limit' parameter must be an integer between 1 and 10000";
		}

		[ $from, $to ] = $this->convertDates( $from, $to );

		$rangeFilter = new PropertyRangeFilter( $dateProperty, from: $from, to: $to );
		$termsAggregation = new ValuePropertyAggregation( $property, $limit, "common_values" );
		$aggregation = new FilterAggregation( $rangeFilter, [ $termsAggregation ], "property_values" );

		$queryEngine = WikiSearchServices::getQueryEngineFactory()->newQueryEngine();
		$queryEngine->addAggregation( $aggregation );

		if ( isset( $baseQuery ) ) {
			$queryEngine->setBaseQuery( $baseQuery );
		}

		try {
			$result = WikiSearchServices::getElasticsearchClientFactory()
				->newElasticsearchClient()
				->search( $queryEngine->toQuery() );
		} catch ( ClientResponseException | ServerResponseException ) {
			$result = [];
		}

		if ( !is_array( $result ) ) {
			// Elasticsearch >= 8.x
			$result = $result->asArray();
		}

		$buckets = $result["aggregations"]["property_values"]["property_values"]["common_values"]["buckets"] ?? null;

		if ( !is_array( $buckets ) ) {
			// The aggregations are not valid
			return "";
		}

		if ( class_exists( "\ComplexArray" ) ) {
			\WSArrays::$arrays[$arrayName] = new \ComplexArray( $buckets );
		}

		return "";
	}

	/**
	 * Turns the given options of the format "a=b" into array values of the format "a" => "b".
	 *
	 * @param array $options
	 * @return array
	 */
	private function extractOptions( array $options ): array {
		$results = [];

		foreach ( $options as $option ) {
			$pair = array_map( 'trim', explode( '=', $option, 2 ) );

			if ( count( $pair ) === 2 ) {
				$results[ $pair[0] ] = $pair[1];
			}

			if ( count( $pair ) === 1 ) {
				$results[ $pair[0] ] = true;
			}
		}

		return $results;
	}

	/**
	 * Converts the given dates to Julian dates.
	 *
	 * @param int $from_year
	 * @param int $to_year
	 * @return array
	 */
	private function convertDates( int $from_year, int $to_year ): array {
		return [ gregoriantojd( 1, 1, $from_year ), gregoriantojd( 12, 31, $to_year ) ];
	}
}