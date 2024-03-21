<?php

namespace WikiSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use WikiSearch\QueryEngine\QueryConvertable;

/**
 * Interface Aggregation
 *
 * @package WikiSearch\QueryEngine\Aggregation
 */
abstract class Aggregation implements QueryConvertable {
    /**
     * @param string $name The name of the aggregation
     */
    public function __construct(protected string $name ) {}

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
	abstract public function toQuery(): AbstractAggregation;
}
