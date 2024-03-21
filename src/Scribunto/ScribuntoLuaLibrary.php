<?php

/**
 * WikiSearch MediaWiki extension
 * Copyright (C) 2022  Wikibase Solutions
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace WikiSearch\Scribunto;

use Elastic\Elasticsearch\ClientBuilder;
use MWException;
use WikiSearch\QueryEngine\Aggregation\FilterAggregation;
use WikiSearch\QueryEngine\Aggregation\PropertyValueAggregation;
use WikiSearch\Factory\QueryEngineFactory;
use WikiSearch\QueryEngine\Filter\PropertyRangeFilter;
use WikiSearch\WikiSearchServices;

/**
 * Register the Lua library.
 */
class ScribuntoLuaLibrary extends \Scribunto_LuaLibraryBase {
	/**
	 * @inheritDoc
	 */
	public function register(): void {
		$interfaceFuncs = [
			'propValues' => [ $this, 'propValues' ]
		];

		$this->getEngine()->registerInterface( __DIR__ . '/' . 'mw.wikisearch.lua', $interfaceFuncs, [] );
	}

	/**
	 * This mirrors the functionality of the #prop_values parser function and makes it available in Lua.
	 *
	 * @param array $properties
	 * @return array
	 * @throws MWException
	 */
	public function propValues( array $properties ): array {
		$limit = $properties["limit"] ?? 100;
		$property = $properties["property"] ?? "";
		$dateProperty = $properties["date property"] ?? "Modification date";
		$from = $properties["from"] ?? 1;
		$to = $properties["to"] ?? 5000;
		$baseQuery = $properties["query"] ?? null;

		if ( !$property || !is_int( $limit ) || !is_int( $from ) || !is_int( $to ) ) {
			return [ null ];
		}

		if (
			$from < 0 || $from > 9999 || // From range check
			$to < 0 || $to > 9999 || // To range check
			$to <= $from || // To-from order check
			$limit < 1 || $limit > 9999 // Limit range check
		) {
			return [ null ];
		}

		list( $from, $to ) = $this->convertDates( $from, $to );

		$rangeFilter = new PropertyRangeFilter( $dateProperty, from: $from, to: $to );
		$termsAggregation = new PropertyValueAggregation( $property, "common_values", $limit );
		$aggregation = new FilterAggregation( $rangeFilter, [ $termsAggregation ], "property_values" );

		$queryEngine = WikiSearchServices::getQueryEngineFactory()->newQueryEngine();
		$queryEngine->addAggregation( $aggregation );

		if ( isset( $baseQuery ) ) {
			$queryEngine->setBaseQuery( $baseQuery );
		}

		$results = WikiSearchServices::getElasticsearchClientFactory()
            ->newElasticsearchClient()
			->search( $queryEngine->toQuery() );

		if ( !isset( $results["aggregations"]["property_values"]["property_values"]["common_values"]["buckets"] ) ) {
			// Failed to create aggregations
			return [ null ];
		}

		$buckets = $results["aggregations"]["property_values"]["property_values"]["common_values"]["buckets"];

		if ( !is_array( $buckets ) ) {
			// The aggregations are not valid
			return [ null ];
		}

		return [ $this->convertToLuaTable( $buckets ) ];
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

	/**
	 * @param $array
	 * @return mixed
	 */
	private function convertToLuaTable( $array ) {
		if ( is_array( $array ) ) {
			foreach ( $array as $key => $value ) {
				$array[$key] = $this->convertToLuaTable( $value );
			}

			array_unshift( $array, '' );
			unset( $array[0] );
		}

		return $array;
	}
}
