<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Filters pages based on the value the specified property has. Unlike PropertyValueFilter, which requires a
 * full match of the given property value, this filter loosely matches based on the provided query string.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-simple-query-string-query.html
 */
class PropertyTextFilter extends PropertyFilter {
	/**
	 * @inheritDoc
	 * @param string $query The query string used to match the property value
	 * @param string $operator The default operator to insert between words
	 */
	public function __construct(
		string|PropertyFieldMapper $field,
		private string $query,
		private string $defaultOperator
	) {
		parent::__construct( $field );
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function filterToQuery(): BoolQuery {
		$fields = [ $this->field->getWeightedPropertyField() ];

		if ( $this->field->hasSearchSubfield() ) {
			$fields[] = $this->field->getWeightedSearchField();
		}

		$queryStringQuery = new QueryStringQuery( $this->query );
		$queryStringQuery->setParameters( [
			"fields" => $fields,
			"default_operator" => $this->defaultOperator,
			"analyze_wildcard" => true,
			"tie_breaker" => 1,
			"lenient" => true
		] );

		$boolQuery = new BoolQuery();
		$boolQuery->add( $queryStringQuery, BoolQuery::FILTER );

		return $boolQuery;
	}
}
