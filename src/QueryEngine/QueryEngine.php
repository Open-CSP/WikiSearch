<?php

namespace WikiSearch\QueryEngine;

use MediaWiki\MediaWikiServices;
use MWException;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\ConstantScoreQuery;
use ONGR\ElasticsearchDSL\Query\Compound\FunctionScoreQuery;
use ONGR\ElasticsearchDSL\Search;
use WikiMap;
use WikiSearch\Logger;
use WikiSearch\QueryEngine\Aggregation\AbstractAggregation;
use WikiSearch\QueryEngine\Aggregation\AbstractPropertyAggregation;
use WikiSearch\QueryEngine\Filter\Filter;
use WikiSearch\QueryEngine\Filter\PropertyFilter;
use WikiSearch\QueryEngine\Highlighter\Highlighter;
use WikiSearch\QueryEngine\Sort\Sort;
use WikiSearch\SMW\SMWQueryProcessor;
use WikiSearch\WikiSearchServices;

/**
 * Class QueryEngine
 *
 * @package WikiSearch\QueryEngine
 */
class QueryEngine {
    private const DEFAULT_RESULT_LIMIT = 10;
    private const AGGREGATION_PROPERTY_PARAMETER = '_wikisearch_aggregation_property';

	/**
	 * @var Search
	 */
	private Search $search;

	/**
	 * The "index" to use for the ElasticSearch query.
	 *
	 * @var string
	 */
	private string $index;

	/**
	 * The base ElasticSearch query.
	 *
	 * @var array|null
	 */
	private ?array $baseQuery = null;

	/**
	 * The main constant score boolean query filter.
	 *
	 * @var BoolQuery
	 */
	private BoolQuery $constantScoreFilters;

	/**
	 * The main function score boolean query filter.
	 *
	 * @var BoolQuery
	 */
	private BoolQuery $functionScoreFilters;

	/**
	 * List of properties to include in the "_source" field.
	 *
	 * @var array
	 */
	private array $sources = [
        // Subject metadata
		'subject.*',
        // Display title of
		'P:16.*',
        // Modification date
		'P:29.*'
	];

	public function __construct( string $index ) {
		$this->index = $index;

		$this->constantScoreFilters = new BoolQuery();
		$this->functionScoreFilters = new BoolQuery();

        $this->search = ( new Search() )
            ->setSize( self::DEFAULT_RESULT_LIMIT )
            ->addQuery( new ConstantScoreQuery( $this->constantScoreFilters ) )
            ->addQuery( new FunctionScoreQuery( $this->functionScoreFilters ) );
	}

    /**
     * Adds an aggregation to the query.
     *
     * @param AbstractAggregation $aggregation
     * @return QueryEngine
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations.html
     */
	public function addAggregation(AbstractAggregation $aggregation ): self {
        $filterAggregation = new FilterAggregation( $aggregation->getName(), new BoolQuery() );
        $filterAggregation->addAggregation( $aggregation->toQuery() );

        if ( $aggregation instanceof AbstractPropertyAggregation ) {
            // Store which property this aggregation affects
            $filterAggregation->{self::AGGREGATION_PROPERTY_PARAMETER} = $aggregation->getProperty();
        }

        $this->search->addAggregation( $filterAggregation );

        return $this;
	}

	/**
	 * Adds a filter to the constant-score fragment of the query.
	 *
	 * @param Filter $filter
	 * @param string $occur The occurrence type for the added filter (should be a BoolQuery constant)
     * @return QueryEngine
	 *
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-bool-query.html
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-constant-score-query.html
	 */
	public function addConstantScoreFilter(Filter $filter, string $occur = BoolQuery::MUST ): self {
		if ( $filter->isPostFilter() ) {
			$this->addPostFilter( $filter, $occur );
		} else {
			$this->constantScoreFilters->add( $filter->toQuery(), $occur );
		}

        return $this;
	}

	/**
	 * Adds a filter to the function-score fragment of the query.
	 *
	 * @param Filter $filter
	 * @param string $occur The occurrence type for the added filter (should be a BoolQuery constant)
     * @return QueryEngine
	 *
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-bool-query.html
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-function-score-query.html
	 */
	public function addFunctionScoreFilter(Filter $filter, string $occur = BoolQuery::MUST ): self {
        if ( $filter->isPostFilter() ) {
            $this->addPostFilter( $filter, $occur );
        } else {
            $this->functionScoreFilters->add( $filter->toQuery(), $occur );
        }

        return $this;
	}

    /**
     * Adds a post filter to the query.
     *
     * @param Filter $filter
     * @param string $occur The occurrence type for the added filter (should be a BoolQuery constant)
     * @return QueryEngine
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-post-filter.html
     */
    public function addPostFilter(Filter $filter, string $occur = BoolQuery::MUST ): self {
        $filterQuery = $filter->toQuery();
        $this->search->addPostFilter( $filterQuery, $occur );

        // Post filters are applied after aggregations have been calculated. This makes it possible to have facets
        // that do not change after the filter they control is applied. This is useful for facets that should act like
        // a disjunction, where a should increase the number of results. However, this leaves us with the problem that
        // other aggregations that should be affected by the added filter are no longer accurate. To solve this, we also
        // add any post filter to the all other aggregations using a FilterAggregation.

        if ( !$filter instanceof PropertyFilter ) {
            return $this;
        }

        foreach ( $this->search->getAggregations() as $aggregation ) {
            if ( !$aggregation instanceof FilterAggregation ) {
                continue;
            }

            if (
                isset( $aggregation->{self::AGGREGATION_PROPERTY_PARAMETER} ) &&
                $aggregation->{self::AGGREGATION_PROPERTY_PARAMETER} === $filter->getField()
            ) {
                // This aggregation affects the same property
                continue;
            }

            $aggregationFilter = $aggregation->getFilter();
            if ( $aggregationFilter instanceof BoolQuery ) {
                $aggregationFilter->add( $filterQuery );;
            }
        }

        return $this;
    }

    /**
     * Adds a highlighter to the query.
     *
     * @param Highlighter $highlighter
     * @return QueryEngine
     */
	public function addHighlighter( Highlighter $highlighter ): self {
		$this->search->addHighlight( $highlighter->toQuery() );

        return $this;
	}

    /**
     * Adds a sort to the query.
     *
     * @param Sort $sort
     * @return QueryEngine
     */
	public function addSort( Sort $sort ): self {
		$this->search->addSort( $sort->toQuery() );

        return $this;
	}

    /**
     * Adds an item to the "_source" parameter. This allows users to specify explicitly which fields should
     * be returned from a search query.
     *
     * If no "_source" parameters are set, all fields are returned.
     *
     * @param string $source
     * @return QueryEngine
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.5/search-request-source-filtering.html
     */
	public function addSource( string $source ): self {
		$this->sources[] = $source;

        return $this;
	}

    /**
     * Sets the offset for the search (i.e. the first n results to discard).
     *
     * @param int $offset
     * @return QueryEngine
     */
	public function setOffset( int $offset ): self {
		$this->search->setFrom( $offset );

        return $this;
	}

	/**
	 * Sets the (maximum) number of results to return.
	 *
	 * @param int $limit
     * @return QueryEngine
	 */
	public function setLimit( int $limit ): self {
		$this->search->setSize( $limit );

        return $this;
	}

    /**
     * Sets the base Semantic MediaWiki query.
     *
     * @param string $baseQuery
     * @return QueryEngine
     */
	public function setBaseQuery( string $baseQuery ): self {
		try {
			$queryProcessor = new SMWQueryProcessor( $baseQuery );
			$query = $queryProcessor->toElasticSearchQuery();
		} catch ( MWException ) {
			// The query is invalid
			Logger::getLogger()->critical( 'Tried to set invalid query as the base query' );
			return $this;
		}

		$this->baseQuery = $query[0];

        return $this;
	}

	/**
	 * Converts this class into a full ElasticSearch query.
	 *
	 * @return array A complete ElasticSearch query
     */
	public function toQuery(): array {
        // Add source filtering to the query
        $this->search->setSource( $this->sources );

        $query = [
            "index" => $this->index,
            "body"  => $this->search->toArray()
        ];

        if ( isset( $this->baseQuery ) ) {
            // Combine the base query with the generated query
            $query = WikiSearchServices::getQueryCombinatorFactory()
                ->newQueryCombinator()
                ->add( $query )
                ->add( $this->baseQuery )
                ->getQuery();
        }

        return $query;
	}
}
