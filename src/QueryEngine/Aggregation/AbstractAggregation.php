<?php

namespace WikiSearch\QueryEngine\Aggregation;

use WikiSearch\QueryEngine\QueryConvertable;

/**
 * Interface Aggregation
 *
 * @package WikiSearch\QueryEngine\Aggregation
 */
abstract class AbstractAggregation implements QueryConvertable {
	/**
	 * @param string $name The name of the aggregation
	 */
	public function __construct( protected string $name ) {
	}

	/**
	 * Returns the name of the aggregation.
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @inheritDoc
	 */
	abstract public function toQuery(): \ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
}
