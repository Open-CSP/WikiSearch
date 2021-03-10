<?php

namespace WSSearch\QueryEngine;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\ConstantScoreQuery;
use ONGR\ElasticsearchDSL\Query\Compound\FunctionScoreQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\SearchEndpoint\SortEndpoint;
use WSSearch\QueryEngine\Aggregation\Aggregation;
use WSSearch\QueryEngine\Aggregation\PropertyAggregation;
use WSSearch\QueryEngine\Filter\Filter;
use WSSearch\QueryEngine\Filter\PropertyValueFilter;
use WSSearch\QueryEngine\Highlighter\DefaultHighlighter;
use WSSearch\QueryEngine\Highlighter\Highlighter;
use WSSearch\SearchEngineConfig;
use WSSearch\SMW\SMWQueryProcessor;

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
     * QueryEngine constructor.
     *
     * @param string $index The ElasticSearch index to create the queries for
     */
    public function __construct( string $index ) {
        $this->elasticsearch_index = $index;

        $this->elasticsearch_search = new Search();

        $this->elasticsearch_search->setSize( MediaWikiServices::getInstance()->getMainConfig()->get( "WSSearchDefaultResultLimit" ) );
        $this->elasticsearch_search->addHighlight( ( new DefaultHighlighter() )->toQuery() );

        $this->constant_score_filters = new BoolQuery();
        $this->function_score_filters = new BoolQuery();

        $this->elasticsearch_search->addQuery( new ConstantScoreQuery( $this->constant_score_filters ) );
        $this->elasticsearch_search->addQuery( new FunctionScoreQuery( $this->function_score_filters ) );
    }

    /**
     * Constructs a new QueryEngine from the given SearchEngineConfig.
     *
     * @param SearchEngineConfig $config
     * @return QueryEngine
     */
    public static function newFromConfig( SearchEngineConfig $config ) {
        $mw_config = MediaWikiServices::getInstance()->getMainConfig();
        $index = $mw_config->get( "WSSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );

        $query_engine = new QueryEngine( $index );

        foreach ( $config->getFacetProperties() as $facet_property ) {
            $translation_pair = explode( "=", $facet_property );
            $property_name = $translation_pair[0];

            $query_engine->addAggregation( new PropertyAggregation( $property_name ) );
        }

        $search_parameters = $config->getSearchParameters();
        if ( isset( $search_parameters["base query"] ) ) {
            $query_engine->setBaseQuery( $search_parameters["base query"] );
        }

        return $query_engine;
    }

    /**
     * Adds aggregations to the query.
     *
     * @param Aggregation[] $aggregations
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations.html
     */
    public function addAggregations( array $aggregations ) {
        foreach ( $aggregations as $aggregation ) {
            $this->addAggregation( $aggregation );
        }
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
     * @param Filter $filter
     * @param string $occur The occurrence type for the added filter (should be a BoolQuery constant)
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-bool-query.html
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-constant-score-query.html
     */
    public function addConstantScoreFilter( Filter $filter, string $occur = BoolQuery::MUST ) {
        $this->constant_score_filters->add($filter->toQuery(), $occur);
    }

    /**
     * Adds a filter to the function-score fragment of the query.
     *
     * @param Filter $filter
     * @param string $occur The occurrence type for the added filter (should be a BoolQuery constant)
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-bool-query.html
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-function-score-query.html
     */
    public function addFunctionScoreFilter( Filter $filter, string $occur = BoolQuery::MUST ) {
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
    private function setBaseQuery( string $base_query ) {
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