<?php

namespace WSSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\BuilderInterface;
use WSSearch\QueryEngine\QueryConvertable;

/**
 * Interface Aggregation
 *
 * @package WSSearch\QueryEngine\Aggregation
 */
interface Aggregation extends QueryConvertable {
    /**
     * @inheritDoc
     */
    public function toQuery(): AbstractAggregation;
}