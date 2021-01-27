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

use BadMethodCallException;
use SMW\ApplicationFactory;
use SMW\DIProperty;
use SMW\Elastic\ElasticStore;

/**
 * Class PropertyInfo
 *
 * Queries the SemanticMediaWiki property store and stores information about the given property
 * name.
 *
 * @package WSSearch
 */
class PropertyInfo {
	/**
	 * @var int The unique ID of the property
	 */
	private $id;

	/**
	 * @var string The kind/datatype of the property
	 */
	private $type;

	/**
	 * @var string The name of the property as a string
	 */
	private $name;

	/**
	 * PropertyInfo constructor.
	 *
	 * @param string $property_name The name of the property
	 */
	public function __construct( string $property_name ) {
		$store = ApplicationFactory::getInstance()->getStore();

		if ( !$store instanceof ElasticStore ) {
			throw new BadMethodCallException( "WSSearch requires ElasticSearch to be installed" );
		}

		$property = new DIProperty( $property_name );

		$this->id   = $store->getObjectIds()->getSMWPropertyID( $property );
		$this->type = $property->findPropertyValueType() === "_txt" ? "txtField" : "wpgField";
		$this->name = $property_name;
	}

	/**
	 * Returns the ID of the property.
	 *
	 * @return int
	 */
	public function getPropertyID(): int {
		return $this->id;
	}

	/**
	 * Returns the type of this property.
	 *
	 * @return string
	 */
	public function getPropertyType(): string {
		return $this->type;
	}

	/**
	 * Returns the name of this property.
	 *
	 * @return string
	 */
	public function getPropertyName(): string {
		return $this->name;
	}
}
