<?php

namespace WikiSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use WikiSearch\QueryEngine\QueryConvertable;

/**
 * Interface Aggregation
 *
 * @package WikiSearch\QueryEngine\Aggregation
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
