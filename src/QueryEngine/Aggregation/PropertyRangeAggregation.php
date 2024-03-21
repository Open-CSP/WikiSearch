<?php

namespace WikiSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\RangeAggregation;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * A multi-bucket value source based aggregation that enables the user to define a
 * set of ranges - each representing a bucket.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-range-aggregation.html
 */
class PropertyRangeAggregation extends PropertyAggregation {
	/**
	 * @inheritDoc
	 * @param array $ranges
	 */
	public function __construct( string|PropertyFieldMapper $field, private array $ranges, string $name = null ) {
        parent::__construct( $field, $name );
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): AbstractAggregation {
		return new RangeAggregation(
			$this->name,
			$this->field->getPropertyField(),
			$this->ranges,
			true
		);
	}
}
