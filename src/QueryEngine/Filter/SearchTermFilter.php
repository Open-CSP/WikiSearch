<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery;
use WSSearch\SearchEngine;
use WSSearch\SearchEngineException;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class SearchTermFilter
 *
 * @package WSSearch\QueryEngine\Filter
 */
class SearchTermFilter implements Filter {
    const OP_AND = "and";
    const OP_OR = "or";

    /**
     * @var string[] The fields to search through for the term in the main "simple query string" query
     */
    public $query_string_fields = [];

    /**
     * @var PropertyFieldMapper[] Chained fields to search through
     */
    public $chained_properties = [];

    /**
     * @var string The search term to filter on
     */
    private $search_term;

    /**
     * SearchTermFilter constructor.
     *
     * @param string $search_term
     * @throws SearchEngineException
     */
    public function __construct( string $search_term ) {
        $this->search_term = $search_term;
        $search_engine_config = SearchEngine::getInstance()->getConfig();

        if ( $search_engine_config->getSearchParameter( "search term properties" ) ) {
            $properties = $search_engine_config->getSearchParameter( "search term properties" );
            $properties = explode( ",", $properties );
            $properties = array_map( "trim", $properties );

            $property_field_mappers = array_map( [ PropertyFieldMapper::class, "__construct" ], $properties );

            foreach ( $property_field_mappers as $mapper ) {
                assert( $mapper instanceof PropertyFieldMapper );

                if ( $mapper->getChainedPropertyFieldMapper() !== null ) {
                    $this->chained_properties[] = $mapper;
                } else {
                    $this->query_string_fields[] = $mapper->getPropertyField();
                }
            }
        } else {
            $this->chained_properties = [
                "subject.title^8",
                "text_copy^5",
                "text_raw",
                "attachment.title^3",
                "attachment.content"
            ];
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
     * @inheritDoc
     *
     * @throws SearchEngineException
     * @throws \MWException
     */
    public function toQuery(): BoolQuery {
        $search_term = $this->prepareSearchTerm( $this->search_term );

        $search_engine_config = SearchEngine::getInstance()->getConfig();
        $default_operator = trim( $search_engine_config->getSearchParameter( "default operator" ) );
        $default_operator = $default_operator === "and" ? self::OP_AND : self::OP_OR;

        $chained_property_queries = [];

        foreach ( $this->chained_properties as $property ) {
            $property_text_filter = new PropertyTextFilter(
                $property,
                $search_term,
                $default_operator
            );

            $filter = new ChainedPropertyFilter( $property_text_filter, $property->getChainedPropertyFieldMapper() );
            $chained_property_queries[] = $filter->toQuery();
        }

        $query_string_query = new SimpleQueryStringQuery( $search_term );
        $query_string_query->setParameters( [
            "fields" => $this->query_string_fields,
            "minimum_should_match" => 1,
            "default_operator" => $default_operator
        ] );

        $bool_query = new BoolQuery();
        $bool_query->add( $query_string_query, BoolQuery::SHOULD );

        foreach ( $chained_property_queries as $chained_property_query ) {
            $bool_query->add( $chained_property_query, BoolQuery::SHOULD );
        }

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