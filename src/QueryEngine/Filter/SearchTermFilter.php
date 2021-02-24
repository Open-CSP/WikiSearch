<?php


namespace WSSearch\QueryEngine\Filter;


use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;

class SearchTermFilter extends Filter {
    /**
     * @var string The search term to filter on
     */
    private $search_term;

    /**
     * SearchTermFilter constructor.
     *
     * @param string $search_term
     */
    public function __construct( string $search_term ) {
        $this->search_term = $search_term;
    }

    /**
     * Sets the search term to filter on.
     *
     * @param string $search_term
     */
    public function setSearchTerm( string $search_term ) {
        $this->search_term = $search_term;
    }

    /**
     * Converts the object to a BuilderInterface for use in the QueryEngine.
     *
     * @return BuilderInterface
     */
    public function toQuery(): BuilderInterface {
        $query_string_query = new QueryStringQuery( $this->search_term );
        $query_string_query->setParameters( [
            "fields" => [
                "subject.title^8",
                "text_copy^5",
                "text_raw",
                "attachment.title^3",
                "attachment.content"
            ],
            "minimum_should_match" => 1
        ] );

        return $query_string_query;
    }
}