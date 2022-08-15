<?php

namespace WikiSearch\QueryEngine\Filter;

use ConfigException;
use Elasticsearch\ClientBuilder;
use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use MWException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use WikiSearch\Logger;
use WikiSearch\QueryEngine\Factory\QueryEngineFactory;
use WikiSearch\SearchEngineException;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class ChainedPropertyTermsFilter
 *
 * This class is used to allow searching of property chains. It takes an initial filter and a property, and
 * recursively constructs a new filter from the results of the initial filter until the end of the
 * property chain is reached.
 *
 * This filter only works on a single property field mapper.
 *
 * @package WikiSearch\QueryEngine\Filter
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.8//query-dsl-terms-query.html
 */
class ChainedPropertyFilter extends PropertyFilter {
	/**
	 * @var PropertyFilter The initial filter to use to get the terms for the to be constructed Terms filter
	 */
	private PropertyFilter $filter;

	/**
	 * ChainedPropertyFilter constructor.
	 *
	 * @param PropertyFilter $filter The initial filter to use to get the values for the to be constructed Terms filter
	 */
	public function __construct( PropertyFilter $filter ) {
		$this->filter = $filter;

		if ( !$filter->getProperty()->isChained() ) {
			throw new InvalidArgumentException( "The given filter must be applied to chained property" );
		}
	}

	/**
	 * Returns the property field mapper corresponding to this filter.
	 *
	 * @return PropertyFieldMapper
	 */
	public function getProperty(): PropertyFieldMapper {
		return $this->filter->getProperty()->getChainedPropertyFieldMapper();
	}

	/**
	 * @inheritDoc
	 *
	 * @return BoolQuery
	 * @throws MWException
     */
	public function filterToQuery(): BoolQuery {
		$query = $this->constructSubqueryFromFilter( $this->filter );
		$terms = $this->getTermsFromSubquery( $query );
		$property = $this->getProperty();

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
	 * @throws MWException
	 */
	private function constructSubqueryFromFilter( AbstractFilter $filter ): array {
		$query_engine = QueryEngineFactory::fromNull();
		$query_engine->addConstantScoreFilter( $filter );

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$query_engine->setLimit( $config->get( "WikiSearchMaxChainedQuerySize" ) );

		return $query_engine->toArray();
	}

	/**
	 * Executes the given (sub)query and returns the terms extracted from it.
	 *
	 * @param array $query
	 * @return array
	 */
	private function getTermsFromSubquery( array $query ): array {
		$results = ClientBuilder::create()
			->setHosts( QueryEngineFactory::fromNull()->getElasticHosts() )
			->build()
			->search( $query );
		$hits = $results["hits"]["hits"];

		return array_map( function ( array $hit ): int {
			/*
			 * Below is an example of what $hit may look like:
			 *
			 * {
			 *      "_index": "smw-data-csp_wikibase_nl-v2",
			 *      "_type": "data",
			 *      "_id": "2673",
			 *      "_score": 1
			 * }
			 */

			return intval( $hit["_id"] );
		}, $hits );
	}
}
