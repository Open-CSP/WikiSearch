<?php

/**
 * WSSearch MediaWiki extension
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

namespace WSSearch;

use Database;
use Title;
use WSSearch\SMW\Property;

/**
 * Class SearchEngineConfig
 *
 * @package WSSearch
 */
class SearchEngineConfig {
    const SEARCH_PARAMETER_KEYS = [ "base query" ];

	/**
	 * @var Title
	 */
	private $title;

    /**
     * @var array
     */
    private $search_parameters;

	/**
	 * @var array
	 */
	private $facet_properties;

	/**
	 * @var array
	 */
	private $result_properties;

    /**
     * @var array
     */
    private $facet_property_ids = [];

    /**
     * @var array
     */
    private $result_property_ids = [];

    /**
     * @var array
     */
    private $translations = [];

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
			return null;
		}
	}

    /**
     * Returns a new SearchEngineConfig from the given parser function parameters, or null on failure.
     *
     * @param Title $title
     * @param array $parameters
     * @return SearchEngineConfig|null
     */
    public static function newFromParameters( Title $title, array $parameters ) {
        $facet_properties = $result_properties = $search_parameters = [];

        foreach ( $parameters as $parameter ) {
            if ( strlen( $parameter ) === 0 ) continue;

            if ( $parameter[0] === "?" ) {
                // This is a "result property"
                $result_properties[] = ltrim( $parameter, "?" );
                continue;
            }

            $key_value_pair = explode( "=", $parameter );
            $key = $key_value_pair[0];

            if ( !in_array( $key_value_pair[0], self::SEARCH_PARAMETER_KEYS ) ) {
                // This is a "facet property", since its key is not a valid search parameter
                $facet_properties[] = $parameter;
                continue;
            }

            $value = isset( $key_value_pair[1] ) ? $key_value_pair[1] : true;
            $search_parameters[$key] = $value;
        }

        try {
            return new SearchEngineConfig( $title, $search_parameters, $facet_properties, $result_properties );
        } catch ( \InvalidArgumentException $exception ) {
            return null;
        }
    }

    /**
     * SearchEngineConfig constructor.
     *
     * @param Title $title The page for which this config is applicable
     * @param array $search_parameters
     * @param array $facet_properties
     * @param array $result_properties
     */
	public function __construct( Title $title, array $search_parameters, array $facet_properties, array $result_properties ) {
		if ( empty( $facet_properties ) ) {
			throw new \InvalidArgumentException( "Invalid facet properties array; at least one facet property is required." );
		}

		if ( empty( $result_properties ) ) {
			throw new \InvalidArgumentException( "Invalid result properties array; at least one result property is required." );
		}

		$this->title = $title;
		$this->facet_properties = $facet_properties;
		$this->search_parameters = $search_parameters;

		$this->result_properties = array_map( function( string $property_name ): Property {
            return new Property( $property_name );
        }, $result_properties );

		foreach ( $facet_properties as $property ) {
            $translation_pair = explode( "=", $property );
            $property_name = $translation_pair[0];

            if ( isset( $translation_pair[1] ) ) {
                $this->translations[$property_name] = $translation_pair[1];
            }

            $facet_property = new Property( $property_name );
            $this->facet_property_ids[$property_name] = $facet_property->getPropertyID();
        }

        foreach ( $this->result_properties as $property ) {
            $this->result_property_ids[$property->getPropertyName()] = $property->getPropertyID();
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
     * Returns all search parameters given to the parser function.
     *
     * @return array
     */
	public function getSearchParameters(): array {
	    return $this->search_parameters;
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
     * Returns the IDs for the facet properties.
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
	 * @return \WSSearch\SMW\Property[]
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
	 * @param Database $database
	 */
	public function update( $database ) {
		$this->delete( $database, $this->title->getArticleID() );
		$this->insert( $database );
	}

	/**
	 * Adds this SearchEngineConfig object to the database with the current values. This function does not take
	 * into account whether the current object might already have been saved and may throw an error if the object
	 * is saved twice. Use $this->update() instead.
	 *
	 * @param Database $database
	 */
	public function insert( $database ) {
		$page_id = $this->title->getArticleID();

		$facet_properties = array_unique( $this->facet_properties );
		$result_properties = array_map( function(Property $property ): string {
		    return $property->getPropertyName();
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
	 * @param Database $database
	 * @param int $page_id
	 */
	public static function delete( $database, int $page_id ) {
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
