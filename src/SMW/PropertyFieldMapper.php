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
use SMW\DIWikiPage;
use SMW\Elastic\ElasticStore;

/**
 * Class PropertyFieldMapper
 *
 * @see https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/1f4bbda9bb8f7826ffabf00159cfdc0760043ca3/src/Elastic/docs/technical.md#field-mapping
 *
 * @package WSSearch
 */
class PropertyFieldMapper {
	const SPECIAL_PROPERTIES = [
		"text_copy",
		"text_raw",
		"subject-title",
		"subject-subobject",
		"subject-namespace",
		"subject-interwiki",
		"subject-sortkey",
		"subject-serialization",
		"subject-sha1",
		"subject-rev_id",
		"subject-namespacename",
		"attachment-title",
		"attachment-content"
	];

	/**
	 * @var int The unique ID of the property
	 */
	private $property_id;

	/**
	 * @var string The kind/datatype of the property
	 */
	private $property_type;

	/**
	 * @var string The key of the property as a string
	 */
	private $property_key;

    /**
     * @var string The human-readable name of the property as a string
     */
    private $property_name;

    /**
     * @var PropertyFieldMapper The property field mapper for the chained property
     *
     * For instance, given the property name "Verrijking.Inhoudsindicatie", the current PropertyFieldMapper would
     * contain information about "Inhoudsindicatie" and the field mapper contained in this class field would contain
     * information about "Verrijking". This field is "null" if this is the property at the beginning of the chain.
     */
    private $chained_property_field_mapper = null;

    /**
	 * PropertyFieldMapper constructor.
	 *
	 * @param string $property_name The name of the property (chained property name allowed)
	 */
	public function __construct( string $property_name ) {
		$store = ApplicationFactory::getInstance()->getStore();

		if ( !$store instanceof ElasticStore ) {
			throw new BadMethodCallException( "WSSearch requires ElasticSearch to be installed" );
		}

		// Split the property name on "." to account for chained properties
		$property_name_chain = explode( ".", $property_name );
		$property_name = array_pop( $property_name_chain );
		$chained_property_name = implode( ".", $property_name_chain );

		// Check whether we are the property at the beginning of the chain
		if ( $chained_property_name !== "" ) {
			$this->chained_property_field_mapper = new PropertyFieldMapper( $chained_property_name );
		}

        $this->property_name = $property_name;
        $this->property_key = str_replace( " ", "_", $this->translateSpecialProperties( $property_name ) );

		$property = new DIProperty( $this->property_key );
        $this->property_id = $store->getObjectIds()->getSMWPropertyID( $property );
        $this->property_type = $this->translatePropertyValueType( $property->findPropertyValueType() );
	}

	/**
	 * Returns the ID of the property.
	 *
	 * @return int
	 */
	public function getPropertyID(): int {
		return $this->property_id;
	}

	/**
	 * Returns the type of this property.
	 *
	 * @return string
	 */
	public function getPropertyType(): string {
		return $this->property_type;
	}

	/**
	 * Returns the key of this property.
	 *
	 * @return string
	 */
	public function getPropertyKey(): string {
		return $this->property_key;
	}

    /**
     * Returns the human-readable name of this property.
     *
     * @return string
     */
    public function getPropertyName(): string {
        return $this->property_name;
    }

    /**
     * Returns the field associated with this property.
     *
     * @param bool $keyword Give the keyword field for this property instead, if it is available
     * @return string
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/keyword.html
     */
	public function getPropertyField( bool $keyword = false ): string {
		if ( in_array( $this->property_name, self::SPECIAL_PROPERTIES ) ) {
			return str_replace( "-", ".", $this->property_name );
		}

		// TODO: Make this more general and figure out when, and when not, to use ".keyword"
	    switch ( $this->property_type ) {
			case "numField":
			case "booField":
				$suffix = "";
				break;
			default:
				$suffix = ".keyword";
				break;
		}

		if ( $keyword === false ) {
			$suffix = "";
		}

	    return "P:{$this->property_id}.{$this->property_type}{$suffix}";
    }

    /**
     * Returns the property's page field identifier.
     *
     * @return string
     */
    public function getPropertyPageFieldIdentifier(): string {
	    return "P:{$this->property_id}.wpgID";
    }

    /**
     * Returns the property field mapper for the chained property.
     *
     * For instance, given the property name "Verrijking.Inhoudsindicatie", the current PropertyFieldMapper would
     * contain information about "Inhoudsindicatie" and the field mapper contained in this class field would contain
     * information about "Verrijking". This field is "null" if this is the property at the beginning of the chain.
     *
     * @return PropertyFieldMapper
     */
    public function getChainedPropertyFieldMapper() {
	    return $this->chained_property_field_mapper;
    }

    /**
     * Returns true if and only if this is a PropertyFieldMapper for a chained property.
     *
     * @return bool
     */
    public function isChained(): bool {
        return $this->chained_property_field_mapper !== null;
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
