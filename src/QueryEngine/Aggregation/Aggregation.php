<?php

namespace WSSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use WSSearch\QueryEngine\QueryConvertable;

abstract class Aggregation implements QueryConvertable {
    /**
     * @var string
     */
    protected $aggregation_name;

    /**
     * Aggregation constructor.
     *
     * @param string $aggregation_name
     */
    public function __construct( string $aggregation_name ) {
        $this->aggregation_name = $aggregation_name;
    }

    /**
     * Sets the aggregation name. This name must be unique for each aggregation in a query.
     *
     * @param string $aggregation_name
     */
    public function setAggregationName( string $aggregation_name ) {
        $this->aggregation_name = $aggregation_name;
    }

    /**
     * @inheritDoc
     */
    public abstract function toQuery(): AbstractAggregation;
}