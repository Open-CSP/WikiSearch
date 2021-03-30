<?php


namespace WSSearch\QueryEngine\Filter;


use Elasticsearch\ClientBuilder;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WSSearch\QueryEngine\Factory\QueryEngineFactory;
use WSSearch\QueryEngine\QueryEngine;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class ChainedPropertyTermsFilter
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.8//query-dsl-terms-query.html
 */
class ChainedPropertyTermsFilter implements Filter {
    /**
     * @var string[] Terms to filter on
     */
    private $terms;

    /**
     * @var PropertyFieldMapper The property to filter on
     */
    private $property;

    /**
     * ChainedPropertyTermsFilter constructor.
     *
     * @param Filter $filter The filter to use to get the values for the to be constructed Terms filter
     * @param PropertyFieldMapper $property The property to filter on
     * @throws \MWException
     */
    public function __construct( Filter $filter, PropertyFieldMapper $property ) {
        $subquery = $this->constructSubqueryFromFilter( $filter );
        $terms = $this->getTermsFromSubquery( $subquery );

        $this->terms = $terms;
        $this->property = $property;
    }

    public function toQuery(): BoolQuery {
        // TODO: Implement toQuery() method.
        return new BoolQuery();
    }

    /**
     * Constructs a new ElasticSearch subquery from the given Filter.
     *
     * @param Filter $filter
     * @return array
     * @throws \MWException
     */
    private function constructSubqueryFromFilter( Filter $filter ): array {
        $query_engine = QueryEngineFactory::fromNull();
        $query_engine->addConstantScoreFilter( $filter );

        return $query_engine->toArray();
    }

    /**
     * Executes the given (sub)query and returns the terms extracted from it.
     *
     * @param array $query
     * @return array
     */
    private function getTermsFromSubquery( array $query ): array {
        $results = ClientBuilder::create()->setHosts( QueryEngineFactory::fromNull()->getElasticHosts() )->build()->search( $query );

        var_dump($results);

        return [];
    }
}