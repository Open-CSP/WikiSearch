<?php

namespace WikiSearch\QueryEngine;

use MediaWiki\MediaWikiServices;
use MWException;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\ConstantScoreQuery;
use ONGR\ElasticsearchDSL\Query\Compound\FunctionScoreQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use WikiMap;
use WikiSearch\Logger;
use WikiSearch\QueryEngine\Aggregation\Aggregation;
use WikiSearch\QueryEngine\Aggregation\PropertyAggregation;
use WikiSearch\QueryEngine\Filter\AbstractFilter;
use WikiSearch\QueryEngine\Filter\PropertyFilter;
use WikiSearch\QueryEngine\Highlighter\Highlighter;
use WikiSearch\QueryEngine\Sort\RelevanceSort;
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
    private const FILTER_FIELD_PARAMETER = '_wikisearch_filter_field';

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

	/**
	 * @var Sort[]
	 */
	private array $fallbackSorts = [];

    /**
     * @var array{AbstractFilter, string}[]
     */
    private array $postFilters = [];

	public function __construct( string $index ) {
        // TODO: Create service for retrieving index
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
	 * @param Aggregation $aggregation
	 *
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations.html
	 */
	public function addAggregation( Aggregation $aggregation ): void {
        $filterAggregation = new FilterAggregation( $aggregation->getName(), new BoolQuery() );
        $filterAggregation->addAggregation( $aggregation->toQuery() );

        if ( $aggregation instanceof PropertyAggregation ) {
            // Store which property this aggregation affects
            $filterAggregation->{self::AGGREGATION_PROPERTY_PARAMETER} = $aggregation->getProperty();
        }

        $this->search->addAggregation( $filterAggregation );
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
	public function addConstantScoreFilter( AbstractFilter $filter, string $occur = BoolQuery::MUST ): void {
		if ( $filter->isPostFilter() ) {
			$this->addPostFilter( $filter, $occur );
		} else {
			$this->constantScoreFilters->add( $filter->toQuery(), $occur );
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
	public function addFunctionScoreFilter( AbstractFilter $filter, string $occur = BoolQuery::MUST ): void {
        if ( $filter->isPostFilter() ) {
            $this->addPostFilter( $filter, $occur );
        } else {
            $this->functionScoreFilters->add( $filter->toQuery(), $occur );
        }
	}

    /**
     * Adds a post filter to the query.
     *
     * @param AbstractFilter $filter
     * @param string $occur The occurrence type for the added filter (should be a BoolQuery constant)
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-post-filter.html
     */
    public function addPostFilter( AbstractFilter $filter, string $occur = BoolQuery::MUST ): void {
        $this->postFilters[] = [$filter, $occur];
    }

	/**
	 * Adds a highlighter to the query.
	 *
	 * @param Highlighter $highlighter
	 */
	public function addHighlighter( Highlighter $highlighter ): void {
		$this->search->addHighlight( $highlighter->toQuery() );
	}

	/**
	 * Adds a sort to the query.
	 *
	 * @param Sort $sort
	 */
	public function addSort( Sort $sort ): void {
		$this->search->addSort( $sort->toQuery() );
	}

	/**
	 * Add fallback sorts. The first item in the list has the highest fallback priority, and the last item the lowest.
	 *
	 * @param Sort[] $sorts
	 * @return void
	 */
	public function addFallbackSorts( array $sorts ): void {
		$this->fallbackSorts = $sorts;
	}

	/**
	 * Adds an item to the "_source" parameter. This allows users to specify explicitly which fields should
	 * be returned from a search query.
	 *
	 * If no "_source" parameters are set, all fields are returned.
	 *
	 * @param string $source
	 * @return void
	 *
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.5/search-request-source-filtering.html
	 */
	public function addSource( string $source ): void {
		$this->sources[] = $source;
	}

	/**
	 * Sets the "index" to use for the ElasticSearch query.
	 *
	 * @param string $index
	 */
	public function setIndex( string $index ): void {
		$this->index = $index;
	}

	/**
	 * Sets the offset for the search (i.e. the first n results to discard).
	 *
	 * @param int $offset
	 */
	public function setOffset( int $offset ): void {
		$this->search->setFrom( $offset );
	}

	/**
	 * Sets the (maximum) number of results to return.
	 *
	 * @param int $limit
	 */
	public function setLimit( int $limit ): void {
		$this->search->setSize( $limit );
	}

	/**
	 * Sets the base Semantic MediaWiki query.
	 *
	 * @param string $baseQuery
	 */
	public function setBaseQuery( string $baseQuery ): void {
		try {
			$queryProcessor = new SMWQueryProcessor( $baseQuery );
			$query = $queryProcessor->toElasticSearchQuery();
		} catch ( MWException $exception ) {
			// The query is invalid
			Logger::getLogger()->critical( 'Tried to set invalid query as the base query' );
			return;
		}

		$this->baseQuery = $query[0];
	}

	/**
	 * Converts this class into a full ElasticSearch query.
	 *
	 * @return array A complete ElasticSearch query
     */
	public function toQuery(): array {
        // Add source filtering to the query
        $this->search->setSource( $this->sources );

		$newSearch = clone $this->search;

        $this->addPostFiltersToAggregations( $newSearch );
		$this->addSortsToSearch( $newSearch );

        $query = [
            "index" => $this->index,
            "body"  => $newSearch->toArray()
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

	/**
	 * Add the sorts to the Search object. Sorts are added in the following order (highest to lowest priority):
	 *
	 * - Sorts added by the frontend (already added)
	 * - Relevance
	 * - Fallback sorts
	 *
	 * @param Search $search
	 * @return void
	 */
	private function addSortsToSearch( Search $search ): void {
		$search->addSort( ( new RelevanceSort() )->toQuery() );

		foreach ( $this->fallbackSorts as $fallbackSort ) {
			$search->addSort( $fallbackSort->toQuery() );
		}
	}

    /**
     * Add the post filters to the aggregations in the given Search object.
     *
     * Post filters are applied after aggregations have been calculated. This makes it possible to have facets
     * that do not change after the filter they control is applied. This is useful for facets that should act like
     * a disjunction, where adding a filter should increase the number of results. However, this leaves us with the
     * problem that other aggregations that should be affected by the added filter are no longer accurate. To solve
     * this, we also add any post filter to the all other aggregations using a FilterAggregation.
     *
     * @param Search $search
     * @return void
     */
    private function addPostFiltersToAggregations( Search $search ): void {
        foreach ( $this->postFilters as [$filter, $occur] ) {
            /** @var AbstractFilter $filter */
            /** @var string $occur */

            $filterQuery = $filter->toQuery();
            $this->search->addPostFilter( $filterQuery, $occur );

            if ( !$filter instanceof PropertyFilter ) {
                continue;
            }

            foreach ( $search->getAggregations() as $aggregation ) {
                if ( !$aggregation instanceof FilterAggregation ) {
                    continue;
                }

                if (
                    isset( $aggregation->{self::AGGREGATION_PROPERTY_PARAMETER}) &&
                    $aggregation->{self::AGGREGATION_PROPERTY_PARAMETER}->getPID() === $filter->getField()->getPID()
                ) {
                    // This aggregation affects the same property
                    continue;
                }

                $aggregationFilter = $aggregation->getFilter();

                if (!$aggregationFilter instanceof BoolQuery) {
                    continue;
                }

                $aggregationFilter->add( $filterQuery );
            }
        }
    }
}
