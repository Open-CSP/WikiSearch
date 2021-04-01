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

    private $chained_properties = [];
    private $property_fields = [];

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

            $property_field_mappers = array_map( function ( string $property ): PropertyFieldMapper {
                return new PropertyFieldMapper( $property );
            }, $properties );

            foreach ( $property_field_mappers as $mapper ) {
            	assert( $mapper instanceof PropertyFieldMapper );

                if ( $mapper->isChained() ) {
                    $this->chained_properties[] = $mapper;
                } else {
                    $this->property_fields[] = $mapper->getPropertyField();
                }
            }
        } else {
            $this->property_fields = [
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

        $bool_query = new BoolQuery();

        foreach ( $this->chained_properties as $property ) {
            // Construct a new chained subquery for each chained property and add it to the bool query
            $property_text_filter = new PropertyTextFilter( $property, $search_term, $default_operator );
            $filter = new ChainedPropertyFilter( $property_text_filter, $property->getChainedPropertyFieldMapper() );
            $bool_query->add( $filter->toQuery(), BoolQuery::SHOULD );
        }

        if ( $this->property_fields !== [] ) {
            $query_string_query = new SimpleQueryStringQuery( $search_term );
            $query_string_query->setParameters( [
                "fields" => $this->property_fields,
                "minimum_should_match" => 1,
                "default_operator" => $default_operator
            ] );

            $bool_query->add( $query_string_query, BoolQuery::SHOULD );
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