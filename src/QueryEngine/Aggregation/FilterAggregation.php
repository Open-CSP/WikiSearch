<?php

namespace WSSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\RangeAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use WSSearch\QueryEngine\Filter\AbstractFilter;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class PropertyValuesAggregation
 *
 * A single bucket of all the documents in the current document set context that match a specified filter.
 *
 * @package WSSearch\QueryEngine\Aggregation
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-filter-aggregation.html
 */
class FilterAggregation implements Aggregation {
    /**
     * @var string
     */
    private $aggregation_name;

    /**
     * @var Aggregation[]
     */
    private $aggregations;

    /**
     * @var AbstractFilter
     */
    private $filter;


    /**
     * FilterAggregation constructor.
     *
     * @param AbstractFilter $filter
     * @param Aggregation[] $aggregations
     * @param string|null $aggregation_name
     */
    public function __construct(AbstractFilter $filter, array $aggregations, string $aggregation_name ) {
        $this->filter = $filter;
        $this->aggregations = $aggregations;
        $this->aggregation_name = $aggregation_name;
    }

    /**
     * @inheritDoc
     */
    public function toQuery(): AbstractAggregation {
        $filter_aggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation(
            $this->aggregation_name,
            $this->filter->toQuery()
        );

        foreach ( $this->aggregations as $aggregation ) {
            $filter_aggregation->addAggregation( $aggregation->toQuery() );
        }

        return $filter_aggregation;
    }
}