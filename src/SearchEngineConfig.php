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
		"highlighted properties" => [ "type" => "propertyfieldlist" ],
		"search term properties" => [ "type" => "propertylist" ],
		"default operator" 		 => [ "type" => "string" ],
		"aggregation size" 		 =>	[ "type" => "integer" ],
		"post filter properties" => [ "type" => "list" ]
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
	 * @var array
	 */
	private array $facet_properties;

	/**
	 * @var array
	 */
	private array $result_properties;

	/**
	 * @var array
	 */
	private array $facet_property_ids = [];

	/**
	 * @var array
	 */
	private array $result_property_ids = [];

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
		$database = wfGetDB( DB_MASTER );
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
			if ( strlen( $parameter ) === 0 ) { continue;
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
		$this->facet_properties = $facet_properties;
		$this->search_parameters = $search_parameters;

		$result_properties = array_filter( $result_properties, function ( string $property_name ) {
			return !empty( $property_name );
		} );

		$this->result_properties = array_map( function ( string $property_name ): PropertyFieldMapper {
			return new PropertyFieldMapper( $property_name );
		}, $result_properties );

		foreach ( $facet_properties as $property ) {
			$translation_pair = explode( "=", $property );
			$property_name = $translation_pair[0];

			if ( isset( $translation_pair[1] ) ) {
				$this->translations[$property_name] = $translation_pair[1];
			}

			$facet_property = new PropertyFieldMapper( $property_name );
			$this->facet_property_ids[$property_name] = $facet_property->getPropertyID();
		}

		foreach ( $this->result_properties as $property ) {
			$this->result_property_ids[$property->getPropertyName()] = $property->getPropertyID();
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
	 * Returns the value of the given search term parameter, or false when the parameter
	 * does not exist.
	 *
	 * @param string $parameter
	 * @return false|string|int|array
	 */
	public function getSearchParameter( string $parameter ) {
		if ( isset( $this->search_parameters_cache[$parameter] ) ) {
			return $this->search_parameters_cache[$parameter];
		}

		if ( !isset( $this->search_parameters[$parameter] ) ) {
			return false;
		}

		$search_parameter_value_raw = $this->search_parameters[$parameter];
		$search_parameter_type = self::SEARCH_PARAMETER_KEYS[$parameter]["type"] ?? "string";

		switch ( $search_parameter_type ) {
			case "integer":
                return $this->search_parameters_cache[$parameter] = intval( $search_parameter_value_raw );
			case "string":
                return $this->search_parameters_cache[$parameter] = trim( $search_parameter_value_raw );
			case "list":
                return $this->search_parameters_cache[$parameter] = array_map( "trim", explode( ",", $search_parameter_value_raw ) );
			case "propertyfieldlist":
                return $this->search_parameters_cache[$parameter] = array_map( function ( $property ): string {
					// Map the property name to its field
					return ( new PropertyFieldMapper( $property ) )->getPropertyField();
				}, $this->parsePropertyList( $search_parameter_value_raw ) );
			case "propertylist":
                return $this->search_parameters_cache[$parameter] = array_map( function ( $property ): PropertyFieldMapper {
					// Map the property name to its field
					return ( new PropertyFieldMapper( $property ) );
				}, $this->parsePropertyList( $search_parameter_value_raw ) );
			default:
                return $this->search_parameters_cache[$parameter] = $search_parameter_value_raw;
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
	 * @return string[]
	 */
	public function getFacetProperties(): array {
		return $this->facet_properties;
	}

	/**
	 * Returns key-value pairs of the property ID with the corresponding property type:
	 *
	 * [
	 *      745 => "txtField",
	 *      752 => "txtField"
	 * ]
	 *
	 * @return array
	 */
	public function getFacetPropertyIDs(): array {
		return $this->facet_property_ids;
	}

	/**
	 * Returns the result properties to show. The first property in this array
	 * is the property from which the value will be used as the page link. Result properties
	 * are the properties prefixed with a "?".
	 *
	 * @return \WikiSearch\SMW\PropertyFieldMapper[]
	 */
	public function getResultProperties(): array {
		return $this->result_properties;
	}

	/**
	 * Returns the IDs for the result properties.
	 *
	 * @return int[]
	 */
	public function getResultPropertyIDs(): array {
		return $this->result_property_ids;
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

		$facet_properties = array_unique( $this->facet_properties );
		$result_properties = array_map( function ( PropertyFieldMapper $property ): string {
			return $property->getPropertyKey();
		}, $this->result_properties );
		$result_properties = array_unique( $result_properties );

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

    /**
     * Parses the given list of property names.
     *
     * @param string $value
     * @return string[]
     */
    private function parsePropertyList( string $value ): array {
        return array_unique(
            array_filter(
                array_map(
                    "trim",
                    explode( ",", $value )
                ),
                fn ( string $v ): bool => !empty( $v )
            )
        );
    }
}
