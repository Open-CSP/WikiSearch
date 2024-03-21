<?php

namespace WikiSearch\QueryEngine\Filter;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\FuzzyQuery;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Filters pages based on the values of their properties using fuzzy matching. This filter does not take
 * property chains into account.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-fuzzy-query.html
 */
class PropertyFuzzyValueFilter extends PropertyFilter {
	/**
	 * @inheritDoc
	 * @param string $value The value to fuzzily filter on
	 * @param int|string $fuzziness The fuzziness to use, or "AUTO"
	 */
	public function __construct(
        string|PropertyFieldMapper $field,
        private string $value,
        private int|string $fuzziness = "AUTO"
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

		$parameters = [
			"fuzziness" => $this->fuzziness
		];

		$fuzzyQuery = new FuzzyQuery( $field, $this->value, $parameters );

		$boolQuery = new BoolQuery();
		$boolQuery->add( $fuzzyQuery, BoolQuery::FILTER );

		return $boolQuery;
	}
}
