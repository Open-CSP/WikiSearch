<?php

namespace WikiSearch\QueryEngine\Filter;

use MediaWiki\MediaWikiServices;
use MWException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WikiSearch\QueryEngine\Factory\QueryEngineFactory;
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
        public PropertyFilter $filter
    ) {}

	/**
	 * Returns the property field mapper corresponding to this filter.
	 *
	 * @return PropertyFieldMapper
	 */
	public function getField(): PropertyFieldMapper {
		return $this->filter->getField()->getChainedPropertyFieldMapper();
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
		$queryEngine = QueryEngineFactory::newQueryEngine();
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
		$results = WikiSearchServices::getElasticsearchClientFactory()
            ->newElasticsearchClient()
			->search( $query );

		return array_map( function ( array $hit ): int {
			return intval( $hit["_id"] );
		}, $results["hits"]["hits"] );
	}
}
