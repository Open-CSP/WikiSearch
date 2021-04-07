<?php

namespace WSSearch\QueryEngine;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\ConstantScoreQuery;
use ONGR\ElasticsearchDSL\Query\Compound\FunctionScoreQuery;
use ONGR\ElasticsearchDSL\Search;
use WSSearch\QueryEngine\Aggregation\Aggregation;
use WSSearch\QueryEngine\Filter\AbstractFilter;
use WSSearch\QueryEngine\Highlighter\Highlighter;
use WSSearch\QueryEngine\Sort\Sort;
use WSSearch\SearchEngine;
use WSSearch\SMW\SMWQueryProcessor;

/**
 * Class QueryEngine
 *
 * @package WSSearch\QueryEngine
 */
class QueryEngine {
    /**
     * @var Search
     */
    private $elasticsearch_search;

    /**
     * The "index" to use for the ElasticSearch query.
     *
     * @var string
     */
    private $elasticsearch_index;

    /**
     * The main constant score boolean query filter.
     *
     * @var BoolQuery
     */
    private $constant_score_filters;

    /**
     * The main function score boolean query filter.
     *
     * @var BoolQuery
     */
    private $function_score_filters;

    /**
     * The base ElasticSearch query.
     *
     * @var array|null
     */
    private $base_query = null;

    /**
     * The configured ElasticSearch hosts.
     *
     * @var array
     */
    private $elasticsearch_hosts;

    /**
     * QueryEngine constructor.
     *
     * @param string $index The ElasticSearch index to create the queries for
     */
    public function __construct( string $index, array $hosts ) {
        $this->elasticsearch_index = $index;
        $this->elasticsearch_hosts = $hosts;

        $this->elasticsearch_search = new Search();
        $this->elasticsearch_search->setSize( MediaWikiServices::getInstance()->getMainConfig()->get( "WSSearchDefaultResultLimit" ) );

        $this->constant_score_filters = new BoolQuery();
        $this->function_score_filters = new BoolQuery();

        $this->elasticsearch_search->addQuery( new ConstantScoreQuery( $this->constant_score_filters ) );
        $this->elasticsearch_search->addQuery( new FunctionScoreQuery( $this->function_score_filters ) );
    }

    /**
     * Adds an aggregation to the query.
     *
     * @param Aggregation $aggregation
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations.html
     */
    public function addAggregation( Aggregation $aggregation ) {
        $this->elasticsearch_search->addAggregation( $aggregation->toQuery() );
    }

    /**
     * Adds a filter to the constant-score fragment of the query.
     *
     * @param AbstractFilter $filter
     * @param string $occur The occurrence type for the added filter (should be a BoolQuery constant)
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-bool-query.html
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-constant-score-query.html
     */
    public function addConstantScoreFilter(AbstractFilter $filter, string $occur = BoolQuery::MUST ) {
    	$this->constant_score_filters->add($filter->toQuery(), $occur);
    }

    /**
     * Adds a filter to the function-score fragment of the query.
     *
     * @param AbstractFilter $filter
     * @param string $occur The occurrence type for the added filter (should be a BoolQuery constant)
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-bool-query.html
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-function-score-query.html
     */
    public function addFunctionScoreFilter(AbstractFilter $filter, string $occur = BoolQuery::MUST ) {
        $this->function_score_filters->add( $filter->toQuery(), $occur );
    }

    /**
     * Adds a highlighter to the query.
     *
     * @param Highlighter $highlighter
     */
    public function addHighlighter( Highlighter $highlighter ) {
        $this->elasticsearch_search->addHighlight( $highlighter->toQuery() );
    }

    /**
     * Adds a sort to the query.
     *
     * @param Sort $sort
     */
    public function addSort( Sort $sort ) {
        $this->elasticsearch_search->addSort( $sort->toQuery() );
    }

    /**
     * Sets the "index" to use for the ElasticSearch query.
     *
     * @param string $index
     */
    public function setIndex( string $index ) {
        $this->elasticsearch_index = $index;
    }

    /**
     * Sets the offset for the search (i.e. the first n results to discard).
     *
     * @param int $offset
     */
    public function setOffset( int $offset ) {
        $this->elasticsearch_search->setFrom( $offset );
    }

    /**
     * Sets the (maximum) number of results to return.
     *
     * @param int $limit
     */
    public function setLimit( int $limit ) {
        $this->elasticsearch_search->setSize( $limit );
    }

    /**
     * Sets the base Semantic MediaWiki query.
     *
     * @param $base_query
     */
    public function setBaseQuery(string $base_query ) {
        try {
            $query_processor = new SMWQueryProcessor( $base_query );
            $elastic_search_query = $query_processor->toElasticSearchQuery();
        } catch( \MWException $exception ) {
            // The query is invalid
            return;
        }

        $this->base_query = $elastic_search_query[0];
    }

    /**
     * Returns the "Search" object. Can be used to alter the query directly.
     *
     * @return Search
     */
    public function _(): Search {
        return $this->elasticsearch_search;
    }

    /**
     * Returns the configured ElasticSearch hosts.
     *
     * @return array
     */
    public function getElasticHosts(): array {
        return $this->elasticsearch_hosts;
    }

    /**
     * Converts this class into a full ElasticSearch query.
     *
     * @return array A complete ElasticSearch query
     * @throws \MWException
     */
    public function toArray(): array {
        $query = [
            "index" => $this->elasticsearch_index,
            "body" => $this->elasticsearch_search->toArray()
        ];

        if ( isset( $this->base_query ) ) {
            $query = ( new QueryCombinator( $query ) )->add( $this->base_query )->getQuery();
        }

        return $query;
    }
}