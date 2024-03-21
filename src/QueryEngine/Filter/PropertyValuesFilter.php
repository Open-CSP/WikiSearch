<?php

namespace WikiSearch\QueryEngine\Filter;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Filters pages based on whether the specified property has any of the values as its
 * value. This filter does not take property chains into account.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
 */
class PropertyValuesFilter extends PropertyFilter {
	/**
	 * @inheritDoc
	 * @param array $values Values of the property
	 */
	public function __construct(
        string|PropertyFieldMapper $field,
        private array $values
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

		$termsQuery = new TermsQuery(
			$field,
			$this->values
		);

		$boolQuery = new BoolQuery();
		$boolQuery->add( $termsQuery, BoolQuery::FILTER );

		return $boolQuery;
	}
}
