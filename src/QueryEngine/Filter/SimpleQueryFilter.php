<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use WSSearch\SearchEngine;
use WSSearch\SearchEngineException;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class SimpleQueryFilter
 *
 * @package WSSearch\QueryEngine\Filter
 */
class SimpleQueryFilter extends AbstractFilter {
	use QueryPreparationTrait;

	/**
	 * @var string The query to filter on
	 */
	private $query;

	/**
	 * @var string[]
	 */
	private $fields;

	/**
	 * SearchTermFilter constructor.
	 *
	 * @param string $query
	 * @param string[] $properties
	 */
	public function __construct( string $query, array $properties ) {
		$this->query = $query;
		$this->fields = array_map(function( string $property_name ): string {
			return ( new PropertyFieldMapper( $property_name ) )->getPropertyField();
		}, $properties );
	}

	/**
	 * Sets the query to filter on.
	 *
	 * @param string $query
	 */
	public function setQuery( string $query ) {
		$this->query = $query;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): BoolQuery {
		$search_term = $this->prepareQuery( $this->query );

		$bool_query = new BoolQuery();
		$query_string_query = new QueryStringQuery( $search_term );

		$query_string_query->setParameters( [
			"fields" => $this->fields
		] );

		$bool_query->add( $query_string_query, BoolQuery::SHOULD );

		return $bool_query;
	}
}
