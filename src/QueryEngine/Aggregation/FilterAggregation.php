<?php

namespace WikiSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use WikiSearch\QueryEngine\Filter\AbstractFilter;

/**
 * A single bucket of all the documents in the current document set context that match a specified filter.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-filter-aggregation.html
 */
class FilterAggregation extends Aggregation {
	/**
     * @inheritDoc
	 * @param AbstractFilter $filter
	 * @param Aggregation[] $aggregations
	 * @param string $name
	 */
	public function __construct( private AbstractFilter $filter, private array $aggregations, string $name ) {
		parent::__construct( $name );
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): AbstractAggregation {
		$filterAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation(
			$this->name,
			$this->filter->toQuery()
		);

		foreach ( $this->aggregations as $aggregation ) {
			$filterAggregation->addAggregation( $aggregation->toQuery() );
		}

		return $filterAggregation;
	}
}
