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

namespace WikiSearch\SMW;

use BadMethodCallException;
use SMW\ApplicationFactory;
use SMW\DataTypeRegistry;
use SMW\DIProperty;
use SMW\Elastic\ElasticStore;
use WikiSearch\Logger;

/**
 * Class PropertyFieldMapper
 *
 * @see https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/1f4bbda9bb8f7826ffabf00159cfdc0760043ca3/src/Elastic/docs/technical.md#field-mapping
 *
 * @package WikiSearch
 */
class PropertyFieldMapper {
	// The default property weight
	public const DEFAULT_PROPERTY_WEIGHT = 1;

	// List of special pre-defined properties in SemanticMediaWiki that do not appear on Special:Browse
	public const INTERNAL_PROPERTIES = [
		"attachment-author",
		"attachment-title",
		"attachment-content",
		"attachment-content_length",
		"attachment-content_type",
		"attachment-date",
		"attachment-language",
		"file_path",
		"file_sha1",
		"noop",
		"subject-title",
		"subject-subobject",
		"subject-namespace",
		"subject-interwiki",
		"subject-sortkey",
		"subject-serialization",
		"subject-sha1",
		"subject-rev_id",
		"subject-namespacename",
		"text_copy",
		"text_raw"
	];

	// List of field types that have a keyword subfield
	public const KEYWORD_FIELD_TYPES = [ "txt", "uri", "wpg", "num", "dat" ];

	// List of internal properties that have a keyword subfield
	public const KEYWORD_INTERNAL_PROPERTIES = [ "subject.title", "subject.interwiki", "subject.subobject", "subject.sortkey" ];

	// List of field types that have a search subfield
	public const SEARCH_FIELD_TYPES = [ "txt" ];

	// List of internal properties that have a search subfield
	public const SEARCH_INTERNAL_PROPERTIES = [ "text_raw", "text_copy", "subject.title" ];

	// List of internal properties that support fast vector highlighting
	public const FVH_INTERNAL_PROPERTIES = [ "noop", "text_copy", "text_raw", "subject.title", "subject.interwiki", "subject.subobject", "subject.sortkey", "subject.rev_id" ];

	/**
	 * @var int The unique ID of the property
	 */
	private int $property_id;

	/**
	 * @var string The kind/datatype of the property
	 */
	private string $property_field_type;

	/**
	 * @var string The key of the property as a string
	 */
	private string $property_key;

	/**
	 * @var string The human-readable name of the property as a string
	 */
	private string $property_name;

	/**
	 * @var int The weight this property was given
	 */
	private int $property_weight;

	/**
	 * @var PropertyFieldMapper|null The property field mapper for the chained property
	 *
	 * For instance, given the property name "Verrijking.Inhoudsindicatie", the current PropertyFieldMapper would
	 * contain information about "Inhoudsindicatie" and the field mapper contained in this class field would contain
	 * information about "Verrijking". This field is "null" if this is the property at the beginning of the chain.
	 */
	private ?PropertyFieldMapper $chained_property_field_mapper = null;

	/**
	 * PropertyFieldMapper constructor.
	 *
	 * @param string $property_name The name of the property (chained property name allowed)
	 */
	public function __construct( string $property_name ) {
		$store = ApplicationFactory::getInstance()->getStore();

		if ( !$store instanceof ElasticStore ) {
			Logger::getLogger()->critical(
				'Tried to construct PropertyFieldMapper for property {propertyName} without an ElasticStore',
				[
					'propertyName' => $property_name
				]
			);

			throw new BadMethodCallException( "WikiSearch requires ElasticSearch to be installed" );
		}

		list( $this->chained_property_field_mapper, $property_name ) = $this->parseChainedProperty( $property_name );
		list( $this->property_weight, $property_name ) = $this->parsePropertyWeight( $property_name );

		$this->property_name = $property_name;

		if ( $this->isInternalProperty() ) {
			$this->property_key = str_replace( "-", ".", $this->property_name );
		} else {
			$this->property_key = PropertyAliasMapper::findPropertyKey( $this->property_name );
		}

		$data_item_property = new DIProperty( $this->property_key );
		$this->property_id = $store->getObjectIds()->getSMWPropertyId( $data_item_property );
		$this->property_field_type = str_replace(
			'_',
			'',
			DataTypeRegistry::getInstance()->getFieldType( $data_item_property->findPropertyValueType() )
		);
	}

	/**
	 * Returns the type of this property.
	 *
	 * @return string
	 * @deprecated Use getPropertyFieldType() instead
	 */
	public function getPropertyType(): string {
		return sprintf( "%sField", $this->property_field_type );
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
	 * Returns the field type of this property.
	 *
	 * @return string
	 */
	public function getPropertyFieldType(): string {
		return $this->property_field_type;
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
	 * Returns this property's PID.
	 *
	 * @return string
	 */
	public function getPID(): string {
		return "P:{$this->property_id}";
	}

	/**
	 * Returns the weight this property was given.
	 *
	 * @return int
	 */
	public function getPropertyWeight(): int {
		return $this->property_weight;
	}

	/**
	 * Returns the field associated with this property.
	 *
	 * @return string
	 */
	public function getPropertyField(): string {
		if ( $this->isInternalProperty() ) {
			// Internal properties are represented by their key and do not have an ID
			return $this->property_key;
		}

		return sprintf( "%s.%sField", $this->getPID(), $this->getPropertyFieldType() );
	}

	/**
	 * Returns the keyword field associated with this property. The caller is responsible for checking if this field
	 * exists.
	 *
	 * @return string
	 */
	public function getKeywordField(): string {
		return sprintf( "%s.keyword", $this->getPropertyField() );
	}

	/**
	 * Returns the search field associated with this property. The caller is responsible for checking if this field
	 * exists.
	 *
	 * @return string
	 */
	public function getSearchField(): string {
		return sprintf( "%s.search", $this->getPropertyField() );
	}

	/**
	 * Returns the field associated with this property, with the weight. The caller is responsible for checking if this
	 * field exists.
	 *
	 * @return string
	 */
	public function getWeightedPropertyField(): string {
		return sprintf( "%s^%d", $this->getPropertyField(), $this->property_weight );
	}

	/**
	 * Returns the keyword subfield associated with this property, if it exists, with the weight. The caller is
	 * responsible for checking if this field exists.
	 *
	 * @return string
	 */
	public function getWeightedKeywordField(): string {
		return sprintf( "%s^%d", $this->getKeywordField(), $this->property_weight );
	}

	/**
	 * Returns the search subfield associated with this property, if it exists, with the weight. The caller is
	 * responsible for checking if this field exists.
	 *
	 * @return string
	 */
	public function getWeightedSearchField(): string {
		return sprintf( "%s^%d", $this->getSearchField(), $this->property_weight );
	}

	/**
	 * Returns the property field mapper for the chained property.
	 *
	 * For instance, given the property name "Verrijking.Inhoudsindicatie", the current PropertyFieldMapper would
	 * contain information about "Inhoudsindicatie" and the field mapper contained in this class field would contain
	 * information about "Verrijking". This field is "null" if this is the property at the beginning of the chain.
	 *
	 * @return PropertyFieldMapper|null
	 */
	public function getChainedPropertyFieldMapper(): ?PropertyFieldMapper {
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
	 * Returns true if and only if this property has a keyword field.
	 *
	 * @return bool
	 */
	public function hasKeywordSubfield(): bool {
		if ( $this->isInternalProperty() ) {
			return in_array( $this->property_key, self::KEYWORD_INTERNAL_PROPERTIES, true );
		} else {
			return in_array( $this->property_field_type, self::KEYWORD_FIELD_TYPES, true );
		}
	}

	/**
	 * Returns true if and only if this property has a search subfield.
	 *
	 * @return bool
	 */
	public function hasSearchSubfield(): bool {
		if ( $this->isInternalProperty() ) {
			return in_array( $this->property_key, self::SEARCH_INTERNAL_PROPERTIES, true );
		} else {
			return in_array( $this->property_field_type, self::SEARCH_FIELD_TYPES, true );
		}
	}

	/**
	 * Returns true if and only if this property is not a regular property, but a special property that does not
	 * appear on Special:Browse.
	 *
	 * @return bool
	 */
	public function isInternalProperty(): bool {
		return in_array( $this->property_name, self::INTERNAL_PROPERTIES, true );
	}

	/**
	 * Returns true if and only if this property supports fast vector highlighting.
	 *
	 * @return bool
	 */
	public function supportsFVH(): bool {
		return !$this->isInternalProperty() || in_array( $this->property_key, self::FVH_INTERNAL_PROPERTIES, true );
	}

	/**
	 * Parses the given property name, and returns the result in the form of:
	 *
	 *  [$weight, $remainder_property_name]
	 *
	 * If the property name does not explicitly specify a weight, the default weight is returned.
	 *
	 * @param string $property_name
	 * @return array
	 */
	private static function parsePropertyWeight( string $property_name ): array {
		// Split the property name on "^" to account for weights
		$parts = explode( "^", $property_name );

		// Pop the last element from the parts so we can inspect it
		$maybe_property_weight = array_pop( $parts );

		if ( preg_match( '/^[0-9]+$/', $maybe_property_weight ) === 1 ) {
			// We have a property weight
			$property_weight = intval( $maybe_property_weight );
		} else {
			// We don't have an explicit property weight
			$property_weight = self::DEFAULT_PROPERTY_WEIGHT;
			$parts[] = $maybe_property_weight;
		}

		return [ $property_weight, implode( "^", $parts ) ];
	}

	/**
	 * Parses the given property name, and returns the result in the form of:
	 *
	 *  [$chained_property_field_mapper, $remainder_property_name]
	 *
	 * A chained property takes the form of "foo.bar.quz".
	 *
	 * @param string $property_name
	 * @return array
	 */
	private static function parseChainedProperty( string $property_name ): array {
		// Split on the last period in the property name
		$property_name_chain = explode( ".", $property_name );
		$final_property_name = array_pop( $property_name_chain );
		$chained_property_name = implode( ".", $property_name_chain );

		if ( $chained_property_name !== "" ) {
			// Recursively construct the chained field mapper, if this is a chained property
			$chained_field_mapper = new PropertyFieldMapper( $chained_property_name );
		} else {
			$chained_field_mapper = null;
		}

		return [ $chained_field_mapper, $final_property_name ];
	}
}
