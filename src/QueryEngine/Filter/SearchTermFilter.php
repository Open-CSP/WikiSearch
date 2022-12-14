<?php

namespace WikiSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class SearchTermFilter
 *
 * @package WikiSearch\QueryEngine\Filter
 */
class SearchTermFilter extends AbstractFilter {
	use QueryPreparationTrait;

	/**
	 * @var PropertyFieldMapper[]
	 */
	private array $chained_properties = [];

	/**
	 * @var string[]|PropertyFieldMapper[]
	 */
	private array $property_fields = [
		"subject.title.search^8",
		"subject.title^8",
		"text_copy.search^5",
		"text_copy^5",
		"text_raw.search",
		"text_raw",
		"attachment.title^3",
		"attachment.content"
	];

	/**
	 * @var string The search term to filter on
	 */
	private string $search_term;

	/**
	 * @var string The default operator to use
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
			$this->property_fields = [];
			foreach ( $properties as $mapper ) {
				if ( $mapper->isChained() ) {
					// Chained properties need to be handled differently, see filterToQuery
					$this->chained_properties[] = $mapper;
				} else {
					$this->property_fields[] = $mapper->getWeightedPropertyField();

					if ( $mapper->hasSearchSubfield() ) {
						$this->property_fields[] = $mapper->getWeightedSearchField();
					}
				}
			}
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
	 */
	public function filterToQuery(): BoolQuery {
		$search_term = $this->prepareQuery( $this->search_term );
		$bool_query = new BoolQuery();

		foreach ( $this->chained_properties as $property ) {
			// Construct a new chained sub query for each chained property and add it to the bool query
			$filter = new ChainedPropertyFilter( new PropertyTextFilter( $property, $search_term, $this->default_operator ) );
			$bool_query->add( $filter->toQuery(), BoolQuery::SHOULD );
		}

		if ( $this->property_fields !== [] ) {
			$query_string_query = new QueryStringQuery( $search_term );
			$query_string_query->setParameters( [
				"fields" => $this->property_fields,
				"default_operator" => $this->default_operator,
				"analyze_wildcard" => true,
				"tie_breaker" => 1,
				"lenient" => true
			] );

			$bool_query->add( $query_string_query, BoolQuery::SHOULD );
		}

		return $bool_query;
	}
}
