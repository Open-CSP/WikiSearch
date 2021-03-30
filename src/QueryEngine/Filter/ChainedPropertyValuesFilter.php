<?php


namespace WSSearch\QueryEngine\Filter;

use Elasticsearch\ClientBuilder;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WSSearch\QueryEngine\Factory\QueryEngineFactory;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class ChainedPropertyTermsFilter
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.8//query-dsl-terms-query.html
 */
class ChainedPropertyValuesFilter implements Filter {
    /**
     * @var PropertyFieldMapper The property to filter on
     */
    private $property;

    /**
     * @var Filter The initial filter to use to get the terms for the to be constructed Terms filter
     */
    private $filter;

    /**
     * ChainedPropertyTermsFilter constructor.
     *
     * @param Filter $filter The initial filter to use to get the values for the to be constructed Terms filter
     * @param PropertyFieldMapper $property The property to filter on; if this property is also a property
     * chain, the class will return a ChainedPropertyTermsFilter containing the constructed TermsFilter
     */
    public function __construct( Filter $filter, PropertyFieldMapper $property ) {
        $this->filter = $filter;
        $this->property = $property;
    }

    /**
     * @return BoolQuery
     * @throws \MWException
     */
    public function toQuery(): BoolQuery {
        $query = $this->constructSubqueryFromFilter( $this->filter );
        $terms = $this->getTermsFromSubquery( $query );

        $filter = new PropertyValuesFilter( $this->property, $terms );

        if ( $this->property->getChainedPropertyFieldMapper() === null ) {
            return $filter->toQuery();
        }

        return ( new ChainedPropertyValuesFilter(
            $filter,
            $this->property->getChainedPropertyFieldMapper()
        ) )->toQuery();
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

        // TODO
        var_dump($results); die();

        return [];
    }
}