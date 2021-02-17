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

/**
 * Class SearchEngineConfig
 *
 * @package WSSearch
 */
class SearchEngineConfig {
	/**
	 * @var Title
	 */
	private $title;

	/**
	 * @var PropertyInfo
	 */
	private $condition_property;

	/**
	 * @var string
	 */
	private $condition_value;

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
	 * Constructs a new SearchEngineConfig object from the values in the database identified by $page. If no
	 * SearchEngineConfig object exists in the database for the given $page, NULL will be returned.
	 *
	 * @param Title $page
	 * @return SearchEngineConfig|null
	 */
	public static function newFromDatabase( Title $page ) {
		$database = wfGetDB( DB_MASTER );
		$page_id = $page->getArticleID();

		$db_condition = $database->select(
			"search_condition",
			[ "condition_property", "condition_value" ],
			[ "page_id" => $page_id ]
		);

		if ( $db_condition->numRows() === 0 ) {
			return null;
		}

		$condition = $db_condition->current()->condition_property . "=" . $db_condition->current()->condition_value;

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

		try {
			return new SearchEngineConfig( $page, $condition, $facet_properties, $result_properties );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * SearchEngineConfig constructor.
	 *
	 * @param Title $title The page for which this config is applicable
	 * @param string $condition
	 * @param array $facet_properties
	 * @param array $result_properties
	 */
	public function __construct( Title $title, string $condition, array $facet_properties, array $result_properties ) {
		if ( empty( $facet_properties ) ) {
			throw new \InvalidArgumentException( "Invalid facet properties array; at least one facet property is required." );
		}

		if ( empty( $result_properties ) ) {
			throw new \InvalidArgumentException( "Invalid result properties array; at least one result property is required." );
		}

		if ( strpos( $condition, "=" ) === false ) {
			throw new \InvalidArgumentException( "Invalid condition; doesn't contain an equality symbol." );
		}

		list( $condition_property, $condition_value ) = explode( "=", $condition );

		if ( !$condition_property || !$condition_value ) {
			throw new \InvalidArgumentException( "Invalid condition; empty property or value" );
		}

		$this->title = $title;

		$this->condition_property = new PropertyInfo( $condition_property );
		$this->condition_value = $condition_value;

		$this->facet_properties = array_map( function( string $property_name ): PropertyInfo {
		    return new PropertyInfo( $property_name );
        }, $facet_properties );

		$this->result_properties = array_map( function( string $property_name ): PropertyInfo {
            return new PropertyInfo( $property_name );
        }, $result_properties );

		foreach ( $facet_properties as $property ) {
            $translation_pair = explode( "=", $property );
            $property_name = $translation_pair[0];

            $facet_property = new PropertyInfo( $property_name );

            $this->facet_property_ids[$property_name] = $facet_property->getPropertyID();
        }

        foreach ( $result_properties as $property ) {
            $this->result_property_ids[$property] = (new PropertyInfo( $property ))->getPropertyID();
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
	 * Returns the condition property name.
	 *
	 * @return PropertyInfo
	 */
	public function getConditionProperty(): PropertyInfo {
		return $this->condition_property;
	}

	/**
	 * Returns the condition value.
	 *
	 * @return string
	 */
	public function getConditionValue(): string {
		return $this->condition_value;
	}

	/**
	 * Returns the facet properties (properties that are not prefixed with "?").
	 *
	 * @return PropertyInfo[]
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
	 * @return PropertyInfo[]
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

		// Insert this object's search condition
		$database->insert(
			"search_condition",
			[
				"page_id" => $page_id,
				"condition_property" => $this->condition_property->getPropertyName(),
				"condition_value" => $this->condition_value
			]
		);

		$facet_properties = array_unique( $this->facet_properties );
		$result_properties = array_unique( $this->result_properties );

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
	}

	/**
	 * Deletes the SearchEngineConfig object associated with the given $page_id from the database.
	 *
	 * @param Database $database
	 * @param int $page_id
	 */
	public static function delete( $database, int $page_id ) {
		$database->delete(
			"search_condition",
			[ "page_id" => $page_id ]
		);

		$database->delete(
			"search_facets",
			[ "page_id" => $page_id ]
		);

		$database->delete(
			"search_properties",
			[ "page_id" => $page_id ]
		);
	}
}
