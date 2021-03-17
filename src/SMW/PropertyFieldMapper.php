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

namespace WSSearch\SMW;

use BadMethodCallException;
use SMW\ApplicationFactory;
use SMW\DataTypeRegistry;
use SMW\DIProperty;
use SMW\Elastic\ElasticStore;

/**
 * Class PropertyFieldMapper
 *
 * @see https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/1f4bbda9bb8f7826ffabf00159cfdc0760043ca3/src/Elastic/docs/technical.md#field-mapping
 *
 * @package WSSearch
 */
class PropertyFieldMapper {
	/**
	 * @var int The unique ID of the property
	 */
	private $id;

	/**
	 * @var string The kind/datatype of the property
	 */
	private $type;

	/**
	 * @var string The key of the property as a string
	 */
	private $key;

    /**
     * @var string The human-readable name of the property as a string
     */
    private $name;

    /**
     * @var string The Elastic field for this property
     */
    private $field;

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

		$this->key = str_replace( " ", "_", $this->translateSpecialProperties( $this->key ) );

		$property = new DIProperty( $this->key );

        $this->name = $property_name;
        $this->id = $store->getObjectIds()->getSMWPropertyID( $property );
        $this->type = $this->translatePropertyValueType( $property->findPropertyValueType() );
        $this->field = "P:" . $this->id . "." . $this->type;

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
	 * Returns the key of this property.
	 *
	 * @return string
	 */
	public function getPropertyKey(): string {
		return $this->key;
	}

    /**
     * Returns the human-readable name of this property.
     *
     * @return string
     */
    public function getPropertyName(): string {
        return $this->name;
    }

    /**
     * Returns the field associated with this property.
     *
     * @return string
     */
	public function getPropertyField(): string {
	    return $this->field;
    }

    /**
     * Translates the given property name to a special property key if it is a special property.
     *
     * @param string $property_name
     * @return string
     */
    private function translateSpecialProperties( string $property_name ): string {
        return PropertyAliasMapper::findPropertyKey( $property_name );
    }

    /**
     * Translates the given property value type to the corresponding Elastic property value type (including the
     * Field affix).
     *
     * @param string $type
     * @return string
     *
     * @see https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/1f4bbda9bb8f7826ffabf00159cfdc0760043ca3/src/Elastic/docs/technical.md#field-mapping
     */
    private function translatePropertyValueType( string $type ): string {
        return trim( $type, "_" ) . "Field";
    }
}
