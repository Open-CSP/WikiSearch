<?php

namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use WSSearch\Logger;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyExistsFilter
 *
 * Filters pages based on whether they have the specified property.
 *
 * @see ChainedPropertyFilter for a filter that takes property chains into account
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-query.html
 */
class HasPropertyFilter extends PropertyFilter {
	/**
	 * @var PropertyFieldMapper The property to filter on
	 */
	private PropertyFieldMapper $property;

	/**
	 * PropertyExistsFilter constructor.
	 *
	 * @param PropertyFieldMapper|string $property The name or object of the property to filter on
	 */
	public function __construct( $property ) {
		if ( is_string( $property ) ) {
			$property = new PropertyFieldMapper( $property );
		}

		if ( !( $property instanceof PropertyFieldMapper ) ) {
			Logger::getLogger()->critical( 'Tried to construct a HasPropertyFilter with an invalid property: {property}', [
				'property' => $property
			] );

			throw new \InvalidArgumentException();
		}

		$this->property = $property;
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
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function toQuery(): BoolQuery {
		$exists_query = new ExistsQuery(
			$this->property->getPropertyField()
		);

		$bool_query = new BoolQuery();
		$bool_query->add( $exists_query, BoolQuery::FILTER );

		/*
		 * Example of such a query:
		 *
		 *  "bool": {
		 *      "filter": {
		 *          "exists": {
		 *              "field": "P:2676.txtField"
		 *          }
		 *      }
		 *  }
		 */

		return $bool_query;
	}
}
