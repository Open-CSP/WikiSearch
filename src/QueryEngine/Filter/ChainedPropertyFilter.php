<?php

namespace WikiSearch\QueryEngine\Filter;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use MediaWiki\MediaWikiServices;
use MWException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WikiSearch\Factory\QueryEngineFactory;
use WikiSearch\SMW\PropertyFieldMapper;
use WikiSearch\WikiSearchServices;

/**
 * This class is used to allow searching of property chains. It takes an initial filter and a property, and
 * recursively constructs a new filter from the results of the initial filter until the end of the
 * property chain is reached.
 *
 * This filter only works on a single property field mapper.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.8//query-dsl-terms-query.html
 */
class ChainedPropertyFilter extends PropertyFilter {
	/**
	 * @param PropertyFilter $filter The initial filter to use to get the values for the to be constructed Terms filter
	 */
	public function __construct(
        private PropertyFilter $filter
    ) {
        parent::__construct( $this->filter->getField()->getChainedPropertyFieldMapper() );
    }

	/**
	 * @inheritDoc
	 *
	 * @return BoolQuery
     */
	public function filterToQuery(): BoolQuery {
		$query = $this->constructSubqueryFromFilter( $this->filter );
		$terms = $this->getTermsFromSubquery( $query );
		$property = $this->getField();

		$filter = new PagesPropertyFilter( $property, $terms );

		if ( $property->isChained() ) {
			$filter = new ChainedPropertyFilter( $filter );
		}

		return $filter->toQuery();
	}

	/**
	 * Constructs a new ElasticSearch subquery from the given Filter.
	 *
	 * @param AbstractFilter $filter
	 * @return array
     */
	private function constructSubqueryFromFilter( AbstractFilter $filter ): array {
		$queryEngine = WikiSearchServices::getQueryEngineFactory()->newQueryEngine();
		$queryEngine->addConstantScoreFilter( $filter );

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$queryEngine->setLimit( $config->get( "WikiSearchMaxChainedQuerySize" ) );

		return $queryEngine->toQuery();
	}

	/**
	 * Executes the given (sub)query and returns the terms extracted from it.
	 *
	 * @param array $query
	 * @return array
	 */
	private function getTermsFromSubquery( array $query ): array {
        try {
            // FIXME: Don't use an actual search here
            $results = WikiSearchServices::getElasticsearchClientFactory()
                ->newElasticsearchClient()
                ->search($query);
        } catch (ClientResponseException|ServerResponseException) {
            $results = [];
        }

        if ( !is_array( $results ) ) {
            // Elasticsearch >= 8.x
            $results = $results->asArray();
        }

		return array_map( function ( array $hit ): int {
			return intval( $hit["_id"] );
		}, $results["hits"]["hits"] );
	}
}
