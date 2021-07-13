<?php

namespace WSSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use WSSearch\QueryEngine\QueryConvertable;

/**
 * Interface Aggregation
 *
 * @package WSSearch\QueryEngine\Aggregation
 */
interface Aggregation extends QueryConvertable {
	/**
	 * Returns the name of the aggregation.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * @inheritDoc
	 */
	public function toQuery(): AbstractAggregation;
}
