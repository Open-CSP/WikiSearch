<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WSSearch\QueryEngine\QueryConvertable;

/**
 * Class Filter
 *
 * @package WSSearch\QueryEngine\Filter
 */
abstract class Filter implements QueryConvertable {
    abstract function toQuery(): BoolQuery;
}