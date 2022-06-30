<?php

namespace WikiSearch\QueryEngine\Filter;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyTextFilter
 *
 * Filters pages based on the value the specified property has. Unlike PropertyValueFilter, which requires a
 * full match of the given property value, this filter loosely matches based on the provided query string.
 *
 * @see ChainedPropertyFilter for a filter that takes property chains into account
 *
 * @package WikiSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-simple-query-string-query.html
 */
class PropertyTextFilter extends PropertyFilter {
	use QueryPreparationTrait;

	/**
	 * @var PropertyFieldMapper The property to filter on
	 */
	private PropertyFieldMapper $property;

	/**
	 * @var string The query string used to match the property value
	 */
	private string $property_value_query;

	/**
	 * @var string The default operator to use
	 */
	private string $default_operator;

	/**
	 * PropertyFilter constructor.
	 *
	 * Note: This filter requires a valid SearchEngineConfig to be defined via SearchEngine::$config.
	 *
	 * @param PropertyFieldMapper|string $property The name or object of the property to filter on
	 * @param string $property_value_query The query string used to match the property value
	 * @param string $default_operator The default operator to insert between words
	 */
	public function __construct( $property, string $property_value_query, string $default_operator ) {
		if ( is_string( $property ) ) {
			$property = new PropertyFieldMapper( $property );
		}

		if ( !( $property instanceof PropertyFieldMapper ) ) {
			Logger::getLogger()->critical(
				'Tried to construct a PropertyTextFilter with an invalid property: {property}',
				[
					'property' => $property
				]
			);

			throw new InvalidArgumentException( '$property must be of type string or PropertyFieldMapper' );
		}

		$this->property = $property;
		$this->property_value_query = $property_value_query;
		$this->default_operator = $default_operator;
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
	 * Sets the query string used to match the property value.
	 *
	 * @param string $property_value_query
	 */
	public function setPropertyValueQuery( string $property_value_query ): void {
		$this->property_value_query = $property_value_query;
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function filterToQuery(): BoolQuery {
		$search_term = $this->prepareQuery( $this->property_value_query );

		$query_string_query = new QueryStringQuery( $search_term );
		$query_string_query->setParameters( [
			"fields" => [ $this->property->getPropertyField() ],
			"default_operator" => $this->default_operator
		] );

		$bool_query = new BoolQuery();
		$bool_query->add( $query_string_query, BoolQuery::FILTER );

		return $bool_query;
	}
}
