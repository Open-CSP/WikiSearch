<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Filters pages based on the values of their properties. This filter does not take
 * property chains into account.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-term-query.html
 */
class PropertyValueFilter extends PropertyFilter {
	/**
	 * @inheritDoc
	 * @param mixed $value The value the property to filter on
	 */
	public function __construct(
		string|PropertyFieldMapper $field,
		private bool|string|int|float $value
	) {
		parent::__construct( $field );
	}

	/**
	 * Converts the object to a BuilderInterface for use in the QueryEngine.
	 *
	 * @return BoolQuery
	 */
	public function filterToQuery(): BoolQuery {
		$field = $this->field->hasKeywordSubfield() ?
			$this->field->getKeywordField() :
			$this->field->getPropertyField();

		$termQuery = new TermQuery(
			$field,
			$this->value
		);

		$boolQuery = new BoolQuery();
		$boolQuery->add( $termQuery, BoolQuery::FILTER );

		return $boolQuery;
	}
}
