<?php

namespace WSSearch\SMW;

use SMW\Elastic\ElasticFactory;

class SMWQueryProcessor {
    /**
     * @var string
     */
    private $query;

    /**
     * SMWQueryProcessor constructor.
     *
     * @param string $query The Semantic MediaWiki query to process
     */
    public function __construct( string $query ) {
        $this->query = $query;
    }

    /**
     * Sets the SemanticMediaWiki query to process.
     *
     * @param string $query
     */
    public function setQuery( string $query ) {
        $this->query = $query;
    }

    /**
     * Converts the SMW query to an ElasticSearch query.
     *
     * @return array
     * @throws \MWException When the query is invalid
     */
    public function toElasticSearchQuery(): array {
        list( $query_string, $parameters, $printouts ) = \SMWQueryProcessor::getComponentsFromFunctionParams(
            [$this->query],
            false
        );

        $query = \SMWQueryProcessor::createQuery(
            $query_string,
            \SMWQueryProcessor::getProcessedParams( $parameters, $printouts ),
            \SMWQueryProcessor::SPECIAL_PAGE,
            '',
            $printouts
        );

        $query->setOption( \SMWQuery::MODE_DEBUG, 'API' );

        $elastic_factory = new ElasticFactory();
        $store = \SMW\ApplicationFactory::getInstance()->getStore();
        $query_engine = $elastic_factory->newQueryEngine( $store );

        $query_engine->getQueryResult( $query );
        $query_info = $query_engine->getQueryInfo();

        if ( !isset( $query_info["elastic"] ) ) {
            throw new \MWException( "Invalid query" );
        }

        return $query_info["elastic"];
    }
}