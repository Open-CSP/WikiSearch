<?php

/**
 * WikiSearch MediaWiki extension
 * Copyright (C) 2021  Wikibase Solutions
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

namespace WikiSearch;

use Title;
use Wikimedia\Rdbms\DBConnRef;
use WikiSearch\QueryEngine\Sort\PropertySort;
use WikiSearch\SMW\PropertyFieldMapper;
use WikiSearch\SMW\SMWQueryProcessor;

/**
 * Class SearchEngineConfig
 *
 * @package WikiSearch
 */
class SearchEngineConfig {
	// phpcs:ignore
	const SEARCH_PARAMETER_KEYS = [
		"base query" 			 =>	[ "type" => "string" ],
		"highlighted properties" => [ "type" => "propertylist" ],
		"search term properties" => [ "type" => "propertylist" ],
		"default operator" 		 => [ "type" => "string" ],
		"aggregation size" 		 =>	[ "type" => "integer" ],
		"post filter properties" => [ "type" => "list" ],
		"highlighter type"       => [ "type" => "string" ],
		"result template"		 => [ "type" => "string" ],
		"fallback sorts"         => [ "type" => "sortlist" ],
	];

	/**
	 * @var Title
	 */
	private Title $title;

	/**
	 * @var array
	 */
	private array $search_parameters;

	/**
	 * @var PropertyFieldMapper[]
	 */
	private array $facet_properties = [];

	/**
	 * @var PropertyFieldMapper[]
	 */
	private array $result_properties;

	/**
	 * @var array
	 */
	private array $translations = [];

	/**
	 * @var array
	 */
	private array $search_parameters_cache = [];

	/**
	 * Constructs a new SearchEngineConfig object from the values in the database identified by $page. If no
	 * SearchEngineConfig object exists in the database for the given $page, NULL will be returned.
	 *
	 * @param Title $page
	 * @return SearchEngineConfig|null
	 */
	public static function newFromDatabase( Title $page ) {
		$database = wfGetDB( DB_PRIMARY );
		$page_id = $page->getArticleID();

		$db_facets = $database->select(
			"search_facets",
			[ "property" ],
			[ "page_id" => $page_id ]
		);

		$facet_properties = [];
		foreach ( $db_facets as $property ) {
			$facet_properties[] = $property->property;
		}

		$db_result_properties = $database->select(
			"search_properties",
			[ "property" ],
			[ "page_id" => $page_id ]
		);

		$result_properties = [];
		foreach ( $db_result_properties as $property ) {
			$result_properties[] = $property->property;
		}

		$db_search_parameters = $database->select(
			"search_parameters",
			[ "parameter_key", "parameter_value" ],
			[ "page_id" => $page_id ]
		);

		$search_parameters = [];
		foreach ( $db_search_parameters as $search_parameter ) {
			$key = $search_parameter->parameter_key;
			$value = $search_parameter->parameter_value;

			if ( $value === "" ) {
				$value = true;
			}

			$search_parameters[$key] = $value;
		}

		try {
			return new SearchEngineConfig( $page, $search_parameters, $facet_properties, $result_properties );
		} catch ( \InvalidArgumentException $e ) {
			Logger::getLogger()->alert( 'Exception caught while trying to construct a new SearchEngineConfig: {e}', [
				'e' => $e
			] );

			return null;
		}
	}

	/**
	 * Returns a new SearchEngineConfig from the given parser function parameters, or null on failure.
	 *
	 * @param Title $title
	 * @param array $parameters
	 * @return SearchEngineConfig
	 */
	public static function newFromParameters( Title $title, array $parameters ): SearchEngineConfig {
		$facet_properties = $result_properties = $search_parameters = [];

		foreach ( $parameters as $parameter ) {
			if ( strlen( $parameter ) === 0 ) {
				continue;
			}

			if ( $parameter[0] === "?" ) {
				// This is a "result property"
				$result_properties[] = ltrim( $parameter, "?" );
				continue;
			}

			$key_value_pair = explode( "=", $parameter );
			$key = trim( $key_value_pair[0] );

			if ( !array_key_exists( $key, self::SEARCH_PARAMETER_KEYS ) ) {
				// This is a "facet property", since its key is not a valid search parameter
				$facet_properties[] = $parameter;
			} else {
				// This is a "search term parameter"
				$search_parameters[$key] = isset( $key_value_pair[1] ) ? $key_value_pair[1] : true;
			}
		}

		$facet_properties = array_unique( $facet_properties );
		$result_properties = array_unique( $result_properties );

		return new SearchEngineConfig( $title, $search_parameters, $facet_properties, $result_properties );
	}

	/**
	 * SearchEngineConfig constructor.
	 *
	 * @param Title $title The page for which this config is applicable
	 * @param array $search_parameters
	 * @param array $facet_properties
	 * @param array $result_properties
	 */
	public function __construct(
		Title $title,
		array $search_parameters,
		array $facet_properties,
		array $result_properties
	) {
		$this->title = $title;
		$this->search_parameters = $search_parameters;

		$result_properties = array_filter( $result_properties, static function ( string $property_name ) {
			return !empty( $property_name );
		} );

		$this->result_properties = array_map( static function ( string $property_name ): PropertyFieldMapper {
			return new PropertyFieldMapper( $property_name );
		}, $result_properties );

		$this->facet_properties = [];
		foreach ( $facet_properties as $property ) {
			$translation_pair = explode( "=", $property );
			$property_name = $translation_pair[0];

			$this->facet_properties[] = new PropertyFieldMapper( $property_name );

			if ( isset( $translation_pair[1] ) ) {
				$this->translations[$property_name] = $translation_pair[1];
			}
		}

		if ( isset( $search_parameters["base query"] ) ) {
			try {
				$query_processor = new SMWQueryProcessor( $search_parameters["base query"] );
				$query_processor->toElasticSearchQuery();
			} catch ( \MWException $exception ) {
				Logger::getLogger()->alert( 'Exception caught while trying to parse a base query: {e}', [
					'e' => $exception
				] );

				// The query is invalid
				throw new \InvalidArgumentException( "Invalid base query" );
			}
		}
	}

	/**
	 * Returns the page for which this config is applicable as a Title object.
	 *
	 * @return Title
	 */
	public function getTitle(): Title {
		return $this->title;
	}

	/**
	 * Returns the value of the given search term parameter, or NULL when the parameter does not exist.
	 *
	 * @param string $parameter
	 * @return string|int|array|null
	 */
	public function getSearchParameter( string $parameter ): int|array|string|null {
		if ( !isset( $this->search_parameters[$parameter] ) ) {
			return null;
		}

		$search_parameter_value_raw = $this->search_parameters[$parameter];

		if ( empty( $search_parameter_value_raw ) ) {
			return null;
		}

		$search_parameter_type = self::SEARCH_PARAMETER_KEYS[$parameter]["type"] ?? "string";

		if ( $search_parameter_value_raw === true && $search_parameter_type !== "flag" ) {
			// Only a flag is valid without a value
			return null;
		}

		switch ( $search_parameter_type ) {
			case "integer":
				return intval( trim( $search_parameter_value_raw ) );
			case "list":
				$search_parameter_value = array_map( "trim", explode( ",", $search_parameter_value_raw ) );
				return array_filter( $search_parameter_value, fn ( string $value ): bool => !empty( $value ) );
			case "propertylist":
				$search_parameter_value = array_map( "trim", explode( ",", $search_parameter_value_raw ) );
				$search_parameter_value = array_filter( $search_parameter_value, fn ( string $value ): bool => !empty( $value ) );
				return array_map( static function ( $property ): PropertyFieldMapper {
					// Map the property name to its field
					return ( new PropertyFieldMapper( $property ) );
				}, $search_parameter_value );
			case "sortlist":
				$search_parameter_value = array_map( "trim", explode( ",", $search_parameter_value_raw ) );
				$search_parameter_value = array_filter( $search_parameter_value, fn ( string $value ): bool => !empty( $value ) );
				return array_map( static function ( $sort ): PropertySort {
					$sortParts = explode( ".", $sort );

					if ( count( $sortParts ) === 1 ) {
						$order = null;
					} else {
						$maybeOrder = array_pop( $sortParts );

						switch ( $maybeOrder ) {
							case 'asc':
							case 'ascending':
							case 'up':
								$order = 'asc';
								break;
							case 'desc':
							case 'descending':
							case 'down':
								$order = 'desc';
								break;
							default:
								$order = null;
								// Re-add the string to the parts if it is invalid, so we restore the original
								// property name (e.g. Foo.Bar).
								$sortParts[] = $maybeOrder;
								break;
						}
					}

					return new PropertySort( implode( ".", $sortParts ), $order );
				}, $search_parameter_value );
			default:
				// Interpret as string
				return trim( $search_parameter_value_raw );
		}
	}

	/**
	 * Returns the array of property translations.
	 *
	 * @return string[]
	 */
	public function getPropertyTranslations(): array {
		return $this->translations;
	}

	/**
	 * Returns the facet properties (properties that are not prefixed with "?"). This may be
	 * the name of the facet property (e.g. "Foobar") or a translation pair (e.g. "Foobar=Boofar").
	 *
	 * @return PropertyFieldMapper[]
	 */
	public function getFacetProperties(): array {
		return $this->facet_properties;
	}

	/**
	 * Returns the result properties to show. The first property in this array
	 * is the property from which the value will be used as the page link. Result properties
	 * are the properties prefixed with a "?".
	 *
	 * @return PropertyFieldMapper[]
	 */
	public function getResultProperties(): array {
		return $this->result_properties;
	}

	/**
	 * Updates/adds this SearchEngineConfig object in the database with the current values.
	 *
	 * @param DBConnRef $database
	 */
	public function update( DBConnRef $database ): void {
		$id = $this->title->getArticleID();

		Logger::getLogger()->debug( 'Updating search engine configuration for page ID {id}', [
			'id' => $id
		] );

		$this->delete( $database, $id );
		$this->insert( $database );

		Logger::getLogger()->debug( 'Finished updating search engine configuration for page ID {id}', [
			'id' => $id
		] );
	}

	/**
	 * Adds this SearchEngineConfig object to the database with the current values. This function does not take
	 * into account whether the current object might already have been saved and may throw an error if the object
	 * is saved twice. Use $this->update() instead.
	 *
	 * @param DBConnRef $database
	 */
	public function insert( DBConnRef $database ): void {
		$page_id = $this->title->getArticleID();

		$facet_properties = array_unique( array_map( static function ( PropertyFieldMapper $property ): string {
			// Use 'getPropertyName' here to make sure that the value in the database corresponds directly to the
			// value present in the parser function call (otherwise it might be translated to something else, causing
			// several problems in the front-end)
			return $property->getPropertyName();
		}, $this->facet_properties ) );

		$result_properties = array_unique( array_map( static function ( PropertyFieldMapper $property ): string {
			return $property->getPropertyName();
		}, $this->result_properties ) );

		// Insert this object's facet properties
		foreach ( $facet_properties as $property ) {
			$database->insert(
				"search_facets",
				[
					"page_id" => $page_id,
					"property" => $property
				]
			);
		}

		// Insert this object's result properties
		foreach ( $result_properties as $property ) {
			$database->insert(
				"search_properties",
				[
					"page_id" => $page_id,
					"property" => $property
				]
			);
		}

		// Insert this object's search parameters
		foreach ( $this->search_parameters as $key => $value ) {
			$database->insert(
				"search_parameters",
				[
					"parameter_key" => $key,
					"parameter_value" => $value === true ? "" : $value,
					"page_id" => $page_id
				]
			);
		}
	}

	/**
	 * Deletes the SearchEngineConfig object associated with the given $page_id from the database.
	 *
	 * @param DBConnRef $database
	 * @param int $page_id
	 */
	public static function delete( DBConnRef $database, int $page_id ): void {
		$database->delete(
			"search_facets",
			[ "page_id" => $page_id ]
		);

		$database->delete(
			"search_properties",
			[ "page_id" => $page_id ]
		);

		$database->delete(
			"search_parameters",
			[ "page_id" => $page_id ]
		);
	}
}
