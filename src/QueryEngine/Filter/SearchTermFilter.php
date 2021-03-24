<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;

/**
 * Class SearchTermFilter
 *
 * @package WSSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-query-string-query.html
 */
class SearchTermFilter implements Filter {
    /**
     * @var string The search term to filter on
     */
    private $search_term;

    /**
     * @var string[] The fields to search through for the term
     */
    private $fields = [
        "subject.title^8",
        "text_copy^5",
        "text_raw",
        "attachment.title^3",
        "attachment.content"
    ];

    /**
     * SearchTermFilter constructor.
     *
     * @param string $search_term
     * @param array|null $fields
     */
    public function __construct( string $search_term, array $fields = null ) {
        $this->search_term = $search_term;

        if ( is_array( $fields ) ) {
            $this->fields = $fields;
        }
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
     * Sets the fields to search through for the term.
     *
     * @param string[] $fields
     */
    public function setFields( array $fields ) {
        $this->fields = $fields;
    }

    /**
     * @inheritdoc
     */
    public function toQuery(): BoolQuery {
        $search_term = $this->prepareSearchTerm( $this->search_term );

        $query_string_query = new QueryStringQuery( $search_term );
        $query_string_query->setParameters( [
            "fields" => $this->fields,
            "minimum_should_match" => 1
        ] );

        $bool_query = new BoolQuery();
        $bool_query->add( $query_string_query, BoolQuery::MUST );

        return $bool_query;
    }

    /**
     * Prepares the search term for use with ElasticSearch.
     *
     * @param string $search_term
     * @return string
     */
    private function prepareSearchTerm( string $search_term ): string {
        $search_term = trim( $search_term );
        $term_length = strlen( $search_term );

        if ( $term_length === 0 ) {
            return "*";
        }

        return $search_term;
    }
}