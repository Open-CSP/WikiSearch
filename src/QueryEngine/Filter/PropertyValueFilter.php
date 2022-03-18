<?php

namespace WikiSearch\QueryEngine\Filter;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyFilter
 *
 * Filters pages based on the values of their properties. This filter does not take
 * property chains into account.
 *
 * @see ChainedPropertyFilter for a filter that takes property chains into account
 *
 * @package WikiSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-term-query.html
 */
class PropertyValueFilter extends PropertyFilter {
	/**
	 * @var PropertyFieldMapper The property to filter on
	 */
	private PropertyFieldMapper $property;

	/**
	 * @var mixed The value the property to filter on
	 */
	private $property_value;

	/**
	 * PropertyFilter constructor.
	 *
	 * @param PropertyFieldMapper|string $property The name or object of the property to filter on
	 * @param mixed $property_value The value the property to filter on
	 */
	public function __construct( $property, $property_value ) {
		if ( is_string( $property ) ) {
			$property = new PropertyFieldMapper( $property );
		}

		if ( !( $property instanceof PropertyFieldMapper ) ) {
			Logger::getLogger()->critical(
				'Tried to construct a PropertyValueFilter with an invalid property: {property}',
				[
					'property' => $property
				]
			);

			throw new InvalidArgumentException( '$property must be of type string or PropertyFieldMapper' );
		}

		if ( !in_array( gettype( $property_value ), [ "boolean", "string", "integer", "double", "float" ] ) ) {
			Logger::getLogger()->critical(
				'Tried to construct a PropertyValueFilter with an invalid property value: {propertyValue}',
				[
					'propertyValue' => $property_value
				]
			);

			throw new InvalidArgumentException(
				'$property_value must be a of type boolean, string, integer, double or float'
			);
		}

		$this->property = $property;
		$this->property_value = $property_value;
	}

	/**
	 * Returns the property field mapper corresponding to this filter.
	 *
	 * @return PropertyFieldMapper
	 */
	public function getProperty(): PropertyFieldMapper {
		return $this->property;
	}

	/**
	 * Sets the property this filter will filter on.
	 *
	 * @param PropertyFieldMapper $property
	 */
	public function setPropertyName( PropertyFieldMapper $property ): void {
		$this->property = $property;
	}

	/**
	 * Sets the value of the property this filter will filter on.
	 *
	 * @param string $property_value
	 */
	public function setPropertyValue( $property_value ): void {
		if ( !in_array( gettype( $property_value ), [ "boolean", "string", "integer", "double", "float" ] ) ) {
			Logger::getLogger()->critical(
				'Tried to set an invalid property value: {propertyValue}',
				[
					'propertyValue' => $property_value
				]
			);

			throw new InvalidArgumentException(
				'$property_value must be a of type boolean, string, integer, double or float'
			);
		}

		$this->property_value = $property_value;
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function toQuery(): BoolQuery {
		$term_query = new TermQuery(
			$this->property->getPropertyField( true ),
			$this->property_value
		);

		$bool_query = new BoolQuery();
		$bool_query->add( $term_query, BoolQuery::FILTER );

		/*
		 * Example of such a query:
		 *
		 *  "bool": {
		 *      "filter": {
		 *          "term": {
		 *              "P:0.wpgID": 0
		 *          }
		 *      }
		 *  }
		 */

		return $bool_query;
	}
}
