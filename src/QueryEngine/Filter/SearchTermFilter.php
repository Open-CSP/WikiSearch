<?php


namespace WSSearch\QueryEngine\Filter;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use WSSearch\SearchEngine;
use WSSearch\SearchEngineException;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class SearchTermFilter
 *
 * @package WSSearch\QueryEngine\Filter
 */
class SearchTermFilter extends AbstractFilter {
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

		if ( SearchEngine::$config->getSearchParameter( "search term properties" ) ) {
			$properties = SearchEngine::$config->getSearchParameter( "search term properties" );

			foreach ( $properties as $mapper ) {
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

		$default_operator = SearchEngine::$config->getSearchParameter( "default operator" );
		$default_operator = $default_operator === "and" ? self::OP_AND : self::OP_OR;

		$bool_query = new BoolQuery();

		foreach ( $this->chained_properties as $property ) {
			// Construct a new chained subquery for each chained property and add it to the bool query
			$property_text_filter = new PropertyTextFilter( $property, $search_term, $default_operator );
			$filter = new ChainedPropertyFilter( $property_text_filter, $property->getChainedPropertyFieldMapper() );
			$bool_query->add( $filter->toQuery(), BoolQuery::SHOULD );
		}

		if ( $this->property_fields !== [] ) {
			$query_string_query = new QueryStringQuery( $search_term );
			$query_string_query->setParameters( [
				"fields" => $this->property_fields,
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

		// Disable regex searches by replacing each "/" with " "
		$search_term = str_replace( "/", ' ', $search_term );

		// Don't insert wildcard around terms when the user is performing an "advanced query"
		$advanced_search_chars = ["\"", "'", "AND", "NOT", "OR", "~", "(", ")", "?"];
		$is_advanced_query = array_reduce( $advanced_search_chars, function( bool $carry, $char ) use ( $search_term ) {
			return $carry ?: strpos( $search_term, $char ) !== false;
		}, false );

		if ( !$is_advanced_query ) {
			$search_term = $this->insertWildcards( $search_term );
		}

		// Disable certain search operators by escaping them
		$search_term = str_replace( ":", '\:', $search_term );
		$search_term = str_replace( "+", '\+', $search_term );
		$search_term = str_replace( "-", '\-', $search_term );
		$search_term = str_replace( "=", '\=', $search_term );

		return $search_term;
	}

	/**
	 * Inserts wild cards around each term in the provided search string.
	 *
	 * @param string $search_string
	 * @return string
	 */
	private function insertWildcards( string $search_string ): string {
		$terms = preg_split( "/((?<=\w)\b\s*)/", $search_string, -1, PREG_SPLIT_DELIM_CAPTURE );

		// $terms is now an array where every even element is a term (0 is a term, 2 is a term, etc.), and
		// every odd element the delimiter between that term and the next term. Calling implode() on
		// $terms gives back the original search string

		$num_terms = count( $terms );

		// Insert quotes around each term
		for ( $idx = 0; $idx < $num_terms; $idx++ ) {
			$is_term = ($idx % 2) === 0 && !empty( $terms[$idx] );

			if ( $is_term ) {
				$terms[$idx] = "*{$terms[$idx]}*";
			}
		}

		// Join everything together again to get the search string
		return implode( "", $terms );
	}
}
