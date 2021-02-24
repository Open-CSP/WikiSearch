<?php

namespace WSSearch\QueryEngine;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Compound\ConstantScoreQuery;
use ONGR\ElasticsearchDSL\Search;
use WSSearch\QueryEngine\Filter\Filter;
use WSSearch\QueryEngine\Filter\SearchTermFilter;

class QueryEngine {
    const TEXT_INDEX_NAME = "text_raw";

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
     * @var BoolQuery The main filter query.
     */
    private $filter_query;

    /**
     * @var BoolQuery The main free text search term query.
     */
    private $search_term_query;

    /**
     * QueryEngine constructor.
     */
    public function __construct() {
        $this->elasticsearch_search = new Search();

        $config = MediaWikiServices::getInstance()->getMainConfig();

        $this->elasticsearch_index = $config->get( "WSSearchElasticStoreIndex" ) ?: "smw-data-" . strtolower( wfWikiID() );
        $this->elasticsearch_search->setSize( $config->get( "WSSearchDefaultResultLimit" ) );

        $highlight = new Highlight();
        $highlight->addParameter( "pre_tags", "<b>" );
        $highlight->addParameter( "post_tags", "</b>");
        $highlight->addParameter( self::TEXT_INDEX_NAME, [
            "fragment_size" => $config->get( "WSSearchHighlightFragmentSize" ),
            "number_of_fragments" => $config->get( "WSSearchHighlightNumberOfFragments" )
        ]);

        $this->elasticsearch_search->addHighlight( $highlight );

        // FIXME: Maybe refactor this
        $this->filter_query = new BoolQuery();
        $this->search_term_query = new BoolQuery();

        $query = new BoolQuery();
        $query->add( $this->filter_query, BoolQuery::MUST );
        $query->add( $this->search_term_query, BoolQuery::MUST );

        $this->elasticsearch_search->addQuery( new ConstantScoreQuery( $query ) );
    }

    /**
     * Sets the main free text search term. This function essentially adds a filter that filters and sorts pages
     * based on whether the search term appears in certain special Semantic MediaWiki properties, such as
     * subject.title and text_raw.
     *
     * @param string $term
     */
    public function setSearchTerm( string $term ) {
        $this->search_term_query->add( ( new SearchTermFilter( $term ) )->toQuery() );
    }

    /**
     * Adds filters to apply to the query.
     *
     * @param Filter[] $filters
     */
    public function addFilters( array $filters ) {
        foreach ( $filters as $filter ) {
            $this->addFilter( $filter );
        }
    }

    /**
     * Adds a filter to apply to the query.
     *
     * @param Filter $filter
     */
    public function addFilter( Filter $filter ) {
        $this->filter_query->add( $filter->toQuery(), BoolQuery::FILTER );
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
     * Converts this class into a full ElasticSearch query, that can be used like so:
     *
     * $this->elasticsearch_client->search( $this->query_engine->toArray() );
     */
    public function toArray(): array {
        return [
            "index" => $this->elasticsearch_index,
            "body" => $this->elasticsearch_search->toArray()
        ];
    }
}