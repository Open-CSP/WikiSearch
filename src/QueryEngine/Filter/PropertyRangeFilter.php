<?php

namespace WikiSearch\QueryEngine\Filter;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Represents a date range filter to filter in between date properties values. This filter does not take
 * property chains into account.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-range-query.html
 */
class PropertyRangeFilter extends PropertyFilter {
	/**
	 * @inheritDoc
	 * @param int $from The lower end of the range
     * @param int $to The upper end of the range
	 * @param float|null $boost
	 */
	public function __construct(
        string|PropertyFieldMapper $field,
        private int $from,
        private int $to,
        private float $boost = 1.0
    ) {
		parent::__construct( $field );
	}

	/**
	 * @inheritDoc
	 */
	public function filterToQuery(): BoolQuery {
		$rangeQuery = new RangeQuery(
			$this->field->getPropertyField(),
			[
                "boost" => $this->boost,
                "from" => $this->from,
                "to" => $this->to,
            ]
		);

		$boolQuery = new BoolQuery();
		$boolQuery->add( $rangeQuery );

		return $boolQuery;
	}
}
