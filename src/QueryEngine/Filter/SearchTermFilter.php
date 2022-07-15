<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use WikiSearch\SearchEngineException;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class SearchTermFilter
 *
 * @package WikiSearch\QueryEngine\Filter
 */
class SearchTermFilter extends AbstractFilter {
	/**
	 * @var array
	 */
	private array $chained_properties = [];

	/**
	 * @var array
	 */
	private array $property_fields = [];

	/**
	 * @var string The search term to filter on
	 */
	private string $search_term;

	/**
	 * @var string
	 */
	private string $default_operator;

	/**
	 * SearchTermFilter constructor.
	 *
	 * @param string $search_term
	 * @param PropertyFieldMapper[] $properties
	 * @param string $default_operator
	 */
	public function __construct( string $search_term, array $properties = [], string $default_operator = "or" ) {
		$this->search_term = $search_term;
		$this->default_operator = $default_operator;

		if ( $properties !== [] ) {
			foreach ( $properties as $mapper ) {
				if ( $mapper->isChained() ) {
					$this->chained_properties[] = $mapper;
				} else {
					$this->property_fields[] = $mapper->getWeightedPropertyField();
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
	public function setSearchTerm( string $search_term ): void {
		$this->search_term = $search_term;
	}

	/**
	 * @inheritDoc
	 *
	 * @throws SearchEngineException
	 * @throws \MWException
	 */
	public function filterToQuery(): BoolQuery {
		$bool_query = new BoolQuery();

		foreach ( $this->chained_properties as $property ) {
			// Construct a new chained subquery for each chained property and add it to the bool query
			$property_text_filter = new PropertyTextFilter( $property, $this->search_term, $this->default_operator );
			$filter = new ChainedPropertyFilter( $property_text_filter, $property->getChainedPropertyFieldMapper() );
			$bool_query->add( $filter->toQuery(), BoolQuery::SHOULD );
		}

		if ( $this->property_fields !== [] ) {
			$query_string_query = new QueryStringQuery( $this->search_term );
			$query_string_query->setParameters( [
				"fields" => $this->property_fields,
				"default_operator" => $this->default_operator,
				"analyze_wildcard" => true
			] );

			$bool_query->add( $query_string_query, BoolQuery::SHOULD );
		}

		return $bool_query;
	}
}
