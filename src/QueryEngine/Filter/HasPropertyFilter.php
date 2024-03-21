<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Filters pages based on whether they have the specified property.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-query.html
 */
class HasPropertyFilter extends PropertyFilter {
	/**
	 * @var PropertyFieldMapper The field to filter on
	 */
	private PropertyFieldMapper $field;

	/**
	 * @param PropertyFieldMapper|string $field The name or object of the property to filter on
	 */
	public function __construct( string|PropertyFieldMapper $field ) {
		if ( is_string( $field ) ) {
			$field = new PropertyFieldMapper( $field );
		}

		$this->field = $field;
	}

	/**
	 * Returns the property field mapper corresponding to this filter.
	 *
	 * @return PropertyFieldMapper
	 */
	public function getField(): PropertyFieldMapper {
		return $this->field;
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function filterToQuery(): BoolQuery {
		$existsQuery = new ExistsQuery(
			$this->field->getPropertyField()
		);

		$boolQuery = new BoolQuery();
		$boolQuery->add( $existsQuery, BoolQuery::FILTER );

		return $boolQuery;
	}
}
