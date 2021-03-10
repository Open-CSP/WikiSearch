<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WSSearch\QueryEngine\QueryConvertable;

/**
 * Interface Filter
 *
 * @package WSSearch\QueryEngine\Filter
 */
interface Filter extends QueryConvertable {
    function toQuery(): BoolQuery;
}