<?php

namespace WSSearch\QueryEngine;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\ConstantScoreQuery;
use ONGR\ElasticsearchDSL\Query\Compound\FunctionScoreQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Search;
use WSSearch\QueryEngine\Aggregation\Aggregation;
use WSSearch\QueryEngine\Aggregation\PropertyAggregation;
use WSSearch\QueryEngine\Filter\AbstractFilter;
use WSSearch\QueryEngine\Filter\PropertyFilter;
use WSSearch\QueryEngine\Highlighter\Highlighter;
use WSSearch\QueryEngine\Sort\Sort;
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
	 * List of aggregations to calculate.
	 *
	 * @var Aggregation[]
	 */
	private $aggregations = [];

	/**
	 * List of filters to be applied after aggregations have been calculated.
	 *
	 * @var AbstractFilter[]
	 */
	private $post_filters = [];

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
    	$this->aggregations[] = $aggregation;
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
    public function addConstantScoreFilter( AbstractFilter $filter, string $occur = BoolQuery::MUST ) {
    	$query = $filter->toQuery();

    	if ( $filter->isPostFilter() ) {
    		$this->post_filters[] = $filter;
		} else {
			$this->constant_score_filters->add($query, $occur);
		}
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
    public function addFunctionScoreFilter( AbstractFilter $filter, string $occur = BoolQuery::MUST ) {
    	$query = $filter->toQuery();

		if ( $filter->isPostFilter() ) {
			$this->post_filters[] = $filter;
		} else {
			$this->function_score_filters->add($query, $occur);
		}
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
    	$elasticsearch_search = clone $this->elasticsearch_search;
		$elasticsearch_search = $this->applyAggregationsAndPostFilters(
    		$elasticsearch_search,
			$this->aggregations,
			$this->post_filters
		);

        $query = [
            "index" => $this->elasticsearch_index,
            "body"  => $elasticsearch_search->toArray()
        ];

        if ( isset( $this->base_query ) ) {
            $query = ( new QueryCombinator( $query ) )->add( $this->base_query )->getQuery();
        }

        return $query;
    }

	/**
	 * Applies the given aggregations and post filters to the given Search object.
	 *
	 * @param Search $search
	 * @param Aggregation[] $aggregations
	 * @param AbstractFilter[] $post_filters
	 *
	 * @return Search
	 */
    private static function applyAggregationsAndPostFilters(
    	Search $search,
		array $aggregations,
		array $post_filters
	): Search {
    	foreach ( $post_filters as $filter ) {
    		$search->addPostFilter( $filter );
		}

		foreach ( $aggregations as $aggregation ) {
			$search->addAggregation( self::constructAggregation( $aggregation, $post_filters ) );
		}

		return $search;
	}

	/**
	 * Constructs a new FilterAggregation from the given Aggregation and post filters.
	 *
	 * @param Aggregation $aggregation
	 * @param AbstractFilter[] $post_filters
	 * @return AbstractAggregation
	 */
	private static function constructAggregation(
		Aggregation $aggregation,
		array $post_filters
	) {
		$has_filter_applied = false;

		$compound_filter = new BoolQuery();
		$aggregation_property = $aggregation instanceof PropertyAggregation ? $aggregation->getProperty() : null;

		foreach ( $post_filters as $filter ) {
			$filter_property = $filter instanceof PropertyFilter ? $filter->getProperty() : null;
			$filter_belongs_to_aggregation = $aggregation_property !== null &&
				$filter_property !== null &&
				$filter_property->getPropertyField( false ) === $filter_property->getPropertyField( false );

			// If the post-filter belongs to the aggregation, it should NOT be added to the filter aggregation
			if ( !$filter_belongs_to_aggregation ) {
				$has_filter_applied = true;
				$compound_filter->add( $filter->toQuery() );
			}
		}

		if ( !$has_filter_applied ) {
			return $aggregation->toQuery();
		} else {
			$filter_aggregation = new FilterAggregation( $aggregation->getName(), $compound_filter );
			$filter_aggregation->addAggregation( $aggregation->toQuery() );

			return $filter_aggregation;
		}
	}
}