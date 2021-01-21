<?php


namespace WSSearch;

use MediaWiki\MediaWikiServices;

/**
 * Class SearchQueryBuilder
 *
 * Class responsible for building an ElasticSearch query from the given parameters.
 *
 * @package WSSearch
 */
class SearchQueryBuilder {
    /**
     * @var string The name of the ES index to search
     */
    private $index;

    /**
     * @var int The size in number of characters of the search fragment
     */
    private $fragment_size;

    /**
     * @var int The number of fragments to return
     */
    private $number_of_fragments;

    /**
     * @var int The maximum number of search results
     */
    private $limit;

    /**
     * @var int The offset for the search
     */
    private $offset = 0;

    /**
     * @var array The aggregate filters to apply
     */
    private $aggregate_filters = [];

    /**
     * @var array
     */
    private $active_filters = [];

    /**
     * @var string
     */
    private $search_term;

    /**
     * @var array
     */
    private $aggregate_date_ranges;

    /**
     * Creates a new canonical SearchQueryBuilder from default values and configuration
     * variables.
     *
     * @return SearchQueryBuilder
     */
    public static function newCanonical(): SearchQueryBuilder {
        $config = MediaWikiServices::getInstance()->getMainConfig();

        try {
            $index = $config->get( "WSSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );
        } catch ( \ConfigException $exception ) {
            $index = "smw-data-" . strtolower( wfWikiID() );
        }

        try {
            $limit = $config->get( "WSSearchDefaultResultLimit" );
        } catch ( \ConfigException $exception ) {
            $limit = 10;
        }

        try {
            $fragment_size = $config->get( "WSSearchHighlightFragmentSize" );
        } catch ( \ConfigException $exception ) {
            $fragment_size = 150;
        }

        try {
            $number_of_fragments = $config->get( "WSSearchHighlightNumberOfFragments" );
        } catch ( \ConfigException $exception ) {
            $number_of_fragments = 1;
        }

        return new SearchQueryBuilder( $index, $limit, $fragment_size, $number_of_fragments );
    }

    /**
     * SearchQueryBuilder constructor.
     *
     * @param string $index
     * @param int $limit
     * @param int $fragment_size
     * @param int $number_of_fragments
     */
    public function __construct( string $index, int $limit, int $fragment_size, int $number_of_fragments ) {
        $this->index = $index;
        $this->limit = $limit;
        $this->fragment_size = $fragment_size;
        $this->number_of_fragments = $number_of_fragments;
    }

    /**
     * Set the name of the ElasticSearch index to search.
     *
     * @param string $index
     */
    public function setIndex( string $index ) {
        $this->index = $index;
    }

    /**
     * Sets the offset for the search. The first $offset search
     * result will be skipped.
     *
     * @param int $offset
     */
    public function setOffset( int $offset ) {
        $this->offset = $offset;
    }

    /**
     * Sets the limit for the number of search results to
     * return.
     *
     * @param int $limit
     */
    public function setLimit( int $limit ) {
        $this->limit = $limit;
    }

    /**
     * Sets the main filter (condition) for this search.
     *
     * @param PropertyInfo $property
     * @param string $property_value
     */
    public function setMainCondition( PropertyInfo $property, string $property_value ) {
        $this->active_filters[] = $this->buildTermField( $property, $property_value );
    }

    /**
     * Sets the size in number of characters of the search
     * fragment (snippet).
     *
     * @param int $size
     */
    public function setFragmentSize( int $size ) {
        $this->fragment_size = $size;
    }

    /**
     * Sets the number of fragments to return.
     *
     * @param int $number_of_fragments
     */
    public function setNumberOfFragments( int $number_of_fragments ) {
        $this->number_of_fragments = $number_of_fragments;
    }

    /**
     * The aggregate (facet) filters to apply.
     *
     * @param array $aggregate_filters
     */
    public function setAggregateFilters(array $aggregate_filters ) {
        $this->aggregate_filters = $aggregate_filters;
    }

    /**
     * Sets the search term.
     *
     * @param string $search_term
     */
    public function setSearchTerm( string $search_term ) {
        $this->search_term = preg_match('/"/', $search_term ) ? $search_term : "*$search_term*";
    }

    /**
     * Sets the date ranges to use as aggregate filters.
     *
     * @param array $date_ranges
     */
    public function setAggregateDateRanges( array $date_ranges ) {
        $this->aggregate_date_ranges = $date_ranges;
    }

    /**
     * Builds the query.
     *
     * @return array
     */
    public function buildQuery(): array {
        $main_query = [];

        // Set the ElasticSearch index
        $main_query["index"] = $this->index;

        // Set the offset
        $main_query["from"] = $this->offset;

        // Set the number of results to return
        $main_query["size"] = $this->limit;

        // Get a reference to the query "body"
        $body =& $main_query["body"];

        // Set the "highlight" parameters
        $body["highlight"] = [
            "pre_tags" => ["<b>"],
            "post_tags" => ["</b>"],
            "fields" => [
                "text_raw" => [
                    "fragment_size" => $this->fragment_size,
                    "number_of_fragments" => $this->number_of_fragments
                ]
            ]
        ];

        // Set the aggregate filters
        $body["aggs"] = $this->buildAggregateFilters();

        // Get a reference to the main part of the Elastic Search query
        $query =& $body["query"];
        $constant_score =& $query["constant_score"];
        $filter =& $constant_score["filter"];

        $filter = $this->buildMainFilter();

        return $main_query;
    }

    /**
     * Builds the "main filter" that filters on the main property condition (i.e. "Class=Handboek") and the search term.
     */
    private function buildMainFilter(): array {
        $filter["bool"]["must"][] = [
            'bool' => [
                'filter' => $this->active_filters
            ]
        ];

        if ( isset( $this->search_term ) ) {
            $filter["bool"]["must"][] = [
                "bool" => [
                    "must" => [
                        "query_string" => [
                            "fields" => [
                                "subject.title^8",
                                "text_copy^5",
                                "text_raw",
                                "attachment.title^3",
                                "attachment.content"
                            ],
                            "query" => $this->search_term,
                            "minimum_should_match" => 1
                        ]
                    ]
                ]
            ];
        }

        return $filter;
    }

    /**
     * Builds the aggregate filters for the query.
     *
     * @return array
     */
    private function buildAggregateFilters(): array {
        $filters = $this->aggregate_filters;

        if ( isset( $this->aggregate_date_ranges ) ) {
            $filters["Date"] = [
                "date_range" => [
                    "field" => "P:29.datField",
                    "ranges" => $this->aggregate_date_ranges
                ]
            ];
        }

        return $filters;
    }

    /**
     * Sets the active filters for this query.
     *
     * @param array $filters
     */
    public function setActiveFilters( array $filters ) {
        foreach ( $filters as $filter ) {
            if ( isset( $filter["range"] ) ) {
                unset( $filter["value"] );
                unset( $filter["key"] );

                $this->active_filters[] = $filter;
            } else {
                $filter_property = new PropertyInfo( $filter["key"] );
                $term_field = $this->buildTermField( $filter_property, $filter["value"] );
                $this->active_filters[] = $term_field;
            }
        }
    }

    /**
     * Builds a term field from the given property.
     *
     * @param PropertyInfo $property
     * @param string $value
     *
     * @return array The built term field
     */
    private function buildTermField( PropertyInfo $property, string $value ): array {
        return [
            "term" => [
                "P:" . $property->getPropertyID() . "." . $property->getPropertyType() . ".keyword" => $value
            ]
        ];
    }
}