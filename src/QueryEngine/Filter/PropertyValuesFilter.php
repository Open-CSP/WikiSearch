<?php

namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use WSSearch\Logger;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyPagesFilter
 *
 * Filters pages based on whether the specified property has any of the values as its
 * value. This filter does not take property chains into account.
 *
 * @see ChainedPropertyFilter for a filter that takes property chains into account
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
 */
class PropertyValuesFilter extends PropertyFilter {
	/**
	 * @var PropertyFieldMapper
	 */
	private $property;

	/**
	 * @var mixed[]
	 */
	private $property_values;

	/**
	 * PropertyTermsFilter constructor.
	 *
	 * @param PropertyFieldMapper $property The property that should match the given page IDs
	 * @param array $values Array values
	 */
	public function __construct( PropertyFieldMapper $property, array $values ) {
		$this->property = $property;
		$this->property_values = $values;

		foreach ( $values as $value ) {
			if ( !in_array( gettype( $value ), [ "boolean", "string", "integer", "double", "float" ] ) ) {
				Logger::getLogger()->critical( 'Tried to construct a PropertyValuesFilter with an invalid property value: {propertyValue}', [
					'propertyValue' => $value
				] );

				throw new \InvalidArgumentException();
			}
		}
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
	public function setPropertyName( PropertyFieldMapper $property ) {
		$this->property = $property;
	}

	/**
	 * Sets the values the given property should have.
	 *
	 * @param mixed[] $property_values
	 */
	public function setPropertyValues( array $property_values ) {
		foreach ( $property_values as $value ) {
			if ( !in_array( gettype( $value ), [ "boolean", "string", "integer", "double", "float" ] ) ) {
				Logger::getLogger()->critical( 'Tried to set an invalid property value: {propertyValue}', [
					'propertyValue' => $value
				] );

				throw new \InvalidArgumentException();
			}
		}

		$this->property_values = $property_values;
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function toQuery(): BoolQuery {
		$terms_query = new TermsQuery(
			$this->property->getPropertyField( true ),
			$this->property_values
		);

		$bool_query = new BoolQuery();
		$bool_query->add( $terms_query, BoolQuery::FILTER );

		return $bool_query;
	}
}
